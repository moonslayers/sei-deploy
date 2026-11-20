<?php

namespace App\Events;

use App\Models\TerminalSession;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TerminalOutputReceived implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public int $offset = 0;

    public function __construct(public TerminalSession $session, public string $output, public string $stream = 'stdout')
    {
        $this->offset = (int) $session->output_offset;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('terminal.'.$this->session->uuid)];
    }

    public function broadcastAs(): string
    {
        return 'terminal.output';
    }
}
