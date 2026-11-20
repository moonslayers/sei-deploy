<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeployScriptVersion extends Model
{
    protected $fillable = ['backend_id', 'content', 'user_id'];

    public function backend(): BelongsTo
    {
        return $this->belongsTo(Backend::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
