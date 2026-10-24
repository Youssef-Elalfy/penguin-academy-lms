<?php

namespace App\Actions\Api;

use App\Models\User;
use App\Services\Api\TokenAbilities;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;

class TokenActions
{
    public function __construct(private TokenAbilities $abilities) {}

    public function issue(#[\SensitiveParameter] array $data): NewAccessToken
    {
        return (new Timebox)->call(fn (): NewAccessToken => DB::transaction(function () use ($data): NewAccessToken {
            $user = User::where('email', $data['email'])->lockForUpdate()->first();
            $validPassword = $user !== null && Hash::check($data['password'], $user->password);
            abort_unless($validPassword && $user->is_active, 401);
            Gate::forUser($user)->authorize('create', PersonalAccessToken::class);

            if (array_diff($data['abilities'], $this->abilities->forUser($user)) !== []) {
                throw ValidationException::withMessages(['abilities' => 'Request only the abilities permitted for your current role. Wildcards are not allowed.']);
            }
            if ($user->tokens()->where('expires_at', '>', now())->where('created_at', '>', now()->subDay())->count() >= 10) {
                throw ValidationException::withMessages(['name' => 'Revoke an existing token before creating another. The limit is 10 active tokens.']);
            }
            if (Hash::needsRehash($user->password)) {
                $user->forceFill(['password' => Hash::make($data['password'])])->save();
            }

            return $user->createToken($data['name'], $data['abilities'], now()->addMinutes($data['expires_in_minutes'] ?? 60));
        }, 3), 200000);
    }

    public function revoke(User $user, PersonalAccessToken $token): void
    {
        Gate::forUser($user)->authorize('delete', $token);
        $user->tokens()->whereKey($token->id)->delete();
    }
}
