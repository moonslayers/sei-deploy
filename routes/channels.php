<?php

use App\Models\TerminalSession;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (string) $user->id === (string) $id;
});

Broadcast::channel('terminal.{sessionId}', function ($user, $sessionId) {
    if (! $user) {
        return false;
    }

    $session = TerminalSession::where('uuid', $sessionId)->first();

    return $session && (int) $session->user_id === (int) $user->id;
});
