<?php

return [
    'emails' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_EMAILS', '')))),
];
