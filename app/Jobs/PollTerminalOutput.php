<?php

namespace App\Jobs;

use App\Events\TerminalOutputReceived;
use App\Models\TerminalSession;
use App\Services\TerminalService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class PollTerminalOutput implements ShouldQueue
{
    use Queueable;

    // Poller runtime cap: 5 minutes. tmux session is independent of this job,
    // so the session survives; re-dispatch happens from TerminalService::input()
    // (via the cache lock) when the user sends input.
    public const MAX_RUNTIME_SECONDS = 300;

    // Exit the tail loop after this many seconds without new output.
    public const IDLE_TIMEOUT_SECONDS = 10;

    // Lock TTL must exceed MAX_RUNTIME_SECONDS so it cannot expire mid-run.
    public const LOCK_TTL_SECONDS = 360;

    // Throttle last_activity_at writes to at most once per window.
    public const ACTIVITY_THROTTLE_SECONDS = 30;

    public function __construct(public int $sessionId) {}

    public function handle(TerminalService $service): void
    {
        $redispatch = false;
        try {
            $deadline = time() + self::MAX_RUNTIME_SECONDS;
            $lastOutputAt = time();
            while (time() < $deadline) {
                $session = TerminalSession::find($this->sessionId);
                if (! $session) {
                    break;
                }

                $name = $session->tmux_session ?: 'sei-'.$session->uuid;
                [$rc] = $service->run('tmux has-session -t '.escapeshellarg($name).' 2>/dev/null');
                if ($rc !== 0 || $session->closed_at) {
                    if (! $session->closed_at) {
                        $session->update(['closed_at' => now()]);
                    }
                    break;
                }

                $logFile = storage_path('app/terminal'.DIRECTORY_SEPARATOR.$session->uuid.'.log');
                $offset = (int) $session->output_offset;
                $hadOutput = false;
                if (is_file($logFile)) {
                    $size = (int) filesize($logFile);
                    if ($size > $offset) {
                        $fh = fopen($logFile, 'r');
                        if ($fh) {
                            fseek($fh, $offset);
                            $chunk = stream_get_contents($fh);
                            fclose($fh);
                            if (is_string($chunk) && $chunk !== '') {
                                event(new TerminalOutputReceived($session, $chunk));
                                $session->update(['output_offset' => $offset + strlen($chunk)]);
                                $hadOutput = true;
                            }
                        }
                    }
                }
                if ($hadOutput) {
                    $lastOutputAt = time();
                }

                // Throttle last_activity_at writes (real user activity is recorded in input()).
                $last = $session->last_activity_at;
                if (! $last || $last->diffInSeconds(now()) >= self::ACTIVITY_THROTTLE_SECONDS) {
                    $session->update(['last_activity_at' => now()]);
                }

                if (time() - $lastOutputAt >= self::IDLE_TIMEOUT_SECONDS) {
                    $redispatch = true;
                    break;
                }

                usleep(125000);
            }
            // If loop ended by deadline but session still open, keep flow alive.
            $session = TerminalSession::find($this->sessionId);
            if ($session && ! $session->closed_at) {
                $redispatch = true;
            }
        } finally {
            Cache::forget('terminal-poller-'.$this->sessionId);
            if ($redispatch) {
                $session = TerminalSession::find($this->sessionId);
                if ($session && ! $session->closed_at) {
                    PollTerminalOutput::dispatch($this->sessionId);
                }
            }
        }
    }
}
