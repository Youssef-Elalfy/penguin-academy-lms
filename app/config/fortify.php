<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',
    'passwords' => 'users',
    'username' => 'email',
    'email' => 'email',
    'lowercase_usernames' => true,
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'middleware' => ['web', 'throttle:fortify'],
    'auth_middleware' => 'auth',
    'views' => true,
    'limiters' => ['login' => 'login'],
    'features' => [Features::registration()],
];
