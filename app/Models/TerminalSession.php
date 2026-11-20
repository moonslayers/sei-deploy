<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TerminalSession extends Model
{
    protected $fillable = ['uuid', 'backend_id', 'user_id', 'last_activity_at', 'closed_at', 'output_offset', 'tmux_session'];

    protected function casts(): array
    {
        return [
            'last_activity_at' => 'datetime',
            'closed_at' => 'datetime',
            'output_offset' => 'integer',
        ];
    }

    public function backend(): BelongsTo
    {
        return $this->belongsTo(Backend::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
