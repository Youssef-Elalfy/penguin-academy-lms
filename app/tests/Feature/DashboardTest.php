<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_statistics_reflect_persisted_active_accounts_and_enrollments(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        User::factory()->teacher()->inactive()->create();
        $students = User::factory()->count(2)->create();
        User::factory()->inactive()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $archived = Course::factory()->create(['teacher_id' => $teacher->id]);
        Enrollment::factory()->create(['student_id' => $students[0]->id, 'course_id' => $course->id]);
        Enrollment::factory()->withdrawn()->create(['student_id' => $students[1]->id, 'course_id' => $course->id]);
        Enrollment::factory()->create(['student_id' => $students[0]->id, 'course_id' => $archived->id]);
        $archived->delete();
        $this->actingAs($admin)->get('/admin/dashboard')->assertOk()->assertViewHas('stats', [
            'teachers' => 1, 'students' => 2, 'courses' => 1, 'enrollments' => 1,
        ]);
    }

    public function test_teacher_dashboard_is_scoped_and_counts_unique_students(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = User::factory()->create();
        $first = Course::factory()->create(['teacher_id' => $teacher->id, 'title' => 'My first course']);
        $second = Course::factory()->create(['teacher_id' => $teacher->id]);
        $private = Course::factory()->create(['title' => 'Another teachers private course']);
        Lesson::factory()->count(3)->create(['course_id' => $first->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $first->id]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $second->id]);
        Enrollment::factory()->create(['course_id' => $private->id]);
        $this->actingAs($teacher)->get('/teacher/dashboard')->assertOk()->assertSeeText($first->title)
            ->assertDontSeeText($private->title)->assertViewHas('stats', ['courses' => 2, 'lessons' => 3, 'students' => 1]);
    }

    public function test_student_dashboard_excludes_other_students_withdrawn_draft_and_archived_records(): void
    {
        $student = User::factory()->create();
        $current = Enrollment::factory()->create(['student_id' => $student->id]);
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $current->id]);
        $hiddenLesson = Lesson::factory()->create(['course_id' => $current->course_id]);
        LessonCompletion::factory()->create(['enrollment_id' => $current->id, 'lesson_id' => $hiddenLesson->id]);
        $withdrawn = Enrollment::factory()->withdrawn()->create(['student_id' => $student->id]);
        LessonCompletion::factory()->create(['enrollment_id' => $withdrawn->id]);
        $draftCourse = Course::factory()->create();
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $draftCourse->id]);
        $archived = Enrollment::factory()->create(['student_id' => $student->id]);
        $archived->course->delete();
        $other = Enrollment::factory()->create();
        LessonCompletion::factory()->create(['enrollment_id' => $other->id]);
        $this->actingAs($student)->get('/student/dashboard')->assertOk()
            ->assertSeeText($current->course->title)->assertDontSeeText($withdrawn->course->title)
            ->assertDontSeeText($draftCourse->title)->assertDontSeeText($other->course->title)
            ->assertViewHas('stats', ['enrollments' => 1, 'completed_lessons' => 1, 'available_courses' => 3]);
    }

    public function test_empty_student_workspace_reports_zero_from_the_database(): void
    {
        $this->actingAs(User::factory()->create())->get('/student/dashboard')->assertOk()
            ->assertViewHas('stats', ['enrollments' => 0, 'completed_lessons' => 0, 'available_courses' => 0])
            ->assertSeeText('A new chapter is waiting.');
    }
}
