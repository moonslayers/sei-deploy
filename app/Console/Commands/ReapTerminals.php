<?php

namespace App\Console\Commands;

use App\Models\TerminalSession;
use App\Services\TerminalService;
use Illuminate\Console\Command;

class ReapTerminals extends Command
{
    protected $signature = 'terminal:reap';

    protected $description = 'Reap idle and orphaned tmux terminal sessions.';

    public function handle(TerminalService $service): int
    {
        $ttl = now()->subMinutes(30);

        $idle = TerminalSession::whereNull('closed_at')->where('last_activity_at', '<', $ttl)->get();
        foreach ($idle as $session) {
            $service->end($session);
            $this->info('Reaped idle session '.$session->uuid);
        }

        // Orphan tmux sessions: compare only against OPEN sessions so that a
        // failed kill-session (closed row but live tmux) is still detected.
        [$rc, $out] = $service->run('tmux list-sessions -F \'#{session_name}\' 2>/dev/null');
        if ($rc === 0) {
            $names = array_filter(explode("\n", trim($out)));
            $known = TerminalSession::whereNull('closed_at')->pluck('tmux_session')->all();
            foreach ($names as $name) {
                if (str_starts_with($name, 'sei-') && ! in_array($name, $known, true)) {
                    [$krc] = $service->run('tmux kill-session -t '.escapeshellarg($name).' 2>/dev/null');
                    // If kill failed, still mark any matching row closed to avoid zombies.
                    TerminalSession::where('tmux_session', $name)->whereNull('closed_at')
                        ->update(['closed_at' => now()]);
                    $this->info('Reaped orphan tmux session '.$name);
                }
            }
        }

        return self::SUCCESS;
    }
}
