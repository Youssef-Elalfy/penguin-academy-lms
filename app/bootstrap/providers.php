<?php

use App\Providers\AppServiceProvider;
use Laravel\Fortify\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    App\Providers\FortifyServiceProvider::class,
];
