<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('m4')]
class LearningReportsTest extends TestCase
{
    use DatabaseTransactions;

    private function analyticsData(): array
    {
        $this->freezeTime();
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $otherTeacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $inactiveStudent = User::factory()->create(['is_active' => false]);
        $withdrawnStudent = User::factory()->create();
        $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        $draft = Course::factory()->create(['teacher_id' => $teacher->id]);
        $otherCourse = Course::factory()->published()->create(['teacher_id' => $otherTeacher->id, 'title' => 'Private other teacher course']);
        $archived = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        $lessons = Lesson::factory()->published()->count(2)->create(['course_id' => $course->id]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'student_id' => $student->id]);
        $finished = Enrollment::factory()->create(['course_id' => $course->id, 'student_id' => $inactiveStudent->id]);
        $withdrawn = Enrollment::factory()->withdrawn()->create(['course_id' => $course->id, 'student_id' => $withdrawnStudent->id]);
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lessons[0]->id]);
        foreach ([$finished, $withdrawn] as $row) {
            foreach ($lessons as $lesson) {
                LessonCompletion::factory()->create(['enrollment_id' => $row->id, 'lesson_id' => $lesson->id]);
            }
        }
        Enrollment::factory()->create(['course_id' => $draft->id, 'student_id' => $student->id]);
        $otherEnrollment = Enrollment::factory()->create(['course_id' => $otherCourse->id, 'student_id' => $student->id]);
        LessonCompletion::factory()->create(['enrollment_id' => $otherEnrollment->id]);
        $archivedEnrollment = Enrollment::factory()->create(['course_id' => $archived->id, 'student_id' => $student->id]);
        LessonCompletion::factory()->create(['enrollment_id' => $archivedEnrollment->id]);
        $archived->delete();

        return compact('admin', 'teacher', 'student', 'course', 'draft', 'otherCourse', 'enrollment', 'finished', 'withdrawn', 'otherEnrollment', 'lessons');
    }

    public function test_teacher_dashboard_aggregates_only_assigned_current_courses(): void
    {
        $data = $this->analyticsData();
        $response = $this->actingAs($data['teacher'])->get(route('teacher.dashboard'))->assertOk()->assertDontSee($data['otherCourse']->title);
        $this->assertSame([
            'enrollments' => 3, 'eligible_assignments' => 4, 'completed_lessons' => 3,
            'completed_enrollments' => 1, 'average_progress' => 50.0, 'completion_rate' => 33.33,
            'last_completed_at' => now()->format('Y-m-d H:i:s'), 'active_students' => 1,
            'published_courses' => 1, 'draft_courses' => 1,
        ], $response->viewData('learningStats'));
        $courses = $response->viewData('courses');
        $this->assertSame(2, $courses->total());
        $course = $courses->firstWhere('id', $data['course']->id);
        $this->assertSame(2, (int) $course->enrollments_count);
        $this->assertSame(75.0, (float) $course->average_progress);
        $this->assertSame(50.0, (float) $course->completion_rate);
    }

    public function test_admin_dashboard_uses_platform_data_and_preserves_previous_stat_contract(): void
    {
        $data = $this->analyticsData();
        $response = $this->actingAs($data['admin'])->get(route('admin.dashboard'))->assertOk();
        $this->assertSame(['teachers' => 2, 'students' => 2, 'courses' => 3, 'enrollments' => 4], $response->viewData('stats'));
        $metrics = $response->viewData('learningStats');
        $this->assertSame(2, $metrics['published_courses']);
        $this->assertSame(1, $metrics['draft_courses']);
        $this->assertSame(4, $metrics['completed_lessons']);
        $this->assertSame(2, $metrics['completed_enrollments']);
        $this->assertSame(62.5, $metrics['average_progress']);
        $this->assertSame(50.0, $metrics['completion_rate']);
        $this->assertSame(3, $response->viewData('courses')->total());
        $this->get(route('admin.enrollments.index'))->assertOk()->assertSee('Progress')->assertSee('50%');
    }

    public function test_student_dashboard_and_continue_links_follow_persisted_completion_changes(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lessons = Lesson::factory()->published()->count(2)->sequence(['position' => 1], ['position' => 2])->create(['course_id' => $enrollment->course_id]);
        $this->actingAs($enrollment->student);
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee(route('courses.lessons.show', [$enrollment->course, $lessons[0]]));
        $this->assertSame(0.0, $response->viewData('learningStats')['average_progress']);
        $this->put(route('courses.lessons.completion.store', [$enrollment->course, $lessons[0]]))->assertRedirect();
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee(route('courses.lessons.show', [$enrollment->course, $lessons[1]]));
        $this->assertSame(50.0, $response->viewData('learningStats')['average_progress']);
        $this->get(route('my-courses'))->assertOk()->assertSee('1 of 2 lessons complete');
        $this->put(route('courses.lessons.completion.store', [$enrollment->course, $lessons[1]]))->assertRedirect();
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee('Review course');
        $this->assertSame(1, $response->viewData('learningStats')['completed_enrollments']);
        $this->assertSame(100.0, $response->viewData('learningStats')['average_progress']);
        $this->delete(route('courses.lessons.completion.destroy', [$enrollment->course, $lessons[0]]))->assertRedirect();
        $response = $this->get(route('student.dashboard'))->assertOk()->assertSee(route('courses.lessons.show', [$enrollment->course, $lessons[0]]));
        $this->assertSame(0, $response->viewData('learningStats')['completed_enrollments']);
        $this->assertSame(1, $response->viewData('learningStats')['completed_lessons']);
    }

    public function test_reports_require_assigned_staff_and_scoped_enrollment_bindings(): void
    {
        $data = $this->analyticsData();
        $index = route('courses.progress.index', $data['course']);
        $show = route('courses.progress.show', [$data['course'], $data['enrollment']]);
        $this->get($index)->assertRedirect(route('login'));
        $this->get($show)->assertRedirect(route('login'));
        $response = $this->actingAs($data['teacher'])->get($index)->assertOk();
        $this->assertSame(75.0, $response->viewData('metrics')['average_progress']);
        $response = $this->get($show)->assertOk()->assertSee('Completed')->assertSee('Incomplete');
        $this->assertSame(50.0, $response->viewData('progress')->percent);
        foreach ([$data['otherCourse']->teacher, $data['student']] as $actor) {
            $this->actingAs($actor)->get($index)->assertForbidden();
            $this->get($show)->assertForbidden();
        }
        $this->actingAs($data['admin'])->get($show)->assertOk();
        $this->get(route('courses.progress.show', [$data['course'], $data['otherEnrollment']]))->assertNotFound();
        $this->get(route('courses.progress.show', [$data['otherCourse'], $data['enrollment']]))->assertNotFound();
    }

    public function test_report_filters_and_pagination_do_not_change_summary_scope(): void
    {
        $course = Course::factory()->published()->create();
        Enrollment::factory()->count(16)->create(['course_id' => $course->id]);
        $withdrawn = Enrollment::factory()->withdrawn()->create(['course_id' => $course->id]);
        $this->actingAs($course->teacher);
        $url = route('courses.progress.index', $course);
        $response = $this->get($url)->assertOk();
        $this->assertSame(16, $response->viewData('enrollments')->total());
        $this->assertCount(15, $response->viewData('enrollments'));
        $this->assertCount(1, $this->get($url.'?page=2')->assertOk()->viewData('enrollments'));
        $response = $this->get($url.'?status=withdrawn')->assertOk();
        $this->assertSame(1, $response->viewData('enrollments')->total());
        $this->assertSame($withdrawn->id, $response->viewData('enrollments')->first()->id);
        $this->assertSame(16, $response->viewData('metrics')['enrollments']);
        $response = $this->get($url.'?'.http_build_query(['status' => 'all', 'q' => $withdrawn->student->email]))->assertOk();
        $this->assertSame(1, $response->viewData('enrollments')->total());
        $this->getJson($url.'?status=invalid')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson($url.'?q='.str_repeat('a', 121))->assertUnprocessable()->assertJsonValidationErrors('q');
    }

    public function test_student_detail_excludes_ineligible_lessons_and_retains_withdrawn_history(): void
    {
        $enrollment = Enrollment::factory()->withdrawn()->create();
        $lessons = Lesson::factory()->published()->count(21)->create(['course_id' => $enrollment->course_id]);
        $draft = Lesson::factory()->create(['course_id' => $enrollment->course_id, 'title' => 'Private draft lesson']);
        $archived = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id, 'title' => 'Archived lesson title']);
        foreach ([$lessons[0], $draft, $archived] as $lesson) {
            LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        }
        $archived->delete();
        $response = $this->actingAs($enrollment->course->teacher)->get(route('courses.progress.show', [$enrollment->course, $enrollment]))
            ->assertOk()->assertSee('history retained')->assertDontSee($draft->title)->assertDontSee($archived->title);
        $this->assertSame(21, $response->viewData('lessons')->total());
        $this->assertCount(20, $response->viewData('lessons'));
        $this->assertSame(4.76, $response->viewData('progress')->percent);
        $this->assertSame(3, $enrollment->completions()->count());
    }

    public function test_archived_courses_are_not_available_through_report_routes(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $teacher = $course->teacher;
        $course->delete();
        $this->actingAs($teacher)->get(route('courses.progress.index', $course))->assertNotFound();
        $this->get(route('courses.progress.show', [$course, $enrollment]))->assertNotFound();
    }

    public function test_report_content_is_html_escaped(): void
    {
        $enrollment = Enrollment::factory()->create();
        $studentName = '<script>alert("student")</script>';
        $courseTitle = '<script>alert("course")</script>';
        $lessonTitle = '<script>alert("lesson")</script>';
        $enrollment->student->update(['name' => $studentName]);
        $enrollment->course->update(['title' => $courseTitle]);
        Lesson::factory()->published()->create(['course_id' => $enrollment->course_id, 'title' => $lessonTitle]);
        $response = $this->actingAs($enrollment->course->teacher)->get(route('courses.progress.show', [$enrollment->course, $enrollment]))->assertOk();
        foreach ([$studentName, $courseTitle, $lessonTitle] as $content) {
            $response->assertSee(e($content), false)->assertDontSee($content, false);
        }
    }

    public function test_report_queries_do_not_grow_per_student(): void
    {
        $course = Course::factory()->published()->create();
        Lesson::factory()->published()->count(2)->create(['course_id' => $course->id]);
        Enrollment::factory()->create(['course_id' => $course->id]);
        $this->actingAs($course->teacher);
        $counts = [];
        foreach ([1, 15] as $size) {
            if ($size === 15) {
                Enrollment::factory()->count(14)->create(['course_id' => $course->id]);
            }
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('courses.progress.index', $course))->assertOk();
            $counts[] = count(DB::getQueryLog());
            DB::disableQueryLog();
        }
        $this->assertSame($counts[0], $counts[1], 'Rendering more learners must not add per-student queries.');
        $this->assertLessThanOrEqual(12, $counts[1]);
    }

    public function test_withdrawn_student_sees_retained_progress_without_learning_actions(): void
    {
        $enrollment = Enrollment::factory()->withdrawn()->create();
        $lessons = Lesson::factory()->published()->count(2)->create(['course_id' => $enrollment->course_id]);
        LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lessons[0]->id]);
        $this->actingAs($enrollment->student)->get(route('my-courses', ['status' => 'withdrawn']))
            ->assertOk()->assertSee('50%')->assertDontSee('Continue learning');
        $this->get(route('courses.show', $enrollment->course))->assertOk()->assertSee('Your retained progress')
            ->assertSee('50%')->assertDontSee('Continue learning')->assertDontSee('Mark complete');
    }
}
