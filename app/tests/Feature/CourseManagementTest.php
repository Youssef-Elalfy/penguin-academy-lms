<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('m3')]
class CourseManagementTest extends TestCase
{
    use DatabaseTransactions;

    private function payload(array $overrides = []): array
    {
        return array_replace(['title' => 'Web foundations', 'slug' => 'web-foundations', 'summary' => 'Build strong foundations.', 'description' => 'Start with accessible HTML.'], $overrides);
    }

    public function test_admin_creates_edits_and_publishes_a_persisted_course(): void
    {
        $admin = User::factory()->admin()->create();
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($admin)->get('/courses/create')->assertOk();
        $this->post('/courses', $this->payload(['teacher_id' => $teacher->id]))->assertSessionHasNoErrors()->assertRedirect();
        $course = Course::where('slug', 'web-foundations')->firstOrFail();
        $this->assertSame($teacher->id, $course->teacher_id);
        $this->assertNull($course->published_at);
        $this->get("/courses/$course->id/edit")->assertOk();
        $this->put("/courses/$course->id", $this->payload(['title' => 'Accessible web foundations', 'teacher_id' => $teacher->id]))->assertRedirectToRoute('courses.show', $course);
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Accessible web foundations']);
        $this->put("/courses/$course->id/publication", ['published' => 1])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($course->fresh()->isPublished());
        $publishedAt = $course->fresh()->published_at;
        $this->put("/courses/$course->id/publication", ['published' => 1])->assertRedirect();
        $this->assertTrue($publishedAt->equalTo($course->fresh()->published_at));
        $this->put("/courses/$course->id/publication", ['published' => 0])->assertRedirect();
        $this->assertNull($course->fresh()->published_at);
    }

