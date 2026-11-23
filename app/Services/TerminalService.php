<?php

namespace App\Services;

use App\Models\Backend;
use App\Models\TerminalSession;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * tmux-backed terminal sessions.
 *
 * Prerequisite on the server: the `tmux` binary must be installed and on PATH.
 * Shell escaping: every tmux argument derived from user input or paths is passed
 * through escapeshellarg().
 */
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
            @mkdir($logDir, 0775, true);
        }
        $logFile = $logDir.DIRECTORY_SEPARATOR.$uuid.'.log';
        @file_put_contents($logFile, '');

        [$rc] = $this->run('tmux new-session -d -s '.escapeshellarg($tmuxName).' -c '.escapeshellarg($resolved));
        if ($rc !== 0) {
            $session->update(['closed_at' => now()]);

            throw new \RuntimeException('Failed to create tmux session.');
        }

        $this->run('tmux pipe-pane -t '.escapeshellarg($tmuxName).' -o '.escapeshellarg('cat >> '.$logFile));

        // Seed logfile with current pane content so terminal doesn't start empty.
        [$rc, $capture] = $this->run('tmux capture-pane -p -t '.escapeshellarg($tmuxName));
        if ($rc === 0 && $capture !== '') {
            @file_put_contents($logFile, $capture, FILE_APPEND);
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
            $this->run('tmux send-keys -t '.escapeshellarg($name).' -l '.escapeshellarg($payload));
        }
        if ($trailingNewline) {
            $this->run('tmux send-keys -t '.escapeshellarg($name).' Enter');
        }

        $session->update(['last_activity_at' => now()]);

    }

    public function resize(TerminalSession $session, int $cols, int $rows): void
    {
        // Best-effort: tmux resize is limited for detached sessions; documented limitation.
        $name = $session->tmux_session ?: 'sei-'.$session->uuid;
        $this->run('tmux resize-pane -t '.escapeshellarg($name).' -x '.(int) $cols.' -y '.(int) $rows.' 2>/dev/null');
    }

    public function end(TerminalSession $session): void
    {
        $name = $session->tmux_session ?: 'sei-'.$session->uuid;
        $this->run('tmux kill-session -t '.escapeshellarg($name).' 2>/dev/null');
        $session->update(['closed_at' => now()]);
    }

    public static function isAlive(TerminalSession $session): bool
    {
        return $session->closed_at === null;
    }
}
