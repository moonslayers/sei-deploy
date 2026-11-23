<?php

namespace App\Services;

use App\Models\Backend;
use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TerminalService
{
    public const MAX_SESSIONS = 3;

    /** @var callable */
    protected $runner;

    public function __construct(?callable $runner = null)
    {
        $this->runner = $runner ?? function (string $command): array {
            $output = [];
            $rc = 0;
            exec($command, $output, $rc);

            return [$rc, implode("\n", $output)];
        };
    }

    public function setRunner(callable $runner): void
    {
        $this->runner = $runner;
    }

    public function run(string $command): array
    {
        return ($this->runner)($command);
    }

    protected function tmuxExists(): bool
    {
        [$rc] = $this->run('command -v tmux');

        return $rc === 0;
    }

    protected function maxSessions(): int
    {
        return (int) config('terminal.max_sessions', self::MAX_SESSIONS);
    }

    protected function socketName(): string
    {
        return (string) config('terminal.socket', 'sei-terminal');
    }

    protected function ensureConf(): string
    {
        $logDir = storage_path('app/terminal');
        if (! is_dir($logDir)) {
            $created = @mkdir($logDir, 0775, true);
            if (! $created && ! is_dir($logDir)) {
                Log::warning('TerminalService: failed to create terminal log directory', [
                    'log_dir' => $logDir,
                    'user' => self::effectiveUser(),
                ]);
            }
        }

        $conf = $logDir.DIRECTORY_SEPARATOR.(string) config('terminal.conf_filename', 'tmux.conf');
        if (! is_file($conf)) {
            $shell = (string) config('terminal.default_shell', '/bin/bash');
            @file_put_contents($conf, 'set -g default-shell '.$shell."\n");
        }

        return $conf;
    }

    /**
     * Env vars inherited from the php-fpm parent (sei-deploy) that must never
     * leak into tmux panes, otherwise artisan of sibling backends resolves the
     * wrong DB/cache/queue/session config. Keep PATH/HOME/TERM/TMUX untouched.
     *
     * NOTE: the contaminated tmux server already running in prod must be killed
     * once with `tmux -L sei-terminal kill-server` so a clean server is born;
     * the `env -u` below applies from the very first `new-session`.
     */
    public const SANITIZED_ENV_VARS = [
        'DB_CONNECTION', 'DB_DATABASE', 'DB_HOST', 'DB_PORT', 'DB_USERNAME',
        'DB_PASSWORD', 'DB_URL',
        'APP_ENV', 'APP_KEY',
        'CACHE_DRIVER', 'CACHE_STORE',
        'QUEUE_CONNECTION',
        'SESSION_DRIVER',
        'REDIS_HOST', 'REDIS_PASSWORD', 'REDIS_PORT', 'REDIS_CLIENT', 'REDIS_DB', 'REDIS_CACHE_DB',
        'MEMCACHED_HOST', 'MEMCACHED_PORT', 'MEMCACHED_USERNAME', 'MEMCACHED_PASSWORD', 'MEMCACHED_PERSISTENT_ID',
    ];

    protected function envSanitizePrefix(): string
    {
        return 'env '.implode(' ', array_map(
            static fn (string $v): string => '-u '.$v,
            self::SANITIZED_ENV_VARS
        )).' ';
    }

    protected function tmuxPrefix(): string
    {
        $conf = escapeshellarg($this->ensureConf());

        return $this->envSanitizePrefix().'tmux -f '.$conf.' -L '.escapeshellarg($this->socketName());
    }

    public function start(Backend $backend, User $user): TerminalSession
    {
        if (! $this->tmuxExists()) {
            throw new \RuntimeException('tmux binary not found. Install tmux on the server (e.g. apt install tmux) and ensure it is on PATH.');
        }

        $resolved = $backend->resolved_path;
        if (! is_dir($resolved)) {
            throw new \RuntimeException('Backend directory not found: '.$resolved);
        }

        $existing = TerminalSession::where('user_id', $user->id)
            ->where('backend_id', $backend->id)
            ->whereNull('closed_at')
            ->latest('id')
            ->first();
        if ($existing) {
            $existing->update(['last_activity_at' => now()]);

            return $existing;
        }

        $active = TerminalSession::where('user_id', $user->id)->whereNull('closed_at')->count();
        if ($active >= $this->maxSessions()) {
            throw new \RuntimeException('Too many concurrent terminal sessions (max '.$this->maxSessions().').');
        }

        $uuid = (string) Str::uuid();
        $tmuxName = 'sei-'.$uuid;

        $session = TerminalSession::create([
            'uuid' => $uuid,
            'backend_id' => $backend->id,
            'user_id' => $user->id,
            'tmux_session' => $tmuxName,
            'last_activity_at' => now(),
            'output_offset' => 0,
        ]);

        $logDir = storage_path('app/terminal');
        if (! is_dir($logDir)) {
            $created = @mkdir($logDir, 0775, true);
            if (! $created && ! is_dir($logDir)) {
                Log::warning('TerminalService: failed to create terminal log directory', [
                    'log_dir' => $logDir,
                    'user' => self::effectiveUser(),
                ]);
            }
        }
        $logFile = $logDir.DIRECTORY_SEPARATOR.$uuid.'.log';
        $written = @file_put_contents($logFile, '');
        if ($written === false) {
            Log::warning('TerminalService: failed to initialize terminal log file', [
                'log_file' => $logFile,
                'user' => self::effectiveUser(),
            ]);
        }

        // Clean stale/orphaned session with same name before creating.
        $this->run($this->tmuxPrefix().' kill-session -t '.escapeshellarg($tmuxName).' 2>/dev/null');

        [$rc] = $this->run($this->tmuxPrefix().' new-session -d -s '.escapeshellarg($tmuxName).' -c '.escapeshellarg($resolved));
        if ($rc !== 0) {
            $session->update(['closed_at' => now()]);

            throw new \RuntimeException('Failed to create tmux session.');
        }

        [$pipeRc] = $this->run($this->tmuxPrefix().' pipe-pane -t '.escapeshellarg($tmuxName).' -o '.escapeshellarg('cat >> '.$logFile));
        if ($pipeRc !== 0) {
            Log::warning('TerminalService: failed to configure tmux pipe-pane', [
                'tmux_session' => $tmuxName,
                'log_file' => $logFile,
                'user' => self::effectiveUser(),
            ]);
        }

        [$rc, $capture] = $this->run($this->tmuxPrefix().' capture-pane -p -t '.escapeshellarg($tmuxName));
        if ($rc === 0 && $capture !== '') {
            $appended = @file_put_contents($logFile, $capture, FILE_APPEND);
            if ($appended === false) {
                Log::warning('TerminalService: failed to seed terminal log file', [
                    'log_file' => $logFile,
                    'user' => self::effectiveUser(),
                ]);
            }
        }

        $session->update(['last_activity_at' => now()]);

        return $session;
    }

    public function write(TerminalSession $session, string $keys, ?User $user = null): void
    {
        if ($user && $session->user_id !== $user->id) {
            throw new \RuntimeException('Unauthorized session');
        }
        if ($session->closed_at) {
            throw new \RuntimeException('Session closed');
        }

        $name = $session->tmux_session ?: 'sei-'.$session->uuid;

        $trailingNewline = str_ends_with($keys, "\n") || str_ends_with($keys, "\r");
        $payload = $trailingNewline ? substr($keys, 0, -1) : $keys;
        if ($payload !== '') {
            $this->run($this->tmuxPrefix().' send-keys -t '.escapeshellarg($name).' -l '.escapeshellarg($payload));
        }
        if ($trailingNewline) {
            $this->run($this->tmuxPrefix().' send-keys -t '.escapeshellarg($name).' Enter');
        }

        $session->update(['last_activity_at' => now()]);
    }

    public function resize(TerminalSession $session, int $cols, int $rows): void
    {
        $name = $session->tmux_session ?: 'sei-'.$session->uuid;
        $this->run($this->tmuxPrefix().' resize-pane -t '.escapeshellarg($name).' -x '.(int) $cols.' -y '.(int) $rows.' 2>/dev/null');
    }

    public function end(TerminalSession $session): void
    {
        $name = $session->tmux_session ?: 'sei-'.$session->uuid;
        $this->run($this->tmuxPrefix().' kill-session -t '.escapeshellarg($name).' 2>/dev/null');
        $session->update(['closed_at' => now()]);
    }

    public static function isAlive(TerminalSession $session): bool
    {
        if ($session->closed_at !== null) {
            return false;
        }

        $name = $session->tmux_session ?: 'sei-'.$session->uuid;
        $conf = storage_path('app/terminal'.DIRECTORY_SEPARATOR.'tmux.conf');
        $socket = (string) config('terminal.socket', 'sei-terminal');
        $vars = implode(' ', array_map(static fn (string $v): string => '-u '.$v, self::SANITIZED_ENV_VARS));
        $cmd = 'env '.$vars.' tmux -f '.escapeshellarg($conf).' -L '.escapeshellarg($socket).' has-session -t '.escapeshellarg($name).' 2>/dev/null';
        $rc = 1;
        exec($cmd, $output, $rc);

        return $rc === 0;
    }

    protected static function effectiveUser(): string
    {
        if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
            $info = posix_getpwuid(posix_geteuid());
            if (is_array($info) && isset($info['name'])) {
                return (string) $info['name'];
            }
        }

        return get_current_user() ?: (string) getmyuid();
    }
}