<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guests_cannot_access_any_role_workspace_or_teacher_management(): void
    {
        foreach (['/dashboard', '/admin/dashboard', '/teacher/dashboard', '/student/dashboard', '/admin/teachers', '/admin/teachers/create', '/account/password'] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->getJson('/admin/dashboard')->assertUnauthorized();
    }

    public static function roles(): array
    {
        return [[UserRole::Admin], [UserRole::Teacher], [UserRole::Student]];
    }

    #[DataProvider('roles')]
    public function test_roles_cannot_open_each_others_dashboards(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        foreach (UserRole::cases() as $other) {
            if ($other !== $role) {
                $this->get('/'.$other->value.'/dashboard')->assertForbidden();
            }
        }
    }

    public function test_course_policy_limits_teacher_ownership_and_student_visibility(): void
    {
        $owner = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create(['teacher_id' => $owner->id]);
        $this->assertTrue(Gate::forUser($owner)->allows('update', $course));
        $this->assertFalse(Gate::forUser($other)->allows('update', $course));
        $this->assertFalse(Gate::forUser($owner)->allows('assignTeacher', $course));
        $this->assertTrue(Gate::forUser($admin)->allows('assignTeacher', $course));
        $this->assertFalse(Gate::forUser($student)->allows('view', $course));
        $course->published_at = now()->addDay();
        $this->assertFalse(Gate::forUser($student)->allows('view', $course));
        $course->published_at = now()->subMinute();
        $course->save();
        $this->assertTrue(Gate::forUser($student)->allows('view', $course));
        $this->assertFalse(Gate::forUser($student)->allows('update', $course));
        $course->delete();
        $this->assertFalse(Gate::forUser($owner)->allows('update', $course));
        $this->assertFalse(Gate::forUser($admin)->allows('view', $course));
    }

    public function test_student_lesson_access_requires_active_enrollment_and_publication(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id]);
        $this->assertFalse(Gate::forUser($student)->allows('view', $lesson));
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'student_id' => $student->id]);
        $this->assertTrue(Gate::forUser($student)->allows('view', $lesson));
        $lesson->is_published = false;
        $this->assertFalse(Gate::forUser($student)->allows('view', $lesson));
        $lesson->is_published = true;
        $enrollment->update(['withdrawn_at' => now()]);
        $this->assertFalse(Gate::forUser($student)->allows('view', $lesson));
    }

    public function test_completion_policy_denies_other_students_and_cross_course_data(): void
    {
        $student = User::factory()->create();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $foreignLesson = Lesson::factory()->published()->create();
        $other = User::factory()->create();
        $this->assertTrue(Gate::forUser($student)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
        $this->assertFalse(Gate::forUser($other)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
        $this->assertFalse(Gate::forUser($student)->allows('create', [LessonCompletion::class, $enrollment, $foreignLesson]));
        $this->assertFalse(Gate::forUser($other)->allows('view', $enrollment));
        $student->is_active = false;
        $this->assertFalse(Gate::forUser($student)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
    }

    public function test_other_teachers_cannot_read_private_lessons_or_enrollments(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        $other = User::factory()->teacher()->create();
        $this->assertFalse(Gate::forUser($other)->allows('view', $lesson));
        $this->assertFalse(Gate::forUser($other)->allows('view', $enrollment));
        $this->assertTrue(Gate::forUser($course->teacher)->allows('view', $lesson));
    }

    public function test_sensitive_user_fields_are_guarded_against_mass_assignment(): void
    {
        $user = User::factory()->create();
        $user->fill(['role' => 'admin', 'is_active' => false, 'must_change_password' => true])->save();
        $this->assertSame(UserRole::Student, $user->fresh()->role);
        $this->assertTrue($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
