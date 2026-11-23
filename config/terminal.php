<?php

return [
    'max_sessions' => (int) env('TERMINAL_MAX_SESSIONS', 3),
    'socket' => env('TERMINAL_TMUX_SOCKET', 'sei-terminal'),
    'default_shell' => env('TERMINAL_DEFAULT_SHELL', '/bin/bash'),
    'conf_filename' => 'tmux.conf',
];