<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use DatabaseTransactions;

    #[TestWith(['/login', 200])]
    #[TestWith(['/missing-security-audit-page', 404])]
    #[TestWith(['/api/v1/courses', 401])]
    public function test_security_headers_cover_success_and_exception_responses(string $path, int $status): void
    {
        $response = $this->get($path)->assertStatus($status);
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $policy = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($policy);
        foreach (["default-src 'self'", "script-src 'self'", "object-src 'none'", "frame-ancestors 'none'", "form-action 'self'"] as $directive) {
            $this->assertStringContainsString($directive, $policy);
        }
    }

    public function test_production_https_responses_enable_hsts(): void
    {
        $this->app->instance('env', 'production');
        $this->get('https://localhost/login')->assertOk()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_local_http_does_not_set_hsts(): void
    {
        $this->get('/login')->assertOk()->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_exact_72_byte_password_can_login(): void
    {
        $password = str_repeat('A', 69).'1!z';
        $user = User::factory()->create(['password' => $password]);
        $this->post('/login', ['email' => $user->email, 'password' => $password])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_rejects_a_suffix_after_the_bcrypt_limit(): void
    {
        $password = str_repeat('A', 69).'1!z';
        $user = User::factory()->create(['password' => $password]);
        $this->postJson('/login', ['email' => $user->email, 'password' => $password.'not-part-of-password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
    }

    public function test_login_rejects_multibyte_password_above_72_bytes(): void
    {
        $this->postJson('/login', ['email' => 'absent@example.test', 'password' => str_repeat('é', 37)])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertGuest();
    }

    public function test_password_change_rejects_truncated_current_password(): void
    {
        $password = str_repeat('A', 69).'1!z';
        $user = User::factory()->create(['password' => $password]);
        $this->actingAs($user)->putJson('/account/password', ['current_password' => $password.'suffix',
            'password' => 'Replacement-2026!', 'password_confirmation' => 'Replacement-2026!'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertTrue(Hash::check($password, $user->fresh()->password));
    }

    public function test_teacher_provisioning_rejects_truncated_administrator_password(): void
    {
        $password = str_repeat('A', 69).'1!z';
        $admin = User::factory()->admin()->create(['password' => $password]);
        $this->actingAs($admin)->postJson('/admin/teachers', ['name' => 'QA Teacher', 'email' => 'audit-teacher@example.test',
            'current_password' => $password.'suffix', 'password' => 'Temporary-2026!', 'password_confirmation' => 'Temporary-2026!'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertDatabaseMissing('users', ['email' => 'audit-teacher@example.test']);
    }

    public function test_fortify_password_confirmation_rejects_truncated_password(): void
    {
        $password = str_repeat('A', 69).'1!z';
        $this->actingAs(User::factory()->create(['password' => $password]))
            ->postJson('/user/confirm-password', ['password' => $password.'suffix'])->assertUnprocessable();
        $this->assertNull(session('auth.password_confirmed_at'));
    }

    public function test_fortify_confirmation_accepts_the_exact_password(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/user/confirm-password', ['password' => 'Test-password-2026'])->assertCreated();
        $this->assertNotNull(session('auth.password_confirmed_at'));
    }

    public function test_absent_account_authentication_applies_a_minimum_timebox(): void
    {
        Sleep::fake();
        try {
            $this->postJson('/login', ['email' => 'absent@example.test', 'password' => 'Invalid-2026!'])
                ->assertUnprocessable()->assertJsonValidationErrors('email');
            Sleep::assertSleptTimes(1);
        } finally {
            Sleep::fake(false);
        }
    }
}
