<?php

return [
    'stateful' => [],
    'guard' => ['web'],
    'expiration' => (int) env('SANCTUM_EXPIRATION', 720),
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
