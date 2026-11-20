<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings as BaseTrimStrings;

class TrimStrings extends BaseTrimStrings
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * Terminal input carries control characters (\r, \t, escape sequences)
     * that must reach tmux verbatim; trimming would turn "\r" into "".
     *
     * @var array<int, string>
     */
    protected $except = [
        'input',
    ];
}
