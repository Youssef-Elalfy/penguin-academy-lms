<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private function assertMysqlError(callable $operation, int $code): void
    {
        try {
            $operation();
            $this->fail('MySQL must reject this invalid write.');
        } catch (QueryException $exception) {
            $this->assertSame($code, (int) $exception->errorInfo[1]);
        }
    }

    public function test_model_relationships_and_lesson_order_use_persisted_records(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $later = Lesson::factory()->create(['course_id' => $course->id, 'position' => 20]);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'position' => 10]);
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'lesson_id' => $first->id]);
        $this->assertSame([$first->id, $later->id], $course->lessons->modelKeys());
        $this->assertTrue($course->teacher->taughtCourses->contains($course));
        $this->assertTrue($enrollment->student->enrollments->contains($enrollment));
        $this->assertTrue($course->enrollments->contains($enrollment));
        $this->assertTrue($first->completions->contains($completion));
        $this->assertTrue($enrollment->completions->contains($completion));
        $this->assertTrue($completion->course->is($course));
        $this->assertTrue($completion->lesson->is($first));
        $this->assertTrue($completion->enrollment->is($enrollment));
    }

    public function test_duplicate_enrollment_is_rejected_by_mysql(): void
    {
        $existing = Enrollment::factory()->create();
        $this->assertMysqlError(fn () => Enrollment::factory()->create(['student_id' => $existing->student_id, 'course_id' => $existing->course_id]), 1062);
    }

    public function test_duplicate_completion_is_rejected_by_mysql(): void
    {
        $existing = LessonCompletion::factory()->create();
        $this->assertMysqlError(fn () => LessonCompletion::factory()->create(['enrollment_id' => $existing->enrollment_id, 'course_id' => $existing->course_id, 'lesson_id' => $existing->lesson_id]), 1062);
    }

    public function test_cross_course_completion_is_rejected_even_without_application_validation(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lesson = Lesson::factory()->create();
        $this->assertMysqlError(fn () => DB::table('lesson_completions')->insert(['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id, 'lesson_id' => $lesson->id]), 1452);
    }

    public function test_forged_completion_course_cannot_match_only_the_lesson(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lesson = Lesson::factory()->create();
        $this->assertMysqlError(fn () => DB::table('lesson_completions')->insert(['enrollment_id' => $enrollment->id, 'course_id' => $lesson->course_id, 'lesson_id' => $lesson->id]), 1452);
    }

    public function test_duplicate_lesson_position_is_rejected(): void
    {
        $lesson = Lesson::factory()->create();
        $this->assertMysqlError(fn () => Lesson::factory()->create(['course_id' => $lesson->course_id, 'position' => $lesson->position]), 1062);
    }

    public function test_orphan_foreign_keys_are_rejected(): void
    {
        $this->assertMysqlError(fn () => Course::factory()->create(['teacher_id' => PHP_INT_MAX]), 1452);
    }

    public function test_soft_deletion_preserves_learning_records_and_hard_deletion_cascades(): void
    {
        $completion = LessonCompletion::factory()->create();
        $course = $completion->course;
        $teacher = $course->teacher;
        $course->delete();
        $this->assertNull(Course::find($course->id));
        $this->assertNotNull(Course::withTrashed()->find($course->id));
        $this->assertDatabaseHas('lessons', ['id' => $completion->lesson_id]);
        $this->assertDatabaseHas('lesson_completions', ['id' => $completion->id]);
        $course->forceDelete();
        $this->assertDatabaseMissing('lessons', ['id' => $completion->lesson_id]);
        $this->assertDatabaseMissing('enrollments', ['id' => $completion->enrollment_id]);
        $this->assertDatabaseMissing('lesson_completions', ['id' => $completion->id]);
        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
    }

    public function test_deleting_referenced_teacher_or_student_is_restricted(): void
    {
        $enrollment = Enrollment::factory()->create();
        $this->assertMysqlError(fn () => $enrollment->course->teacher->delete(), 1451);
        $this->assertMysqlError(fn () => $enrollment->student->delete(), 1451);
    }

    public function test_changing_a_referenced_enrollments_course_is_restricted(): void
    {
        $completion = LessonCompletion::factory()->create();
        $course = Course::factory()->create();
        $this->assertMysqlError(fn () => DB::table('enrollments')->where('id', $completion->enrollment_id)->update(['course_id' => $course->id]), 1451);
    }

    public function test_database_rejects_an_unknown_role(): void
    {
        $user = User::factory()->create();
        $this->assertMysqlError(fn () => DB::table('users')->where('id', $user->id)->update(['role' => 'superadmin']), 1265);
    }
}
