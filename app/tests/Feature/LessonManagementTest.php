<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('m3')]
class LessonManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_teacher_creates_edits_and_publishes_a_lesson_with_server_assigned_position(): void
    {
        $course = Course::factory()->create();
        $this->actingAs($course->teacher)->get("/courses/$course->id/lessons/create")->assertOk();
        $this->post("/courses/$course->id/lessons", ['title' => 'First steps', 'content' => "A useful introduction.\nA second paragraph."])->assertSessionHasNoErrors()->assertRedirect();
        $lesson = $course->lessons()->firstOrFail();
        $this->assertSame(1, $lesson->position);
        $this->assertFalse($lesson->is_published);
        $this->assertSame(1, $course->fresh()->lesson_order_version);
        $this->get("/courses/$course->id/lessons/$lesson->id/edit")->assertOk();
        $this->put("/courses/$course->id/lessons/$lesson->id", ['title' => 'A clearer title', 'content' => 'Revised content'])->assertRedirect();
        $this->assertDatabaseHas('lessons', ['id' => $lesson->id, 'content' => 'Revised content']);
        $this->put("/courses/$course->id/lessons/$lesson->id/publication", ['is_published' => 1])->assertRedirect();
        $this->assertTrue($lesson->fresh()->is_published);
        $this->put("/courses/$course->id/lessons/$lesson->id/publication", ['is_published' => 0])->assertRedirect();
        $this->assertFalse($lesson->fresh()->is_published);
    }

    public function test_admin_can_manage_lessons_in_any_course(): void
    {
        $course = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->post("/courses/$course->id/lessons", ['title' => 'Admin lesson', 'content' => 'Content'])->assertRedirect();
        $lesson = $course->lessons()->firstOrFail();
        $this->get("/courses/$course->id/lessons/$lesson->id")->assertOk()->assertSee('Content');
        $this->delete("/courses/$course->id/lessons/$lesson->id")->assertRedirect();
        $this->assertSoftDeleted($lesson);
    }

    public function test_students_and_other_teachers_cannot_write_lessons(): void
    {
        $course = Course::factory()->published()->create();
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id]);
        foreach ([User::factory()->create(), User::factory()->teacher()->create()] as $actor) {
            $this->actingAs($actor);
            $this->get("/courses/$course->id/lessons/create")->assertForbidden();
            $this->post("/courses/$course->id/lessons", ['title' => 'Forged', 'content' => 'Forged'])->assertForbidden();
            $this->get("/courses/$course->id/lessons/$lesson->id/edit")->assertForbidden();
            $this->put("/courses/$course->id/lessons/$lesson->id", ['title' => 'Forged', 'content' => 'Forged'])->assertForbidden();
            $this->put("/courses/$course->id/lessons/$lesson->id/publication", ['is_published' => 0])->assertForbidden();
            $this->delete("/courses/$course->id/lessons/$lesson->id")->assertForbidden();
            $this->get("/courses/$course->id/lessons/order")->assertForbidden();
            $this->put("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => [$lesson->id]])->assertForbidden();
        }
        $this->assertNotSoftDeleted($lesson);
        $this->assertTrue($lesson->fresh()->is_published);
    }

    public function test_cross_course_lesson_routes_return_404_even_for_administrators(): void
    {
        $course = Course::factory()->create();
        $foreign = Lesson::factory()->create();
        $path = "/courses/$course->id/lessons/$foreign->id";
        $this->actingAs(User::factory()->admin()->create())->get($path)->assertNotFound();
        $this->get("$path/edit")->assertNotFound();
        $this->put($path, ['title' => 'Move', 'content' => 'Changed'])->assertNotFound();
        $this->put("$path/publication", ['is_published' => 1])->assertNotFound();
        $this->delete($path)->assertNotFound();
        $this->assertSame($foreign->course_id, $foreign->fresh()->course_id);
    }

    public function test_student_read_access_requires_active_enrollment_and_both_publication_flags(): void
    {
        $course = Course::factory()->published()->create();
        $student = User::factory()->create();
        $lesson = Lesson::factory()->published()->create(['course_id' => $course->id, 'content' => 'Authorized lesson body']);
        $path = "/courses/$course->id/lessons/$lesson->id";
        $this->actingAs($student)->get($path)->assertForbidden();
        $enrollment = Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id]);
        $this->get($path)->assertOk()->assertSee('Authorized lesson body');
        $lesson->update(['is_published' => false]);
        $this->get($path)->assertForbidden();
        $lesson->update(['is_published' => true]);
        $course->published_at = null;
        $course->save();
        $this->get($path)->assertForbidden();
        $course->published_at = now();
        $course->save();
        $enrollment->update(['withdrawn_at' => now()]);
        $this->get($path)->assertForbidden();
    }

    public function test_soft_delete_keeps_completions_and_reserved_positions_but_removes_lesson_access(): void
    {
        $completion = LessonCompletion::factory()->create();
        $course = $completion->course;
        $lesson = $completion->lesson;
        $lesson->position = 1;
        $lesson->save();
        $this->actingAs($course->teacher)->delete("/courses/$course->id/lessons/$lesson->id")->assertRedirect();
        $this->assertSoftDeleted($lesson);
        $this->assertModelExists($completion);
        $this->assertModelExists($completion->enrollment);
        $this->get("/courses/$course->id/lessons/$lesson->id")->assertNotFound();
        $this->post("/courses/$course->id/lessons", ['title' => 'A later lesson', 'content' => 'New content'])->assertRedirect();
        $this->assertSame(2, $course->lessons()->firstOrFail()->position);
        $this->actingAs($completion->enrollment->student)->get("/courses/$course->id")->assertOk()->assertDontSee($lesson->title);
        $this->get('/student/dashboard')->assertOk()->assertViewHas('stats', fn ($stats) => $stats['completed_lessons'] === 0);
        $this->actingAs($course->teacher)->get('/teacher/dashboard')->assertOk()->assertViewHas('stats', fn ($stats) => $stats['lessons'] === 1);
    }

    public function test_reordering_swaps_unique_positions_without_touching_archived_lesson_history(): void
    {
        $course = Course::factory()->create();
        $archived = Lesson::factory()->create(['course_id' => $course->id, 'position' => 1]);
        $archived->delete();
        $first = Lesson::factory()->create(['course_id' => $course->id, 'position' => 2]);
        $second = Lesson::factory()->create(['course_id' => $course->id, 'position' => 3]);
        $this->actingAs($course->teacher)->get("/courses/$course->id/lessons/order")->assertOk()->assertSee('Save lesson order')->assertDontSee($archived->title);
        $this->put("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => [$second->id, $first->id]])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame([$second->id, $first->id], $course->lessons()->pluck('id')->all());
        $this->assertSame(1, $archived->fresh()->position);
        $this->assertSame(2, $second->fresh()->position);
        $this->assertSame(3, $first->fresh()->position);
        $this->assertSame(1, $course->fresh()->lesson_order_version);
    }

    public function test_stale_order_returns_conflict_and_preserves_the_newer_order(): void
    {
        $course = Course::factory()->create();
        $lessons = Lesson::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->create(['course_id' => $course->id]);
        $ids = $lessons->modelKeys();
        $this->actingAs($course->teacher)->put("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => array_reverse($ids)])->assertRedirect();
        $this->putJson("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => $ids])->assertConflict()->assertJsonPath('message', 'The lesson list changed. Reload the order page and try again.');
        $this->assertSame(array_reverse($ids), $course->lessons()->pluck('id')->all());
        $this->put("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => $ids])->assertConflict()->assertSee('Reload lesson order');
    }

    public function test_reorder_rejects_duplicates_missing_ids_and_foreign_lessons_without_writes(): void
    {
        $course = Course::factory()->create();
        $lessons = Lesson::factory()->count(2)->sequence(['position' => 1], ['position' => 2])->create(['course_id' => $course->id]);
        $foreign = Lesson::factory()->create();
        $ids = $lessons->modelKeys();
        $this->actingAs($course->teacher);
        foreach ([[$ids[0], $ids[0]], [$ids[0]], [$ids[0], $foreign->id], [$ids[0], PHP_INT_MAX]] as $invalid) {
            $this->putJson("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => $invalid])->assertUnprocessable();
        }
        $this->putJson("/courses/$course->id/lessons/order", ['lesson_ids' => $ids])->assertUnprocessable()->assertJsonValidationErrors('order_version');
        $this->assertSame($ids, $course->lessons()->pluck('id')->all());
        $this->assertSame(0, $course->fresh()->lesson_order_version);
    }

    public function test_adding_and_archiving_lessons_invalidates_open_order_forms(): void
    {
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->create(['course_id' => $course->id, 'position' => 1]);
        $this->actingAs($course->teacher)->post("/courses/$course->id/lessons", ['title' => 'New', 'content' => 'New'])->assertRedirect();
        $this->putJson("/courses/$course->id/lessons/order", ['order_version' => 0, 'lesson_ids' => [$lesson->id]])->assertConflict();
        $this->delete("/courses/$course->id/lessons/$lesson->id")->assertRedirect();
        $this->assertSame(2, $course->fresh()->lesson_order_version);
    }

    public function test_lesson_validation_and_protected_fields_prevent_invalid_writes(): void
    {
        $course = Course::factory()->create();
        $this->actingAs($course->teacher)->postJson("/courses/$course->id/lessons", [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);
        $this->postJson("/courses/$course->id/lessons", ['title' => ['array'], 'content' => str_repeat('x', 100001)])->assertUnprocessable()->assertJsonValidationErrors(['title', 'content']);
        foreach (['course_id', 'position', 'is_published', 'role', 'teacher_id', 'deleted_at'] as $field) {
            $this->postJson("/courses/$course->id/lessons", ['title' => 'Unsafe', 'content' => 'Body', $field => 1])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $this->assertSame(0, $course->lessons()->count());
    }

    public function test_lesson_content_is_escaped_and_student_navigation_skips_unpublished_lessons(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $first = Lesson::factory()->published()->create(['course_id' => $course->id, 'position' => 1, 'title' => '<script>Lesson</script>', 'content' => '<img src=x onerror=alert(1)>']);
        $draft = Lesson::factory()->create(['course_id' => $course->id, 'position' => 2]);
        $last = Lesson::factory()->published()->create(['course_id' => $course->id, 'position' => 3]);
        $this->actingAs($enrollment->student)->get("/courses/$course->id/lessons/$first->id")->assertOk()->assertSee('&lt;script&gt;Lesson&lt;/script&gt;', false)->assertSee('&lt;img', false)->assertDontSee('<img src=x onerror=alert(1)>', false)->assertSee($last->title)->assertDontSee($draft->title);
    }

    public function test_position_exhaustion_is_a_validation_error_without_partial_writes(): void
    {
        $course = Course::factory()->create();
        Lesson::factory()->create(['course_id' => $course->id, 'position' => 4294967295]);
        $this->actingAs($course->teacher)->postJson("/courses/$course->id/lessons", ['title' => 'Too far', 'content' => 'Content'])->assertUnprocessable()->assertJsonValidationErrors('lesson_ids');
        $this->assertSame(1, $course->lessons()->count());
        $this->assertSame(0, $course->fresh()->lesson_order_version);
    }
}
