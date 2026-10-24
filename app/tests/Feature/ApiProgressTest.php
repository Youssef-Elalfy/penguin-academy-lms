<?php

namespace Tests\Feature;

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiProgressTest extends ApiTestCase
{
    public function test_completion_and_undo_are_idempotent_and_persist_correct_progress(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $lessons = Lesson::factory()->published()->count(3)->create(['course_id' => $course->id]);
        $token = $this->token($enrollment->student, ['progress:write', 'progress:read']);
        $url = '/enrollments/'.$enrollment->id.'/lessons/'.$lessons[0]->id.'/completion';

        $this->api('PUT', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 33.33)->assertJsonPath('data.completed_lessons', 1);
        $completion = LessonCompletion::sole();
        $this->api('PUT', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 33.33);
        $this->assertDatabaseCount('lesson_completions', 1);
        $this->assertDatabaseHas('lesson_completions', ['id' => $completion->id, 'enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'lesson_id' => $lessons[0]->id]);
        $this->api('GET', '/enrollments/'.$enrollment->id.'/progress', [], $token)->assertOk()->assertJsonPath('data.percent', 33.33);
        $this->api('DELETE', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 0);
        $this->api('DELETE', $url, [], $token)->assertOk()->assertJsonPath('data.completed_lessons', 0);
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_progress_uses_current_eligible_curriculum_and_zero_lessons_means_zero(): void
    {
        $enrollment = Enrollment::factory()->create();
        $token = $this->token($enrollment->student, ['progress:read', 'progress:write']);
        $url = '/enrollments/'.$enrollment->id.'/progress';

        $this->api('GET', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 0)->assertJsonPath('data.is_complete', false);
        $done = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id]);
        $this->api('GET', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 100)->assertJsonPath('data.is_complete', true);
        $new = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $this->api('GET', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 50);
        $new->forceFill(['is_published' => false])->save();
        $this->api('GET', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 100);
        $done->lesson->delete();
        $this->api('GET', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 0)->assertJsonPath('data.completed_lessons', 0);
        $this->assertModelExists($done);
    }

    public function test_withdrawal_preserves_history_blocks_changes_and_reenrollment_restores_access(): void
    {
        $enrollment = Enrollment::factory()->create();
        $done = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id]);
        $token = $this->token($enrollment->student, ['progress:read', 'progress:write', 'enrollments:write']);
        $url = '/enrollments/'.$enrollment->id.'/lessons/'.$done->lesson_id.'/completion';

        $this->api('DELETE', '/enrollments/'.$enrollment->id, [], $token)->assertNoContent();
        $this->api('PUT', $url, [], $token)->assertForbidden();
        $this->api('DELETE', $url, [], $token)->assertForbidden();
        $this->api('GET', '/enrollments/'.$enrollment->id.'/progress', [], $token)->assertOk()->assertJsonPath('data.percent', 100)->assertJsonPath('data.next_lesson_id', null);
        $this->assertModelExists($done);
        $this->api('POST', '/enrollments', ['course_id' => $enrollment->course_id], $token)->assertOk()->assertJsonPath('data.id', $enrollment->id);
        $this->api('DELETE', $url, [], $token)->assertOk()->assertJsonPath('data.percent', 0);
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    #[TestWith(['PUT'])]
    #[TestWith(['DELETE'])]
    public function test_cross_course_and_cross_student_completion_requests_are_rejected(string $method): void
    {
        $enrollment = Enrollment::factory()->create();
        $other = Enrollment::factory()->create(['course_id' => $enrollment->course_id]);
        $lesson = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $foreign = Lesson::factory()->published()->create();
        $token = $this->token($enrollment->student, ['progress:write', 'progress:read']);

        $this->api($method, '/enrollments/'.$other->id.'/lessons/'.$lesson->id.'/completion', [], $token)->assertForbidden();
        $this->api($method, '/enrollments/'.$enrollment->id.'/lessons/'.$foreign->id.'/completion', [], $token)->assertNotFound();
        $this->api('GET', '/enrollments/'.$other->id.'/progress', [], $token)->assertForbidden();
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    #[TestWith(['unpublished-lesson', 403])]
    #[TestWith(['archived-lesson', 404])]
    #[TestWith(['unpublished-course', 403])]
    #[TestWith(['archived-course', 403])]
    public function test_inaccessible_content_cannot_change_history(string $case, int $status): void
    {
        $enrollment = Enrollment::factory()->create();
        $done = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id]);
        $token = $this->token($enrollment->student, ['progress:write']);
        if ($case === 'unpublished-lesson') {
            $done->lesson->forceFill(['is_published' => false])->save();
        } elseif ($case === 'archived-lesson') {
            $done->lesson->delete();
        } elseif ($case === 'unpublished-course') {
            $enrollment->course->forceFill(['published_at' => null])->save();
        } else {
            $enrollment->course->delete();
        }
        $url = '/enrollments/'.$enrollment->id.'/lessons/'.$done->lesson_id.'/completion';

        $this->api('PUT', $url, [], $token)->assertStatus($status);
        $this->api('DELETE', $url, [], $token)->assertStatus($status);
        $this->assertModelExists($done);
    }

    public function test_forged_completion_fields_are_rejected_without_writes(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lesson = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $token = $this->token($enrollment->student, ['progress:write']);

        $this->api('PUT', '/enrollments/'.$enrollment->id.'/lessons/'.$lesson->id.'/completion', ['student_id' => $enrollment->student_id, 'percent' => 100], $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['student_id', 'percent']);
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    public function test_staff_can_read_authorized_progress_but_cannot_complete_for_students(): void
    {
        $enrollment = Enrollment::factory()->create();
        $lesson = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);
        $teacher = $enrollment->course->teacher;
        $token = $this->token($teacher, ['progress:read', 'progress:write']);
        $otherToken = $this->token(User::factory()->teacher()->create(), ['progress:read']);

        $this->api('GET', '/enrollments/'.$enrollment->id.'/progress', [], $token)->assertOk()->assertJsonPath('data.percent', 0);
        $this->api('GET', '/enrollments/'.$enrollment->id.'/progress', [], $otherToken)->assertForbidden();
        $this->api('PUT', '/enrollments/'.$enrollment->id.'/lessons/'.$lesson->id.'/completion', [], $token)->assertForbidden();
        $this->assertDatabaseCount('lesson_completions', 0);
    }
}
