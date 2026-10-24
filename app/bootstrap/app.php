<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureApiAccount;
use App\Http\Middleware\EnsurePasswordChanged;
use App\Http\Middleware\SecurityHeaders;
use App\Services\Api\ApiErrors;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class, EnsureActiveAccount::class]);
        $middleware->api(append: [SecurityHeaders::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, EnsureApiAccount::class);
        $middleware->prependToPriorityList(SubstituteBindings::class, CheckAbilities::class);
        $middleware->alias(['password.changed' => EnsurePasswordChanged::class,
            'api.account' => EnsureApiAccount::class,
            'abilities' => CheckAbilities::class]);
        $middleware->redirectGuestsTo(fn (Request $request): string => route('login'));
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond([ApiErrors::class, 'render']);
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'token', 'access_token']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson());
    })->create();
