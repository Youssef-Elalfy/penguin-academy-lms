<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiTokenTest extends ApiTestCase
{
    private function credentials(User $user, array $extra = []): array
    {
        return array_replace(['email' => $user->email, 'password' => 'Test-password-2026', 'name' => 'Personal device',
            'abilities' => ['courses:read', 'tokens:read', 'tokens:revoke']], $extra);
    }

    public function test_issues_a_hashed_expiring_token_and_authenticates_real_bearer_requests(): void
    {
        $user = User::factory()->create();
        $this->travelTo(now()->startOfSecond());

        $response = $this->api('POST', '/tokens', $this->credentials($user, ['email' => strtoupper($user->email), 'expires_in_minutes' => 15]))->assertCreated();
        $secret = $response->json('data.access_token');
        $stored = PersonalAccessToken::findToken($secret);
        $this->assertSame(hash('sha256', explode('|', $secret)[1]), $stored->token);
        $this->assertTrue($stored->expires_at->equalTo(now()->addMinutes(15)));
        $this->assertSame(['courses:read', 'tokens:read', 'tokens:revoke'], $stored->abilities);
        $this->api('GET', '/courses', [], $secret)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $listing = $this->api('GET', '/tokens', [], $secret)->assertOk()->json('data.0');
        $this->assertSame(['id', 'name', 'abilities', 'created_at', 'expires_at', 'last_used_at'], array_keys($listing));
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $stored->id, 'tokenable_id' => $user->id]);
    }

    #[TestWith(['wrong-password'])]
    #[TestWith(['inactive'])]
    #[TestWith(['unknown'])]
    public function test_returns_generic_401_for_invalid_credentials(string $case): void
    {
        $user = User::factory()->create();
        $data = $this->credentials($user);
        if ($case === 'inactive') {
            $user->forceFill(['is_active' => false])->save();
        } elseif ($case === 'unknown') {
            $data['email'] = 'unknown@example.test';
        } else {
            $data['password'] = 'incorrect';
        }

        $this->api('POST', '/tokens', $data)->assertUnauthorized()->assertExactJson([
            'message' => 'Valid credentials or a valid bearer token are required.', 'code' => 'unauthenticated',
        ]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_returns_403_for_temporary_password_and_existing_tokens_after_account_changes(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $token = $this->token($user, ['courses:read']);

        $this->api('POST', '/tokens', $this->credentials($user))->assertForbidden();
        $this->api('GET', '/courses', [], $token)->assertForbidden();
        $user->forceFill(['must_change_password' => false, 'is_active' => false])->save();
        $this->api('GET', '/courses', [], $token)->assertUnauthorized();
    }

    #[TestWith(['expires_in_minutes', 0])]
    #[TestWith(['expires_in_minutes', 1441])]
    #[TestWith(['abilities', ['*']])]
    #[TestWith(['abilities', ['courses:write']])]
    #[TestWith(['abilities', ['courses:read', 'courses:read'], 'abilities.0'])]
    #[TestWith(['abilities', []])]
    #[TestWith(['role', 'admin'])]
    #[TestWith(['email', ['invalid']])]
    public function test_returns_422_for_invalid_token_requests(string $field, mixed $value, ?string $error = null): void
    {
        $user = User::factory()->create();

        $this->api('POST', '/tokens', $this->credentials($user, [$field => $value]))
            ->assertUnprocessable()->assertJsonValidationErrors($error ?? $field);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    #[TestWith(['expired'])]
    #[TestWith(['no-expiry'])]
    #[TestWith(['globally-expired'])]
    #[TestWith(['revoked'])]
    #[TestWith(['invalid'])]
    public function test_returns_401_for_unusable_tokens(string $case): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('Expiry test', ['courses:read'], now()->addHour());
        if ($case === 'expired') {
            $token->accessToken->forceFill(['expires_at' => now()->subSecond()])->save();
        } elseif ($case === 'no-expiry') {
            $token->accessToken->forceFill(['expires_at' => null])->save();
        } elseif ($case === 'globally-expired') {
            $token->accessToken->forceFill(['created_at' => now()->subDays(2)])->save();
        } elseif ($case === 'revoked') {
            $token->accessToken->delete();
        }

        $this->api('GET', '/courses', [], $case === 'invalid' ? 'invalid' : $token->plainTextToken)->assertUnauthorized();
    }

    public function test_rejects_session_and_query_authentication_and_requires_abilities(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user, ['tokens:read']);

        $this->actingAs($user, 'web')->getJson('/api/v1/courses')->assertUnauthorized();
        $this->api('GET', '/courses?api_token='.$token)->assertUnauthorized();
        $this->api('GET', '/courses', [], $token)->assertForbidden()->assertJsonPath('code', 'forbidden');
    }

    public function test_revokes_only_owned_tokens_and_current_token_immediately(): void
    {
        $user = User::factory()->create();
        $token = $this->token($user, ['tokens:revoke', 'tokens:read']);
        $other = User::factory()->create()->createToken('Other owner', ['tokens:read'], now()->addHour());
        $second = $user->createToken('Second token', ['courses:read'], now()->addHour());

        $this->api('DELETE', '/tokens/'.$other->accessToken->id, [], $token)->assertNotFound();
        $this->assertFalse(Gate::forUser($user)->allows('delete', $other->accessToken));
        $this->api('DELETE', '/tokens/'.$second->accessToken->id, [], $token)->assertNoContent();
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $second->accessToken->id]);
        $this->api('DELETE', '/tokens/current', [], $token)->assertNoContent();
        $this->api('GET', '/tokens', [], $token)->assertUnauthorized();
        $this->assertModelExists($other->accessToken);
    }

    public function test_limits_active_tokens_and_does_not_count_expired_tokens(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 10; $i++) {
            $user->createToken('Active '.$i, ['courses:read'], now()->addHour());
        }

        $this->api('POST', '/tokens', $this->credentials($user))->assertUnprocessable()->assertJsonValidationErrors('name');
        $user->tokens()->first()->forceFill(['expires_at' => now()->subMinute()])->save();
        $this->api('POST', '/tokens', $this->credentials($user))->assertCreated();
        $this->assertDatabaseCount('personal_access_tokens', 11);
    }

    public function test_token_does_not_preserve_previous_role_permissions(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $token = $this->token($teacher, ['courses:write']);
        $teacher->forceFill(['role' => UserRole::Student])->save();

        $this->api('PATCH', '/courses/'.$course->id, ['title' => 'Unauthorized'], $token)->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => $course->title]);
    }

    public function test_token_issuance_rejects_url_parameters(): void
    {
        $user = User::factory()->create();

        $this->api('POST', '/tokens?name=device', $this->credentials($user))->assertUnprocessable()->assertJsonValidationErrors('request');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_issuance_throttles_repeated_failures(): void
    {
        $user = User::factory()->create();
        $payload = $this->credentials($user, ['password' => 'wrong']);
        for ($i = 0; $i < 5; $i++) {
            $this->api('POST', '/tokens', $payload)->assertUnauthorized();
        }

        $this->api('POST', '/tokens', $payload)->assertTooManyRequests()->assertHeader('Retry-After')->assertJsonPath('code', 'rate_limited');
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_authenticated_api_throttle_is_shared_between_tokens_for_the_same_user(): void
    {
        $user = User::factory()->create();
        $one = $this->token($user, ['courses:read']);
        $two = $this->token($user, ['courses:read']);
        for ($i = 0; $i < 120; $i++) {
            $this->api('GET', '/courses', [], $i % 2 === 0 ? $one : $two)->assertOk();
        }

        $this->api('GET', '/courses', [], $two)->assertTooManyRequests()->assertJsonPath('code', 'rate_limited');
    }
}
