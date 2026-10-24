<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiLessonTest extends ApiTestCase
{
    public function test_teacher_creates_edits_publishes_orders_and_archives_lessons(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        $token = $this->token($teacher, ['lessons:write', 'lessons:read']);
        $url = '/courses/'.$course->id.'/lessons';
        $first = $this->api('POST', $url, ['title' => 'First', 'content' => 'Read this.'], $token)->assertCreated()->json('data.id');
        $second = $this->api('POST', $url, ['title' => 'Second', 'content' => 'Next.'], $token)->assertCreated()->json('data.id');

        $this->api('PATCH', $url.'/'.$first, ['content' => 'Revised content'], $token)->assertOk()->assertJsonPath('data.content', 'Revised content');
        $this->api('PUT', $url.'/'.$first.'/publication', ['is_published' => true], $token)->assertOk()->assertJsonPath('data.is_published', true);
        $version = $course->refresh()->lesson_order_version;
        $this->api('PUT', $url.'/order', ['order_version' => $version, 'lesson_ids' => [$second, $first]], $token)
            ->assertOk()->assertJsonPath('data.lesson_order_version', $version + 1);
        $this->api('GET', $url, [], $token)->assertOk()->assertJsonPath('data.0.id', $second)->assertJsonMissingPath('data.0.content');
        $this->assertDatabaseHas('lessons', ['id' => $first, 'content' => 'Revised content', 'position' => 2]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'course_id' => $course->id, 'lesson_id' => $first]);
        $this->api('DELETE', $url.'/'.$first, [], $token)->assertNoContent();
        $this->assertSoftDeleted('lessons', ['id' => $first]);
        $this->assertModelExists($completion);
        $this->api('GET', $url.'/'.$first, [], $token)->assertNotFound();
    }

    public function test_stale_and_invalid_order_requests_preserve_positions(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $first = Lesson::factory()->create(['course_id' => $course->id, 'position' => 1]);
        $second = Lesson::factory()->create(['course_id' => $course->id, 'position' => 2]);
        $url = '/courses/'.$course->id.'/lessons/order';
        $token = $this->token($teacher, ['lessons:write']);

        $this->api('PUT', $url, ['order_version' => 100, 'lesson_ids' => [$second->id, $first->id]], $token)->assertConflict()->assertJsonPath('code', 'conflict');
        $this->api('PUT', $url, ['order_version' => 0, 'lesson_ids' => [$first->id, $first->id]], $token)->assertUnprocessable();
        $this->api('PUT', $url, ['order_version' => 0, 'lesson_ids' => [$first->id]], $token)->assertUnprocessable();
        $this->assertDatabaseHas('lessons', ['id' => $first->id, 'position' => 1]);
        $this->assertDatabaseHas('lessons', ['id' => $second->id, 'position' => 2]);
    }

    #[TestWith(['GET'])]
    #[TestWith(['PATCH'])]
    #[TestWith(['DELETE'])]
    public function test_cross_course_nested_lesson_requests_return_404(string $method): void
    {
        $admin = User::factory()->admin()->create();
        $course = Course::factory()->create();
        $foreign = Lesson::factory()->create();
        $token = $this->token($admin, ['lessons:read', 'lessons:write']);

        $this->api($method, '/courses/'.$course->id.'/lessons/'.$foreign->id, [], $token)->assertNotFound();
        $this->assertNotSoftDeleted($foreign);
    }

    public function test_student_requires_current_enrollment_and_published_non_archived_content(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();
        $published = Lesson::factory()->published()->create(['course_id' => $course->id]);
        $draft = Lesson::factory()->create(['course_id' => $course->id]);
        $url = '/courses/'.$course->id.'/lessons';
        $token = $this->token($student, ['lessons:read']);

        $this->assertFalse(Gate::forUser($student)->allows('viewAny', [Lesson::class, $course]));
        $this->api('GET', $url, [], $token)->assertForbidden();
        $this->api('GET', $url.'/'.$published->id, [], $token)->assertForbidden();
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id, 'student_id' => $student->id]);
        $this->assertTrue(Gate::forUser($student)->allows('viewAny', [Lesson::class, $course]));
        $this->api('GET', $url, [], $token)->assertOk()->assertJsonCount(1, 'data');
        $this->api('GET', $url.'/'.$published->id, [], $token)->assertOk()->assertJsonPath('data.content', $published->content);
        $this->api('GET', $url.'/'.$draft->id, [], $token)->assertForbidden();
        $course->forceFill(['published_at' => null])->save();
        $this->api('GET', $url, [], $token)->assertForbidden();
        $course->forceFill(['published_at' => now()->subMinute()])->save();
        $enrollment->forceFill(['withdrawn_at' => now()])->save();
        $this->api('GET', $url.'/'.$published->id, [], $token)->assertForbidden();
        $course->delete();
        $this->api('GET', $url, [], $token)->assertNotFound();
    }

    #[TestWith(['student'])]
    #[TestWith(['other-teacher'])]
    public function test_unassigned_users_cannot_manage_lessons(string $role): void
    {
        $user = $role === 'student' ? User::factory()->create() : User::factory()->teacher()->create();
        $lesson = Lesson::factory()->create();
        $url = '/courses/'.$lesson->course_id.'/lessons';
        $token = $this->token($user, ['lessons:write']);

        $this->api('POST', $url, ['title' => 'Denied', 'content' => 'Denied'], $token)->assertForbidden();
        $this->api('PATCH', $url.'/'.$lesson->id, ['content' => 'Denied'], $token)->assertForbidden();
        $this->api('PUT', $url.'/'.$lesson->id.'/publication', ['is_published' => true], $token)->assertForbidden();
        $this->api('DELETE', $url.'/'.$lesson->id, [], $token)->assertForbidden();
        $this->assertNotSoftDeleted($lesson);
        $this->assertDatabaseCount('lessons', 1);
    }

    public function test_forged_lesson_metadata_is_rejected_and_publication_filter_works(): void
    {
        $course = Course::factory()->create();
        $teacher = $course->teacher;
        $token = $this->token($teacher, ['lessons:write', 'lessons:read']);
        $url = '/courses/'.$course->id.'/lessons';
        $this->api('POST', $url, ['title' => 'Title', 'content' => 'Text', 'position' => 1, 'course_id' => $course->id, 'is_published' => true], $token)
            ->assertUnprocessable()->assertJsonValidationErrors(['position', 'course_id', 'is_published']);
        $this->assertDatabaseCount('lessons', 0);
        Lesson::factory()->create(['course_id' => $course->id]);
        Lesson::factory()->published()->count(2)->create(['course_id' => $course->id]);

        $this->api('GET', $url.'?status=published&per_page=1', [], $token)->assertOk()->assertJsonPath('meta.total', 2)->assertJsonCount(1, 'data');
        $this->api('GET', $url.'?status=draft', [], $token)->assertOk()->assertJsonCount(1, 'data');
    }
}
