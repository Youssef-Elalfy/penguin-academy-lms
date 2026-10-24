<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function form(array $changes = []): array
    {
        return array_replace(['name' => 'New Teacher', 'email' => 'teacher@example.test',
            'password' => 'Temporary-2026!', 'password_confirmation' => 'Temporary-2026!',
            'current_password' => 'Test-password-2026'], $changes);
    }

    public function test_admin_can_create_edit_deactivate_and_reactivate_a_teacher(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->post('/admin/teachers', $this->form())->assertRedirect();
        $teacher = User::where('email', 'teacher@example.test')->firstOrFail();
        $this->assertSame(UserRole::Teacher, $teacher->role);
        $this->assertTrue($teacher->must_change_password);
        $this->assertTrue(Hash::check('Temporary-2026!', $teacher->password));
        $this->get('/admin/teachers')->assertOk()->assertSeeText('New Teacher');
        $this->get('/admin/teachers/'.$teacher->id.'/edit')->assertOk();
        $this->put('/admin/teachers/'.$teacher->id, $this->form(['name' => 'Updated Teacher', 'is_active' => '0', 'password' => null, 'password_confirmation' => null]))->assertRedirect();
        $this->assertSame('Updated Teacher', $teacher->fresh()->name);
        $this->assertFalse($teacher->fresh()->is_active);
        $this->put('/admin/teachers/'.$teacher->id, $this->form(['is_active' => '1', 'password' => null, 'password_confirmation' => null]))->assertRedirect();
        $this->assertTrue($teacher->fresh()->is_active);
    }

    public static function unprivilegedRoles(): array
    {
        return [[UserRole::Teacher], [UserRole::Student]];
    }

    #[DataProvider('unprivilegedRoles')]
    public function test_non_admins_cannot_manage_teachers(UserRole $role): void
    {
        $target = User::factory()->teacher()->create();
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get('/admin/teachers')->assertForbidden();
        $this->get('/admin/teachers/create')->assertForbidden();
        $this->postJson('/admin/teachers', $this->form())->assertForbidden();
        $this->get('/admin/teachers/'.$target->id.'/edit')->assertForbidden();
        $this->putJson('/admin/teachers/'.$target->id, $this->form(['is_active' => false]))->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'teacher@example.test']);
        $this->assertTrue($target->fresh()->is_active);
    }

    public function test_teacher_routes_cannot_be_used_to_edit_admins_or_students(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->actingAs($admin);
        foreach ([$admin, $student] as $target) {
            $this->get('/admin/teachers/'.$target->id.'/edit')->assertForbidden();
            $this->putJson('/admin/teachers/'.$target->id, $this->form(['is_active' => false]))->assertForbidden();
            $this->assertTrue($target->fresh()->is_active);
        }
    }

    public function test_administrator_password_confirmation_is_required_for_writes(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson('/admin/teachers', $this->form(['current_password' => 'incorrect']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->assertDatabaseMissing('users', ['email' => 'teacher@example.test']);
    }

    public function test_teacher_creation_and_update_reject_role_escalation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson('/admin/teachers', $this->form(['role' => 'admin']))->assertUnprocessable()->assertJsonValidationErrors('role');
        $teacher = User::factory()->teacher()->create();
        $this->putJson('/admin/teachers/'.$teacher->id, $this->form(['role' => 'admin', 'is_active' => true]))->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->assertSame(UserRole::Teacher, $teacher->fresh()->role);
    }

    public function test_duplicate_teacher_email_is_rejected_without_overwriting_a_user(): void
    {
        $existing = User::factory()->create(['email' => 'teacher@example.test']);
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson('/admin/teachers', $this->form())->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->assertSame(UserRole::Student, $existing->fresh()->role);
        $this->assertSame(1, User::where('email', 'teacher@example.test')->count());
    }

    public function test_deactivation_revokes_sessions_tokens_and_remember_token(): void
    {
        $teacher = User::factory()->teacher()->create();
        $remember = $teacher->remember_token;
        DB::table('sessions')->insert(['id' => 'teacher-session', 'user_id' => $teacher->id, 'payload' => '', 'last_activity' => time()]);
        $teacher->createToken('test-only', ['*'], now()->addHour());
        $this->actingAs(User::factory()->admin()->create());
        $this->put('/admin/teachers/'.$teacher->id, $this->form(['is_active' => false, 'password' => null, 'password_confirmation' => null]))->assertRedirect();
        $this->assertDatabaseMissing('sessions', ['id' => 'teacher-session']);
        $this->assertSame(0, $teacher->tokens()->count());
        $this->assertNotSame($remember, $teacher->fresh()->remember_token);
        $this->assertFalse($teacher->fresh()->is_active);
    }

    public function test_admin_password_reset_requires_teacher_to_change_it_again(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->put('/admin/teachers/'.$teacher->id, $this->form(['is_active' => true]))->assertRedirect();
        $this->assertTrue($teacher->fresh()->must_change_password);
        $this->assertTrue(Hash::check('Temporary-2026!', $teacher->fresh()->password));
    }

    public function test_teacher_names_are_escaped_and_search_is_scoped_to_teachers(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => '<script>alert(1)</script>']);
        $student = User::factory()->create(['name' => 'Private student name']);
        $this->actingAs(User::factory()->admin()->create());
        $this->get('/admin/teachers')->assertOk()->assertSee(e($teacher->name), false)->assertDontSee($teacher->name, false)->assertDontSeeText($student->name);
        $this->get('/admin/teachers?q='.urlencode($teacher->email))->assertOk()->assertSeeText($teacher->email);
    }
}
