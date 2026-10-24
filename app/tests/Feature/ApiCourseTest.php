<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiCourseTest extends ApiTestCase
{
    public function test_teacher_creates_edits_publishes_and_unpublishes_own_course(): void
    {
        $teacher = User::factory()->teacher()->create();
        $token = $this->token($teacher, ['courses:write', 'courses:read']);
        $response = $this->api('POST', '/courses', ['title' => 'Design foundations', 'slug' => 'Design-Foundations'], $token)
            ->assertCreated()->assertJsonPath('data.teacher.id', $teacher->id)->assertJsonPath('data.is_published', false);
        $id = $response->json('data.id');
        $this->assertDatabaseHas('courses', ['id' => $id, 'slug' => 'design-foundations', 'teacher_id' => $teacher->id, 'published_at' => null]);

        $this->api('PATCH', '/courses/'.$id, ['title' => 'Design principles'], $token)->assertOk()->assertJsonPath('data.title', 'Design principles');
        $this->api('PUT', '/courses/'.$id.'/publication', ['published' => true], $token)->assertOk()->assertJsonPath('data.is_published', true);
        $publishedAt = Course::findOrFail($id)->published_at;
        $this->api('PUT', '/courses/'.$id.'/publication', ['published' => true], $token)->assertOk();
        $this->assertTrue($publishedAt->equalTo(Course::findOrFail($id)->published_at));
        $this->api('PUT', '/courses/'.$id.'/publication', ['published' => false], $token)->assertOk()->assertJsonPath('data.is_published', false);
        $this->assertDatabaseHas('courses', ['id' => $id, 'title' => 'Design principles', 'published_at' => null]);
    }

    public function test_admin_reassigns_eligible_teachers_and_archives_without_removing_history(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id]);
        $enrollment = Enrollment::factory()->create(['course_id' => $course->id]);
        $token = $this->token($admin, ['courses:write', 'courses:read']);

        $this->api('PATCH', '/courses/'.$course->id, ['teacher_id' => $teacher->id], $token)->assertOk()->assertJsonPath('data.teacher.id', $teacher->id);
        $this->api('DELETE', '/courses/'.$course->id, [], $token)->assertNoContent();
        $this->assertSoftDeleted($course);
        $this->assertModelExists($lesson);
        $this->assertModelExists($enrollment);
        $this->api('GET', '/courses/'.$course->id, [], $token)->assertNotFound();
        $this->api('GET', '/courses', [], $token)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_teachers_cannot_access_other_courses_reassign_teachers_or_archive(): void
    {
        $teacher = User::factory()->teacher()->create();
        $own = Course::factory()->create(['teacher_id' => $teacher->id]);
        $other = Course::factory()->create();
        $token = $this->token($teacher, ['courses:read', 'courses:write']);

        $this->api('GET', '/courses/'.$other->id, [], $token)->assertForbidden();
        $this->api('PATCH', '/courses/'.$other->id, ['title' => 'Denied'], $token)->assertForbidden();
        $this->api('PUT', '/courses/'.$other->id.'/publication', ['published' => true], $token)->assertForbidden();
        $this->api('PATCH', '/courses/'.$own->id, ['teacher_id' => $other->teacher_id], $token)->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->api('DELETE', '/courses/'.$own->id, [], $token)->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $other->id, 'title' => $other->title]);
        $this->assertNotSoftDeleted($own);
    }

    #[TestWith(['POST'])]
    #[TestWith(['PATCH'])]
    #[TestWith(['DELETE'])]
    public function test_student_cannot_mutate_courses_even_with_a_privileged_token(string $method): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();
        $token = $this->token($student, ['courses:write']);

        $this->api($method, '/courses'.($method === 'POST' ? '' : '/'.$course->id), ['title' => 'Denied', 'slug' => 'denied'], $token)->assertForbidden();
        $this->assertDatabaseCount('courses', 1);
        $this->assertNotSoftDeleted($course);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => $course->title]);
    }

    public function test_catalog_paginates_filters_and_scopes_without_exposing_account_fields(): void
    {
        $student = User::factory()->create();
        $published = Course::factory()->published()->count(3)->sequence(['title' => 'Alpha'], ['title' => 'Beta'], ['title' => 'Gamma'])->create();
        Course::factory()->create();
        $archived = Course::factory()->published()->create();
        $archived->delete();
        Lesson::factory()->create(['course_id' => $published[0]->id]);
        Lesson::factory()->published()->create(['course_id' => $published[0]->id]);
        $token = $this->token($student, ['courses:read']);

        $page = $this->api('GET', '/courses?sort=title&per_page=2', [], $token)->assertOk()
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.last_page', 2)->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.lessons_count', 1)->assertJsonPath('data.0.title', 'Alpha');
        $this->assertSame(['id', 'name'], array_keys($page->json('data.0.teacher')));
        $page->assertJsonMissingPath('data.0.lesson_order_version')->assertJsonMissingPath('data.0.teacher.email');
        $this->api('GET', '/courses?q=Beta', [], $token)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $published[1]->id);
        $this->api('GET', '/courses?sort=title&per_page=2&page=2', [], $token)->assertOk()->assertJsonPath('data.0.title', 'Gamma');
        $this->api('GET', '/courses?status=draft', [], $token)->assertUnprocessable();
    }

    public function test_staff_catalog_filters_teacher_and_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $draft = Course::factory()->create(['teacher_id' => $teacher->id]);
        Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        Course::factory()->published()->create();

        $this->api('GET', '/courses?status=draft&teacher_id='.$teacher->id, [], $this->token($admin, ['courses:read']))
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $draft->id);
        $this->api('GET', '/courses', [], $this->token($teacher, ['courses:read']))->assertOk()->assertJsonPath('meta.total', 2);
    }

    #[TestWith(['role', 'admin'])]
    #[TestWith(['position', 1])]
    #[TestWith(['published_at', '2026-01-01'])]
    #[TestWith(['unexpected', true])]
    #[TestWith(['slug', '../../invalid'])]
    #[TestWith(['title', ''])]
    public function test_invalid_course_payload_returns_422_without_persistence(string $field, mixed $value): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->api('POST', '/courses', array_replace(['title' => 'Valid', 'slug' => 'valid'], [$field => $value]), $this->token($teacher, ['courses:write']))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_admin_rejects_non_teacher_and_disabled_teacher_assignments(): void
    {
        $admin = User::factory()->admin()->create();
        $student = User::factory()->create();
        $teacher = User::factory()->teacher()->inactive()->create();
        $token = $this->token($admin, ['courses:write']);
        foreach ([$student->id, $teacher->id] as $id) {
            $this->api('POST', '/courses', ['title' => 'Valid', 'slug' => 'valid', 'teacher_id' => $id], $token)
                ->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        }
        $this->assertDatabaseCount('courses', 0);
    }

    public function test_duplicate_and_archived_slugs_are_reserved_and_teacher_is_required_for_publication(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->token($admin, ['courses:write']);
        $course = Course::factory()->create(['teacher_id' => null]);

        $this->api('PUT', '/courses/'.$course->id.'/publication', ['published' => true], $token)->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->api('POST', '/courses', ['title' => 'Duplicate', 'slug' => $course->slug], $token)->assertUnprocessable()->assertJsonValidationErrors('slug');
        $course->delete();
        $this->api('POST', '/courses', ['title' => 'Duplicate', 'slug' => $course->slug], $token)->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertDatabaseCount('courses', 1);
    }
}
