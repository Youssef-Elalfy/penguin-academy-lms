<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdministratorProvisioningTest extends TestCase
{
    use DatabaseTransactions;

    public function test_secure_command_provisions_an_admin_with_a_hashed_password(): void
    {
        $this->artisan('academy:admin', ['--name' => 'Academy Owner', '--email' => 'OWNER@EXAMPLE.TEST'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'Private-admin-2026!')
            ->expectsQuestion('Confirm password', 'Private-admin-2026!')
            ->expectsOutput('Administrator created. No credentials were printed.')
            ->assertSuccessful();
        $admin = User::where('email', 'owner@example.test')->firstOrFail();
        $this->assertSame(UserRole::Admin, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertTrue(Hash::check('Private-admin-2026!', $admin->password));
    }

    public function test_command_never_promotes_or_overwrites_an_existing_account(): void
    {
        $existing = User::factory()->create(['email' => 'owner@example.test']);
        $original = $existing->password;
        $this->artisan('academy:admin', ['--name' => 'Academy Owner', '--email' => 'owner@example.test'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'Private-admin-2026!')
            ->expectsQuestion('Confirm password', 'Private-admin-2026!')
            ->assertFailed();
        $this->assertSame(UserRole::Student, $existing->fresh()->role);
        $this->assertSame($original, $existing->fresh()->password);
    }

    public function test_command_rejects_weak_passwords(): void
    {
        $this->artisan('academy:admin', ['--name' => 'Academy Owner', '--email' => 'owner@example.test'])
            ->expectsQuestion('Password (12+ characters, mixed case and numbers)', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'owner@example.test']);
    }

    public function test_non_interactive_command_requires_explicit_secure_input(): void
    {
        $this->artisan('academy:admin', ['--no-interaction' => true])->assertFailed();
    }
}
