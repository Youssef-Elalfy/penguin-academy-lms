<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Support\Timebox;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\Http\Requests\LoginRequest as FortifyLoginRequest;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FortifyLoginRequest::class, LoginRequest::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));

        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password): bool => is_string($password)
            && strlen($password) <= 72 && $user->is_active && Hash::check($password, $user->password));

        Fortify::authenticateUsing(fn (Request $request): ?User => (new Timebox)->call(function () use ($request): ?User {
            $user = User::where('email', Str::lower(trim((string) $request->input('email'))))->first();
            if ($user && Hash::check((string) $request->input('password'), $user->password) && $user->is_active) {
                if (Hash::needsRehash($user->password)) {
                    $user->password = (string) $request->input('password');
                    $user->save();
                }

                return $user;
            }

            return null;
        }, 200000));

        RateLimiter::for('login', fn (Request $request): array => [
            Limit::perMinute(5)->by('identity:'.(is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : 'invalid').'|'.$request->ip()),
            Limit::perMinute(30)->by('ip:'.$request->ip()),
        ]);
        RateLimiter::for('fortify', function (Request $request): Limit {
            if ($request->isMethod('POST') && $request->is('register')) {
                return Limit::perMinute(6)->by('register:'.$request->ip());
            }
            if ($request->isMethod('POST') && $request->is('user/confirm-password')) {
                return Limit::perMinute(5)->by('confirm:'.$request->user()?->id.'|'.$request->ip());
            }

            return Limit::none();
        });
        RateLimiter::for('account-write', fn (Request $request): Limit => Limit::perMinute(10)->by((string) $request->user()?->id));
    }
}
