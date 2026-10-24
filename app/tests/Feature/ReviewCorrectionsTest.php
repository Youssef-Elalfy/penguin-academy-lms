<?php

namespace Tests\Feature;

use App\Actions\Academy\Participants;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReviewCorrectionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_active_teachers_can_create_courses_but_students_and_inactive_teachers_cannot(): void
    {
        $this->assertTrue(Gate::forUser(User::factory()->teacher()->create())->allows('create', Course::class));
        $this->assertFalse(Gate::forUser(User::factory()->create())->allows('create', Course::class));
        $this->assertFalse(Gate::forUser(User::factory()->teacher()->inactive()->create())->allows('create', Course::class));
    }

    public function test_assigned_teacher_manages_only_their_course_enrollments(): void
    {
        $enrollment = Enrollment::factory()->create();
        $owner = $enrollment->course->teacher;
        $other = User::factory()->teacher()->create();
        foreach (['view', 'delete'] as $ability) {
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $enrollment));
            $this->assertFalse(Gate::forUser($other)->allows($ability, $enrollment));
        }
        $this->assertTrue(Gate::forUser($owner)->allows('create', [Enrollment::class, $enrollment->course]));
        $this->assertFalse(Gate::forUser($other)->allows('create', [Enrollment::class, $enrollment->course]));
    }

    public function test_lesson_soft_delete_preserves_history_and_denies_access_and_completion_changes(): void
    {
        $completion = LessonCompletion::factory()->create();
        $lesson = $completion->lesson;
        $lesson->delete();
        $this->assertSoftDeleted($lesson);
        $this->assertModelExists($completion);
        $this->assertModelExists($completion->enrollment);
        $this->assertNull(Lesson::find($lesson->id));
        $this->assertTrue($completion->lesson()->withTrashed()->first()->is($lesson));
        foreach ([$completion->enrollment->student, $completion->course->teacher, User::factory()->admin()->create()] as $actor) {
            $this->assertFalse(Gate::forUser($actor)->allows('view', $lesson));
            $this->assertFalse(Gate::forUser($actor)->allows('update', $lesson));
        }
        $this->assertFalse(Gate::forUser($completion->enrollment->student)->allows('delete', $completion->fresh()));
    }

    public static function ineligibleParticipants(): array
    {
        return [
            'student as teacher' => ['teacher', UserRole::Student, true],
            'admin as teacher' => ['teacher', UserRole::Admin, true],
            'inactive teacher' => ['teacher', UserRole::Teacher, false],
            'teacher as student' => ['student', UserRole::Teacher, true],
            'admin as student' => ['student', UserRole::Admin, true],
            'inactive student' => ['student', UserRole::Student, false],
        ];
    }

    #[DataProvider('ineligibleParticipants')]
    public function test_wrong_roles_and_inactive_accounts_fail_eligibility(string $method, UserRole $role, bool $active): void
    {
        $user = User::factory()->create(['role' => $role, 'is_active' => $active]);
        $this->expectException(ValidationException::class);
        app(Participants::class)->$method($user->id);
    }

    public function test_eligible_participants_are_resolved_from_persisted_roles(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $this->assertTrue(app(Participants::class)->teacher($teacher->id)->is($teacher));
        $this->assertTrue(app(Participants::class)->student($student->id)->is($student));
    }
}
