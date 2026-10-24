<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('m3')]
class AcademyConcurrencyTest extends TestCase
{
    /** Real independent PHP processes share the dedicated MySQL test database. */
    private function race(Course $course, array $inputs): array
    {
        $workers = [];
        DB::beginTransaction();
        Course::whereKey($course->id)->lockForUpdate()->firstOrFail();
        try {
            foreach ($inputs as $input) {
                $process = proc_open([PHP_BINARY, base_path('tests/Fixtures/academy-worker.php')], [
                    0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w'],
                ], $pipes, base_path());
                $this->assertIsResource($process);
                $workers[] = ['process' => $process, 'pipes' => $pipes];
                fwrite($pipes[0], json_encode(['course' => $course->id, ...$input], JSON_THROW_ON_ERROR));
                fclose($pipes[0]);
                stream_set_timeout($pipes[1], 15);
                $this->assertSame("READY\n", fgets($pipes[1]), 'Worker must boot and reach the shared course lock.');
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

    public function test_simultaneous_enrollment_requests_return_the_same_record(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        try {
            $request = ['operation' => 'enroll', 'actor' => $student->id];
            $results = $this->race($course, [$request, $request]);
            $this->assertSame([200, 200], array_column($results, 'status'));
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $this->assertSame(1, $course->enrollments()->active()->count());
        } finally {
            $course->forceDelete();
            $student->delete();
            $teacher->delete();
        }
    }

    public function test_simultaneous_lesson_creation_allocates_distinct_positions(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        try {
            $results = $this->race($course, [
                ['operation' => 'lesson', 'actor' => $teacher->id, 'title' => 'Parallel lesson one'],
                ['operation' => 'lesson', 'actor' => $teacher->id, 'title' => 'Parallel lesson two'],
            ]);
            $this->assertSame([200, 200], array_column($results, 'status'));
            $this->assertSame([1, 2], $course->lessons()->pluck('position')->all());
            $this->assertSame(2, $course->fresh()->lesson_order_version);
        } finally {
            $course->forceDelete();
            $teacher->delete();
        }
    }

    public function test_simultaneous_reorders_commit_once_and_return_a_conflict_to_the_other_editor(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $lessons = Lesson::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->create(['course_id' => $course->id]);
        try {
            $ids = $lessons->modelKeys();
            $results = $this->race($course, [
                ['operation' => 'reorder', 'actor' => $teacher->id, 'ids' => array_reverse($ids)],
                ['operation' => 'reorder', 'actor' => $teacher->id, 'ids' => $ids],
            ]);
            $statuses = array_column($results, 'status');
            sort($statuses);
            $this->assertSame([200, 409], $statuses);
            $winner = collect($results)->firstWhere('status', 200);
            $this->assertSame($winner['ids'], $course->lessons()->pluck('id')->all());
            $this->assertSame(1, $course->fresh()->lesson_order_version);
        } finally {
            $course->forceDelete();
            $teacher->delete();
        }
    }
}