    public function test_teacher_course_creation_assigns_signed_in_teacher_and_permits_own_editing(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->actingAs($teacher)->post('/courses', $this->payload())->assertRedirect();
        $course = Course::where('slug', 'web-foundations')->firstOrFail();
        $this->assertSame($teacher->id, $course->teacher_id);
        $this->put("/courses/$course->id", $this->payload(['summary' => 'Updated summary']))->assertRedirect();
        $this->put("/courses/$course->id/publication", ['published' => 1])->assertRedirect();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'summary' => 'Updated summary']);
        $this->assertTrue($course->fresh()->isPublished());
        $this->delete("/courses/$course->id")->assertForbidden();
        $this->assertNotSoftDeleted($course);
    }

    public function test_teacher_cannot_forge_assignment_on_create_or_update(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $course = Course::factory()->create(['teacher_id' => $teacher->id]);
        $this->actingAs($teacher)->postJson('/courses', $this->payload(['teacher_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->putJson("/courses/$course->id", $this->payload(['teacher_id' => $other->id]))->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->putJson("/courses/$course->id/teacher", ['teacher_id' => $other->id])->assertForbidden();
        $this->assertSame($teacher->id, $course->fresh()->teacher_id);
        $this->assertDatabaseMissing('courses', ['slug' => 'web-foundations']);
    }

    public function test_students_cannot_create_or_modify_courses_and_guests_are_redirected(): void
    {
        $course = Course::factory()->published()->create();
        $this->get('/courses')->assertRedirect('/login');
        $this->post('/courses', $this->payload())->assertRedirect('/login');
        $student = User::factory()->create();
        $this->actingAs($student)->get('/courses/create')->assertForbidden();
        $this->post('/courses', $this->payload())->assertForbidden();
        $this->get("/courses/$course->id/edit")->assertForbidden();
        $this->put("/courses/$course->id", $this->payload())->assertForbidden();
        $this->put("/courses/$course->id/publication", ['published' => 0])->assertForbidden();
        $this->put("/courses/$course->id/teacher", ['teacher_id' => $student->id])->assertForbidden();
        $this->delete("/courses/$course->id")->assertForbidden();
        $this->assertTrue($course->fresh()->isPublished());
    }

    public function test_teacher_cannot_access_or_change_another_teachers_course(): void
    {
        $course = Course::factory()->published()->create(['title' => 'Private teaching workspace']);
        $other = User::factory()->teacher()->create();
        $this->actingAs($other)->get('/courses')->assertOk()->assertDontSee('Private teaching workspace');
        $this->get("/courses/$course->id")->assertForbidden();
        $this->get("/courses/$course->id/edit")->assertForbidden();
        $this->put("/courses/$course->id", $this->payload())->assertForbidden();
        $this->put("/courses/$course->id/publication", ['published' => 0])->assertForbidden();
        $this->delete("/courses/$course->id")->assertForbidden();
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Private teaching workspace']);
    }

    public function test_admin_reassignment_immediately_removes_previous_teacher_access(): void
    {
        $course = Course::factory()->create();
        $old = $course->teacher;
        $new = User::factory()->teacher()->create();
        $this->actingAs(User::factory()->admin()->create())->put("/courses/$course->id/teacher", ['teacher_id' => $new->id])->assertRedirect();
        $this->assertSame($new->id, $course->fresh()->teacher_id);
        $this->actingAs($old)->get("/courses/$course->id")->assertForbidden();
        $this->actingAs($new)->get("/courses/$course->id")->assertOk();
    }

    public static function invalidTeachers(): array
    {
        return ['student' => [UserRole::Student, true], 'administrator' => [UserRole::Admin, true], 'inactive teacher' => [UserRole::Teacher, false]];
    }

    #[DataProvider('invalidTeachers')]
    public function test_course_writes_reject_ineligible_teacher_accounts(UserRole $role, bool $active): void
    {
        $invalid = User::factory()->create(['role' => $role, 'is_active' => $active]);
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->postJson('/courses', $this->payload(['teacher_id' => $invalid->id]))->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->putJson("/courses/$course->id", $this->payload(['teacher_id' => $invalid->id]))->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->putJson("/courses/$course->id/teacher", ['teacher_id' => $invalid->id])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->assertSame($course->teacher_id, $course->fresh()->teacher_id);
    }

    public function test_missing_teacher_and_invalid_publication_are_validation_errors(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create());
        $this->putJson("/courses/$course->id/teacher", ['teacher_id' => PHP_INT_MAX])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->putJson("/courses/$course->id/publication", ['published' => 'sometimes'])->assertUnprocessable()->assertJsonValidationErrors('published');
        $this->assertNull($course->fresh()->published_at);
    }

    public function test_publishing_requires_an_active_teacher_and_removal_requires_unpublishing(): void
    {
        $course = Course::factory()->create(['teacher_id' => null]);
        $this->actingAs(User::factory()->admin()->create())->putJson("/courses/$course->id/publication", ['published' => 1])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $teacher = User::factory()->teacher()->inactive()->create();
        $course->teacher_id = $teacher->id;
        $course->save();
        $this->putJson("/courses/$course->id/publication", ['published' => 1])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $teacher->is_active = true;
        $teacher->save();
        $this->put("/courses/$course->id/publication", ['published' => 1])->assertRedirect();
        $this->putJson("/courses/$course->id/teacher", ['teacher_id' => null])->assertUnprocessable()->assertJsonValidationErrors('teacher_id');
        $this->put("/courses/$course->id/publication", ['published' => 0])->assertRedirect();
        $this->put("/courses/$course->id/teacher", ['teacher_id' => null])->assertRedirect();
        $this->assertNull($course->fresh()->teacher_id);
    }

    public function test_archiving_preserves_learning_records_hides_routes_and_reserves_slug(): void
    {
        $completion = LessonCompletion::factory()->create();
        $course = $completion->course;
        $this->actingAs(User::factory()->admin()->create())->delete("/courses/$course->id")->assertRedirectToRoute('courses.index');
        $this->assertSoftDeleted($course);
        $this->assertModelExists($completion);
        $this->assertModelExists($completion->enrollment);
        $this->assertModelExists($completion->lesson);
        $this->get("/courses/$course->id")->assertNotFound();
        $this->get("/courses/$course->id/lessons/$completion->lesson_id")->assertNotFound();
        $this->postJson('/courses', $this->payload(['slug' => $course->slug]))->assertUnprocessable()->assertJsonValidationErrors('slug');
    }

    public static function protectedPayloads(): array
    {
        return ['role' => ['role', 'admin'], 'identity' => ['user_id', 3], 'primary key' => ['id', 99], 'publication' => ['published_at', '2026-10-01'], 'archive' => ['deleted_at', '2026-10-01'], 'ordering version' => ['lesson_order_version', 99]];
    }

    #[DataProvider('protectedPayloads')]
    public function test_protected_course_fields_are_rejected(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson('/courses', $this->payload([$field => $value]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseMissing('courses', ['slug' => 'web-foundations']);
    }

    public function test_course_validation_rejects_missing_malformed_duplicate_and_oversized_values(): void
    {
        $this->actingAs(User::factory()->admin()->create())->postJson('/courses', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'slug'])->assertJsonPath('errors.title.0', 'The title field is required.');
        $this->postJson('/courses', $this->payload(['slug' => 'invalid slug!', 'summary' => str_repeat('a', 501)]))->assertUnprocessable()->assertJsonValidationErrors(['slug', 'summary']);
        $this->post('/courses', $this->payload())->assertRedirect();
        $this->postJson('/courses', $this->payload())->assertUnprocessable()->assertJsonValidationErrors('slug');
        $this->assertSame(1, Course::where('slug', 'web-foundations')->count());
    }

    public function test_catalog_hides_drafts_scheduled_and_archived_courses_and_scopes_pagination(): void
    {
        $this->freezeTime();
        $teacher = User::factory()->teacher()->create();
        Course::factory()->count(13)->published()->create(['teacher_id' => $teacher->id]);
        $draft = Course::factory()->create(['title' => 'Secret draft']);
        Course::factory()->create(['published_at' => now()->addDay(), 'title' => 'Future course']);
        $archived = Course::factory()->published()->create(['title' => 'Archived course']);
        $archived->delete();
        $this->actingAs(User::factory()->create())->get('/courses')->assertOk()->assertViewHas('courses', fn ($courses) => $courses->total() === 13 && $courses->count() === 12)->assertDontSee('Secret draft')->assertDontSee('Future course')->assertDontSee('Archived course');
        $this->get('/courses?page=2')->assertOk()->assertViewHas('courses', fn ($courses) => $courses->count() === 1);
        $this->get("/courses/$draft->id")->assertForbidden();
        $this->getJson('/courses?status=draft')->assertUnprocessable();
        $this->getJson('/courses?sort=title%3Bdrop%20table%20users')->assertUnprocessable();
    }

    public function test_admin_filters_and_course_text_are_escaped(): void
    {
        $course = Course::factory()->published()->create(['title' => '<script>title</script>', 'summary' => '<img src=x onerror=alert(1)>', 'description' => '<script>body</script>']);
        Course::factory()->create(['title' => 'Unrelated course']);
        $this->actingAs(User::factory()->admin()->create())->get('/courses?status=published&teacher_id='.$course->teacher_id.'&q=title&sort=title')->assertOk()->assertSee('&lt;script&gt;title&lt;/script&gt;', false)->assertDontSee('<script>title</script>', false)->assertDontSee('Unrelated course');
        $this->get("/courses/$course->id")->assertOk()->assertSee('&lt;script&gt;body&lt;/script&gt;', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
