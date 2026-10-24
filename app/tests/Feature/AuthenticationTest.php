<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private function registration(array $overrides = []): array
    {
        return array_replace(['name' => 'New Student', 'email' => 'new-student@example.test',
            'password' => 'Learning-2026!', 'password_confirmation' => 'Learning-2026!'], $overrides);
    }

    public function test_authentication_forms_render_with_csrf_fields(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="_token"', false);
        $this->get('/register')->assertOk()->assertSeeText('Start your journey.');
    }

    public function test_registration_persists_a_student_with_a_hashed_password(): void
    {
        $this->post('/register', $this->registration(['email' => 'NEW-STUDENT@EXAMPLE.TEST']))->assertRedirect('/dashboard');
        $user = User::where('email', 'new-student@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(Hash::check('Learning-2026!', $user->password));
        $this->assertArrayNotHasKey('password', $user->toArray());
        $this->assertArrayNotHasKey('remember_token', $user->toArray());
    }

    public static function privilegedFields(): array
    {
        return [['role', 'admin'], ['role', 'teacher'], ['is_active', false],
            ['must_change_password', true], ['email_verified_at', '2026-01-01'], ['id', 1]];
    }

    #[DataProvider('privilegedFields')]
    public function test_registration_rejects_privilege_fields(string $field, mixed $value): void
    {
        $this->postJson('/register', $this->registration([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new-student@example.test']);
    }

    public function test_registration_rejects_weak_mismatched_or_multibyte_overlong_passwords(): void
    {
        foreach (['short', 'LongNoNumbersHere', str_repeat('é', 36).'A1'] as $password) {
            $this->postJson('/register', $this->registration(['password' => $password, 'password_confirmation' => $password]))
                ->assertUnprocessable()->assertJsonValidationErrors('password');
        }
        $this->postJson('/register', $this->registration(['password_confirmation' => 'Different-2026!']))
            ->assertUnprocessable()->assertJsonValidationErrors('password');
    }

    public function test_registration_rejects_duplicate_email_case_insensitively(): void
    {
        User::factory()->create(['email' => 'new-student@example.test']);
        $this->postJson('/register', $this->registration(['email' => 'NEW-STUDENT@EXAMPLE.TEST']))
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(1, User::where('email', 'new-student@example.test')->count());
    }

    public static function roles(): array
    {
        return [[UserRole::Admin], [UserRole::Teacher], [UserRole::Student]];
    }

    #[DataProvider('roles')]
    public function test_each_role_can_login_and_is_routed_to_its_dashboard(UserRole $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->post('/login', ['email' => strtoupper($user->email), 'password' => 'Test-password-2026'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertRedirect('/'.$role->value.'/dashboard');
        $this->get('/'.$role->value.'/dashboard')->assertOk();
    }

    public function test_invalid_password_and_inactive_account_cannot_login(): void
    {
        $user = User::factory()->create();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('email');
        $user->is_active = false;
        $user->save();
        $this->postJson('/login', ['email' => $user->email, 'password' => 'Test-password-2026'])->assertUnprocessable();
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/login', ['email' => 'unknown@example.test', 'password' => 'incorrect'])->assertUnprocessable();
        }
        $this->postJson('/login', ['email' => 'UNKNOWN@EXAMPLE.TEST', 'password' => 'incorrect'])->assertStatus(429)->assertHeader('Retry-After');
    }

    public function test_registration_is_rate_limited(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->postJson('/register', [])->assertUnprocessable();
        }
        $this->postJson('/register', [])->assertStatus(429);
    }

    public function test_malformed_login_input_returns_validation_instead_of_a_server_error(): void
    {
        $this->postJson('/login', ['email' => ['admin'], 'password' => 'irrelevant'])->assertUnprocessable();
    }

    public function test_logout_ends_authentication_and_blocks_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/student/dashboard')->assertRedirect('/login');
        $this->get('/logout')->assertStatus(405);
    }
}
