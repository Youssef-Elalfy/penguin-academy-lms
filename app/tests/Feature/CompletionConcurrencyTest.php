<?php

namespace Tests\Feature;

use App\Actions\Academy\EnrollmentActions;
use App\Actions\Academy\LessonActions;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('m4')]
class CompletionConcurrencyTest extends TestCase
{
    /** Independent processes read committed fixtures, then queue on the same course lock. */
    private function race(Course $course, array $inputs, ?callable $beforeRelease = null): array
    {
        $workers = [];
        DB::beginTransaction();
        Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($inputs as $input) {
                $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/completion-worker.php')], [
                    0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
                ], $pipes, base_path());
                $this->assertIsResource($process);
                $workers[] = ['process' => $process, 'pipes' => $pipes];
                fwrite($pipes[0], json_encode(['course' => $course->id, ...$input], JSON_THROW_ON_ERROR));
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 15);
                $this->assertSame("READY\n", fgets($pipes[1]), 'Worker must load its models before the lock is released.');
            }
            if ($beforeRelease) {
                $beforeRelease();
            }
            DB::commit();
            $results = [];
            foreach ($workers as &$worker) {
                $output = stream_get_contents($worker['pipes'][1]);
                $errors = stream_get_contents($worker['pipes'][2]);
                fclose($worker['pipes'][1]);
                fclose($worker['pipes'][2]);
                $code = proc_close($worker['process']);
                $worker['closed'] = true;
                $this->assertSame(0, $code, $errors);
                $results[] = json_decode(trim($output), true, flags: JSON_THROW_ON_ERROR);
            }

            return $results;
        } finally {
            while (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            foreach ($workers as $worker) {
                if (! ($worker['closed'] ?? false)) {
                    proc_terminate($worker['process']);
                    foreach ($worker['pipes'] as $pipe) {
                        if (is_resource($pipe)) {
                            fclose($pipe);
                        }
                    }
                    proc_close($worker['process']);
                }
            }
        }
    }

    private function withEnrollment(callable $test): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        try {
            $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id]);
            $lesson = Lesson::factory()->published()->create(['course_id' => $course->id]);
            $test($course, $enrollment, $lesson, $student);
        } finally {
            $course->forceDelete();
            $student->delete();
            $teacher->delete();
        }
    }

    public function test_simultaneous_completions_return_one_persisted_record(): void
    {
        $this->withEnrollment(function (Course $course, Enrollment $enrollment, Lesson $lesson, User $student): void {
            $input = ['actor' => $student->id, 'lesson' => $lesson->id, 'completed' => true];
            $results = $this->race($course, [$input, $input]);
            $this->assertSame([200, 200], array_column($results, 'status'));
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $this->assertSame(1, $enrollment->completions()->count());
            $this->assertDatabaseHas('lesson_completions', ['id' => $results[0]['id'], 'course_id' => $course->id, 'enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        });
    }

    public function test_simultaneous_undo_requests_succeed_without_duplicate_changes(): void
    {
        $this->withEnrollment(function (Course $course, Enrollment $enrollment, Lesson $lesson, User $student): void {
            LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
            $input = ['actor' => $student->id, 'lesson' => $lesson->id, 'completed' => false];
            $results = $this->race($course, [$input, $input]);
            $this->assertSame([200, 200], array_column($results, 'status'));
            $this->assertSame([null, null], array_column($results, 'id'));
            $this->assertSame(0, $enrollment->completions()->count());
        });
    }

    #[TestWith([true])]
    #[TestWith([false])]
    public function test_withdrawal_before_queued_completion_mutations_prevents_changes(bool $completed): void
    {
        $this->withEnrollment(function (Course $course, Enrollment $enrollment, Lesson $lesson, User $student) use ($completed): void {
            $history = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
            $results = $this->race($course, [['actor' => $student->id, 'lesson' => $lesson->id, 'completed' => $completed]],
                fn () => app(EnrollmentActions::class)->withdraw($student, $course, $enrollment));
            $this->assertSame([403], array_column($results, 'status'));
            $this->assertModelExists($history);
            $this->assertNotNull($enrollment->fresh()->withdrawn_at);
        });
    }

    public function test_archiving_a_lesson_before_a_queued_completion_preserves_history(): void
    {
        $this->withEnrollment(function (Course $course, Enrollment $enrollment, Lesson $lesson, User $student): void {
            $history = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
            $results = $this->race($course, [['actor' => $student->id, 'lesson' => $lesson->id, 'completed' => false]],
                fn () => app(LessonActions::class)->delete($course->teacher, $course, $lesson));
            $this->assertSame([404], array_column($results, 'status'));
            $this->assertModelExists($history);
            $this->assertSoftDeleted($lesson);
        });
    }
}
