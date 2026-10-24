<?php

namespace Tests\Feature;

use App\Actions\Academy\EnrollmentActions;
use App\Actions\Academy\LessonActions;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Services\Learning\ProgressService;
use App\Services\Learning\ProgressSummary;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('m4')]
class LearningProgressTest extends TestCase
{
    use DatabaseTransactions;

    public function test_zero_eligible_lessons_has_zero_progress_and_is_not_complete(): void
    {
        $enrollment = Enrollment::factory()->create();
        $draft = Lesson::factory()->create(['course_id' => $enrollment->course_id]);
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $draft->id]);

        $progress = app(ProgressService::class)->forEnrollment($enrollment);

        $this->assertSame(0, $progress->total);
        $this->assertSame(0, $progress->completed);
        $this->assertSame(0.0, $progress->percent);
        $this->assertFalse($progress->isComplete);
        $this->assertNull($progress->nextLessonId);
    }

    public function test_only_same_enrollment_completions_of_published_current_lessons_count(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lessons = Lesson::factory()->published()->count(3)->create(['course_id' => $enrollment->course_id]);
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lessons[0]->id]);
        $draft = Lesson::factory()->create(['course_id' => $enrollment->course_id]);
        $archived = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        foreach ([$draft, $archived] as $lesson) {
            LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        }
        $archived->delete();
        $other = Enrollment::factory()->create(['course_id' => $enrollment->course_id]);
        LessonCompletion::factory()->create(['enrollment_id' => $other->id, 'lesson_id' => $lessons[1]->id]);
        LessonCompletion::factory()->create();

        $progress = app(ProgressService::class)->forEnrollment($enrollment);

        $this->assertSame(3, $progress->total);
        $this->assertSame(1, $progress->completed);
        $this->assertSame(33.33, $progress->percent);
        $this->assertSame(3, $enrollment->completions()->count());
    }

    public function test_curriculum_changes_recalculate_existing_progress_without_destroying_history(): void
    {
        $enrollment = Enrollment::factory()->create();
        $first = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $first->id]);
        $service = app(ProgressService::class);
        $this->assertSame(100.0, $service->forEnrollment($enrollment)->percent);

        $second = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $this->assertSame(50.0, $service->forEnrollment($enrollment)->percent);
        $second->update(['is_published' => false]);
        $this->assertSame(100.0, $service->forEnrollment($enrollment)->percent);
        $first->delete();
        $this->assertSame(0.0, $service->forEnrollment($enrollment)->percent);
        $this->assertModelExists($completion);
        $first->restore();
        $this->assertSame(100.0, $service->forEnrollment($enrollment)->percent);
    }

    public function test_continue_learning_uses_the_first_incomplete_eligible_lesson_in_current_order(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $lessons = Lesson::factory()->published()->count(3)->sequence(['position' => 1], ['position' => 2], ['position' => 3])->create(['course_id' => $course->id]);
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lessons[0]->id]);
        $service = app(ProgressService::class);
        $this->assertSame($lessons[1]->id, $service->forEnrollment($enrollment)->nextLessonId);

        app(LessonActions::class)->reorder($course->teacher, $course, [$lessons[2]->id, $lessons[0]->id, $lessons[1]->id], 0);

        $this->assertSame($lessons[2]->id, $service->forEnrollment($enrollment)->nextLessonId);
        $lessons[2]->update(['is_published' => false]);
        $this->assertSame($lessons[1]->id, $service->forEnrollment($enrollment)->nextLessonId);
        $lessons[1]->delete();
        $progress = $service->forEnrollment($enrollment);
        $this->assertTrue($progress->isComplete);
        $this->assertNull($progress->nextLessonId);
    }

    public function test_withdrawn_or_unavailable_courses_never_offer_a_continue_lesson(): void
    {
        $enrollment = Enrollment::factory()->create();
        Lesson::factory()->published()->count(2)->create(['course_id' => $enrollment->course_id]);
        $first = $enrollment->course->lessons()->first();
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $first->id]);
        $service = app(ProgressService::class);
        app(EnrollmentActions::class)->withdraw($enrollment->student, $enrollment->course, $enrollment);
        $this->assertSame(50.0, $service->forEnrollment($enrollment)->percent);
        $this->assertNull($service->forEnrollment($enrollment)->nextLessonId);
        app(EnrollmentActions::class)->enroll($enrollment->student, $enrollment->course);
        $this->assertNotNull($service->forEnrollment($enrollment)->nextLessonId);
        $enrollment->course->forceFill(['published_at' => null])->save();
        $this->assertNull($service->forEnrollment($enrollment)->nextLessonId);
        $enrollment->course->delete();
        $this->assertNull($service->forEnrollment($enrollment)->nextLessonId);
        $this->assertSame(1, $enrollment->completions()->count());
    }

    public function test_single_progress_snapshot_uses_one_aggregate_query(): void
    {
        $enrollment = Enrollment::factory()->create();
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id]);
        DB::flushQueryLog();
        DB::enableQueryLog();

        $progress = app(ProgressService::class)->forEnrollment($enrollment);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertSame(100.0, $progress->percent);
        $this->assertCount(1, $queries);
        $this->assertStringContainsString('count(*)', strtolower($queries[0]['query']));
    }

    #[TestWith([0, 0, 0.0])]
    #[TestWith([2, 3, 66.67])]
    #[TestWith([5, 2, 100.0])]
    #[TestWith([-1, 3, 0.0])]
    public function test_percentage_stays_between_zero_and_one_hundred(int $completed, int $total, float $expected): void
    {
        $summary = new ProgressSummary($completed, $total);
        $this->assertSame($expected, $summary->percent);
    }
}
