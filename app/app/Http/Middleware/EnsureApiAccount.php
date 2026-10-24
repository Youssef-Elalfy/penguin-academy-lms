<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_unless($user?->is_active && $token instanceof PersonalAccessToken && $token->expires_at !== null, 401);
        abort_if($user->must_change_password, 403);

        return $next($request);
    }
}
