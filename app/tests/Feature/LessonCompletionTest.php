<?php

namespace Tests\Feature;

use App\Actions\Academy\CompletionActions;
use App\Actions\Academy\EnrollmentActions;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonCompletion;
use App\Models\User;
use App\Services\Learning\ProgressService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[Group('m4')]
class LessonCompletionTest extends TestCase
{
    use DatabaseTransactions;

    private function lesson(): array
    {
        $enrollment = Enrollment::factory()->create();
        $lesson = Lesson::factory()->published()->create(['course_id' => $enrollment->course_id]);

        return [$enrollment, $lesson, "/courses/{$enrollment->course_id}/lessons/{$lesson->id}/completion"];
    }

    public function test_student_completion_persists_and_updates_the_reader_after_refresh(): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $this->actingAs($enrollment->student)->put($url)->assertRedirectToRoute('courses.lessons.show', [$enrollment->course_id, $lesson->id])->assertSessionHas('status');
        $this->assertDatabaseHas('lesson_completions', ['enrollment_id' => $enrollment->id, 'course_id' => $enrollment->course_id, 'lesson_id' => $lesson->id]);
        $reader = "/courses/{$enrollment->course_id}/lessons/{$lesson->id}";
        $this->get($reader)->assertOk()->assertSeeText('Undo completion')->assertViewHas('completed', true)
            ->assertViewHas('progress', fn ($p) => $p->percent === 100.0);
        $this->get($reader)->assertOk()->assertViewHas('completed', true);
        $this->get("/courses/{$enrollment->course_id}")->assertOk()->assertSeeText('Completed')->assertSee('value="100"', false);
    }

    public function test_duplicate_completion_preserves_the_same_record_and_timestamp(): void
    {
        $this->freezeTime();
        [$enrollment, $lesson, $url] = $this->lesson();
        $this->actingAs($enrollment->student)->put($url)->assertRedirect();
        $original = $enrollment->completions()->sole();
        $this->travel(5)->minutes();

        $this->put($url)->assertRedirect();

        $this->assertSame($original->id, $enrollment->completions()->sole()->id);
        $this->assertSame($original->completed_at->toDateTimeString(), $original->fresh()->completed_at->toDateTimeString());
        $this->assertSame($original->updated_at->toDateTimeString(), $original->fresh()->updated_at->toDateTimeString());
        $this->assertSame(1, $enrollment->completions()->count());
    }

    public function test_undo_is_idempotent_and_never_removes_another_students_completion(): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $other = Enrollment::factory()->create(['course_id' => $enrollment->course_id]);
        $own = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        $foreign = LessonCompletion::factory()->create(['enrollment_id' => $other->id, 'lesson_id' => $lesson->id]);

        $this->actingAs($enrollment->student)->delete($url)->assertRedirect()->assertSessionHas('status');
        $this->delete($url)->assertRedirect();

        $this->assertModelMissing($own);
        $this->assertModelExists($foreign);
        $this->get("/courses/{$enrollment->course_id}/lessons/{$lesson->id}")->assertOk()->assertSeeText('Mark complete')
            ->assertViewHas('progress', fn ($p) => $p->percent === 0.0);
    }

    public function test_guests_cannot_complete_or_undo(): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $this->put($url)->assertRedirect('/login');
        $this->delete($url)->assertRedirect('/login');
        $this->assertDatabaseCount('lesson_completions', 0);
    }

    #[TestWith(['admin'])]
    #[TestWith(['teacher'])]
    #[TestWith(['student'])]
    public function test_non_enrolled_actors_cannot_change_student_progress(string $role): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $actor = User::factory()->create(['role' => $role]);
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        $this->assertFalse(Gate::forUser($actor)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
        $this->assertFalse(Gate::forUser($actor)->allows('delete', $completion));

        $this->actingAs($actor)->put($url)->assertForbidden();
        $this->delete($url)->assertForbidden();

        $this->assertModelExists($completion);
        $this->assertDatabaseCount('lesson_completions', 1);
    }

    public function test_completion_policy_allows_only_the_active_enrolled_student(): void
    {
        [$enrollment, $lesson] = $this->lesson();
        $student = $enrollment->student;
        $this->assertTrue(Gate::forUser($student)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
        $student->is_active = false;
        $this->assertFalse(Gate::forUser($student)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
        $student->is_active = true;
        $enrollment->withdrawn_at = now();
        $this->assertFalse(Gate::forUser($student)->allows('create', [LessonCompletion::class, $enrollment, $lesson]));
    }

    #[TestWith(['student_id'])]
    #[TestWith(['enrollment_id'])]
    #[TestWith(['course_id'])]
    #[TestWith(['lesson_id'])]
    #[TestWith(['role'])]
    #[TestWith(['completed_at'])]
    #[TestWith(['completion_id'])]
    #[TestWith(['percentage'])]
    public function test_forged_progress_fields_are_rejected_without_writes(string $field): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $this->actingAs($enrollment->student)->putJson($url, [$field => '999'])->assertUnprocessable()
            ->assertJsonValidationErrors($field)->assertJsonPath("errors.$field.0", 'The '.str_replace('_', ' ', $field).' field must be missing.');
        $this->assertDatabaseCount('lesson_completions', 0);
        $this->assertSame('student', $enrollment->student->fresh()->role->value);
    }

    public function test_cross_course_lesson_is_rejected_before_any_completion_change(): void
    {
        [$enrollment, $lesson] = $this->lesson();
        $otherCourse = Course::factory()->published()->create();
        Enrollment::factory()->create(['course_id' => $otherCourse->id, 'student_id' => $enrollment->student_id]);
        $url = "/courses/{$otherCourse->id}/lessons/{$lesson->id}/completion";

        $this->actingAs($enrollment->student)->put($url)->assertNotFound();
        $this->delete($url)->assertNotFound();

        $this->assertDatabaseCount('lesson_completions', 0);
    }

    #[TestWith(['lesson_draft', 403])]
    #[TestWith(['lesson_archived', 404])]
    #[TestWith(['course_draft', 403])]
    #[TestWith(['course_scheduled', 403])]
    #[TestWith(['course_archived', 404])]
    public function test_inaccessible_curriculum_blocks_completion_and_undo_and_retains_history(string $state, int $status): void
    {
        $this->freezeTime();
        [$enrollment, $lesson, $url] = $this->lesson();
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id]);
        match ($state) {
            'lesson_draft' => $lesson->update(['is_published' => false]),
            'lesson_archived' => $lesson->delete(),
            'course_draft' => $enrollment->course->forceFill(['published_at' => null])->save(),
            'course_scheduled' => $enrollment->course->forceFill(['published_at' => now()->addDay()])->save(),
            'course_archived' => $enrollment->course->delete(),
        };

        $this->actingAs($enrollment->student)->put($url)->assertStatus($status);
        $this->delete($url)->assertStatus($status);

        $this->assertModelExists($completion);
        $this->assertDatabaseCount('lesson_completions', 1);
    }

    public function test_withdrawal_blocks_changes_and_reenrollment_restores_retained_progress(): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $student = $enrollment->student;
        $this->actingAs($student)->put($url)->assertRedirect();
        $completion = $enrollment->completions()->sole();
        app(EnrollmentActions::class)->withdraw($student, $enrollment->course, $enrollment);

        $this->put($url)->assertForbidden();
        $this->delete($url)->assertForbidden();
        $this->assertModelExists($completion);
        $this->get("/courses/{$enrollment->course_id}/lessons/{$lesson->id}")->assertForbidden();

        $this->post("/courses/{$enrollment->course_id}/enrollments")->assertRedirect();
        $this->assertSame($enrollment->id, $student->enrollments()->sole()->id);
        $this->assertSame(100.0, app(ProgressService::class)->forEnrollment($enrollment)->percent);
        $this->delete($url)->assertRedirect();
        $this->assertModelMissing($completion);
    }

    public function test_action_rechecks_a_stale_published_lesson_under_the_course_lock(): void
    {
        [$enrollment, $lesson] = $this->lesson();
        Lesson::whereKey($lesson->id)->update(['is_published' => false]);
        try {
            app(CompletionActions::class)->setCompleted($enrollment->student, $enrollment->course, $lesson, true);
            $this->fail('A stale Lesson instance must not authorize a completion.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('lesson_completions', 0);
        }
    }

    public function test_completion_writes_remain_rate_limited(): void
    {
        [$enrollment, $lesson, $url] = $this->lesson();
        $this->actingAs($enrollment->student);
        for ($i = 0; $i < 60; $i++) {
            $this->put($url)->assertRedirect();
        }
        $this->putJson($url)->assertTooManyRequests();
        $this->assertSame(1, $enrollment->completions()->count());
    }
}
