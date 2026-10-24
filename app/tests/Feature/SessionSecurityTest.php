<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionSecurityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_login_rotates_session_id_and_logout_invalidates_session_and_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->withSession(['marker' => 'private'])->get('/login')->assertOk();
        $beforeLogin = session()->getId();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-2026'])->assertRedirect('/dashboard');
        $this->assertNotSame($beforeLogin, session()->getId());
        $beforeLogout = session()->getId();
        $token = session()->token();
        $this->post('/logout')->assertRedirect('/');
        $this->assertNotSame($beforeLogout, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertNull(session('marker'));
        $this->assertGuest();
    }

    public function test_csrf_protection_rejects_missing_token_with_test_bypass_disabled(): void
    {
        $this->app->instance('env', 'local');
        $this->post('/login', ['email' => 'someone@example.test', 'password' => 'irrelevant'])->assertStatus(419);
        $this->post('/register', [])->assertStatus(419);
    }

    public function test_browser_response_has_security_headers_and_http_only_session_cookie(): void
    {
        $response = $this->get('/login')->assertOk()->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $cookie = collect($response->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_session_driver_persists_encrypted_session_data_in_mysql(): void
    {
        config(['session.driver' => 'database', 'session.encrypt' => true]);
        app('session')->forgetDrivers();
        $this->withSession(['private_marker' => 'encrypted-session-probe'])->get('/login')->assertOk();
        $payload = DB::table('sessions')->where('id', session()->getId())->value('payload');
        $this->assertNotNull($payload);
        $raw = base64_decode($payload);
        $this->assertStringNotContainsString('encrypted-session-probe', $raw);
        $this->assertStringContainsString('encrypted-session-probe', Crypt::decryptString($raw));
    }

    public function test_remember_me_sets_the_guard_recaller_cookie(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'Test-password-2026', 'remember' => '1'])
            ->assertRedirect('/dashboard')->assertCookie(Auth::guard('web')->getRecallerName());
    }

    public function test_disabled_account_is_logged_out_of_existing_sessions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $user->is_active = false;
        $user->save();
        $this->get('/student/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_temporary_teacher_password_must_change_before_dashboard_access(): void
    {
        $user = User::factory()->teacher()->create(['must_change_password' => true]);
        $this->actingAs($user)->get('/teacher/dashboard')->assertRedirect('/account/password');
        $this->get('/account/password')->assertOk();
        $this->put('/account/password', ['current_password' => 'Test-password-2026', 'password' => 'Personal-password-2026', 'password_confirmation' => 'Personal-password-2026'])
            ->assertRedirect('/dashboard');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Personal-password-2026', $user->fresh()->password));
        $this->get('/teacher/dashboard')->assertOk();
    }

    public function test_password_change_requires_current_password_and_cannot_target_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $original = $other->password;
        $this->actingAs($user)->putJson('/account/password', ['current_password' => 'incorrect', 'password' => 'Personal-password-2026', 'password_confirmation' => 'Personal-password-2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/account/password', ['current_password' => 'Test-password-2026', 'password' => 'Personal-password-2026', 'password_confirmation' => 'Personal-password-2026', 'user_id' => $other->id])
            ->assertUnprocessable()->assertJsonValidationErrors('user_id');
        $this->assertSame($original, $other->fresh()->password);
    }

    public function test_password_change_revokes_other_sessions_tokens_and_old_remember_cookie(): void
    {
        $user = User::factory()->create();
        $oldRemember = $user->remember_token;
        DB::table('sessions')->insert(['id' => 'another-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
        $user->createToken('test-only', ['*'], now()->addHour());
        $this->actingAs($user)->put('/account/password', ['current_password' => 'Test-password-2026', 'password' => 'Personal-password-2026', 'password_confirmation' => 'Personal-password-2026'])
            ->assertRedirect('/dashboard');
        $this->assertDatabaseMissing('sessions', ['id' => 'another-session']);
        $this->assertSame(0, $user->tokens()->count());
        $this->assertNotSame($oldRemember, $user->fresh()->remember_token);
        $this->assertAuthenticatedAs($user);
    }
}
