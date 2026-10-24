<?php

return [
    // This API authenticates bearer tokens only; Blade continues to use the web guard.
    'stateful' => [],
    'guard' => [],
    'expiration' => 1440,
    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),
];
