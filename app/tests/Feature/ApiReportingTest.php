<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\ApiTestCase;

class ApiReportingTest extends ApiTestCase
{
    public function test_reports_use_persisted_totals_and_assigned_teacher_scope(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        LessonCompletion::factory()->create(['course_id' => $course->id, 'enrollment_id' => $enrollment->id]);
        Lesson::factory()->published()->create(['course_id' => $course->id]);
        Enrollment::factory()->withdrawn()->create(['course_id' => $course->id]);
        $foreign = Course::factory()->published()->create();
        Enrollment::factory()->create(['course_id' => $foreign->id]);
        Course::factory()->create();

        $teacherToken = $this->token($teacher, ['reports:read']);
        $this->api('GET', '/reports/overview', [], $teacherToken)->assertOk()
            ->assertJsonPath('scope', 'assigned_courses')->assertJsonPath('summary.enrollments', 1)
            ->assertJsonPath('summary.average_progress', 50)->assertJsonPath('summary.active_students', 1)
            ->assertJsonPath('data.0.course_id', $course->id)->assertJsonPath('data.0.eligible_lessons', 2)
            ->assertJsonPath('data.0.completed_lessons', 1)->assertJsonMissingPath('summary.active_teacher_accounts')
            ->assertJsonCount(1, 'data');
        $this->api('GET', '/reports/overview?per_page=1', [], $this->token($admin, ['reports:read']))->assertOk()
            ->assertJsonPath('scope', 'academy')->assertJsonPath('summary.enrollments', 2)
            ->assertJsonPath('summary.average_progress', 25)->assertJsonPath('summary.published_courses', 2)
            ->assertJsonPath('summary.draft_courses', 1)->assertJsonPath('summary.active_student_accounts', 3)
            ->assertJsonPath('meta.total', 3)->assertJsonCount(1, 'data');
        $student = $enrollment->student;
        $this->api('GET', '/reports/overview', [], $this->token($student, ['reports:read']))->assertForbidden();
    }

    public function test_report_access_follows_current_teacher_assignment(): void
    {
        $course = Course::factory()->published()->create();
        $teacher = $course->teacher;
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        $token = $this->token($teacher, ['reports:read', 'enrollments:read', 'progress:read']);
        $course->forceFill(['teacher_id' => User::factory()->teacher()->create()->id])->save();

        $this->api('GET', '/reports/overview', [], $token)->assertOk()->assertJsonPath('summary.enrollments', 0)->assertJsonCount(0, 'data');
        $this->api('GET', '/enrollments', [], $token)->assertOk()->assertJsonCount(0, 'data');
        $this->api('GET', '/enrollments/'.$enrollment->id.'/progress', [], $token)->assertForbidden();
    }

    public function test_representative_api_query_counts_do_not_grow_per_result_row(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin, ['courses:read', 'enrollments:read', 'reports:read']);
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $course->id]);
        $this->travelTo(now()->startOfSecond());
        $measure = function (string $uri) use ($token): array {
            $this->travel(1)->seconds();
            DB::enableQueryLog();
            DB::flushQueryLog();
            $response = $this->api('GET', $uri, [], $token)->assertOk();
            $queries = DB::getQueryLog();
            DB::disableQueryLog();
            DB::flushQueryLog();

            return ['rows' => count($response->json('data')), 'queries' => count($queries),
                'selects' => count(array_filter($queries, fn (array $q): bool => str_starts_with(strtolower($q['query']), 'select')))];
        };
        $metrics = [];
        foreach (['/courses', '/enrollments', '/reports/overview'] as $uri) {
            $metrics[$uri]['one_row'] = $measure($uri);
        }
        Course::factory()->published()->count(11)->create()->each(function (Course $extra): void {
            $enrollment = Enrollment::factory()->create(['course_id' => $extra->id]);
            LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $extra->id]);
        });

        foreach (array_keys($metrics) as $uri) {
            $metrics[$uri]['twelve_rows'] = $measure($uri);
            $this->assertSame(1, $metrics[$uri]['one_row']['rows']);
            $this->assertSame(12, $metrics[$uri]['twelve_rows']['rows']);
            $this->assertSame($metrics[$uri]['one_row']['queries'], $metrics[$uri]['twelve_rows']['queries'], $uri.' must not introduce N+1 queries.');
            $this->assertLessThanOrEqual(12, $metrics[$uri]['twelve_rows']['queries']);
        }
        if ($path = getenv('M5_METRICS_FILE')) {
            file_put_contents($path, json_encode(['method' => 'Real bearer-authenticated Laravel HTTP tests; all SQL statements included, fixture creation excluded.', 'responses' => $metrics], JSON_PRETTY_PRINT)."\n");
        }
    }
}
