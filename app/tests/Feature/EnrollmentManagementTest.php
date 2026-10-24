<?php

namespace Tests\Feature;

use App\Actions\Academy\EnrollmentActions;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonCompletion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('m3')]
class EnrollmentManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_student_self_enrolls_and_duplicate_requests_reuse_one_record(): void
    {
        $course = Course::factory()->published()->create();
        $student = User::factory()->create();
        $this->actingAs($student)->post("/courses/$course->id/enrollments")->assertSessionHasNoErrors()->assertRedirectToRoute('courses.show', $course);
        $enrollment = $course->enrollments()->firstOrFail();
        $this->assertSame($student->id, $enrollment->student_id);
        $this->post("/courses/$course->id/enrollments")->assertRedirect();
        $this->assertSame(1, $course->enrollments()->count());
        $this->assertSame($enrollment->id, $course->enrollments()->first()->id);
        $this->get('/my-courses')->assertOk()->assertSee($course->title);
        $this->get('/student/dashboard')->assertOk()->assertViewHas('stats', fn ($stats) => $stats['enrollments'] === 1);
    }

    public function test_withdrawal_and_reenrollment_preserve_completion_history_and_original_join_date(): void
    {
        $this->freezeTime();
        $completion = LessonCompletion::factory()->create();
        $enrollment = $completion->enrollment;
        $course = $enrollment->course;
        $date = $enrollment->enrolled_at;
        $path = "/courses/$course->id/enrollments/$enrollment->id";
        $this->actingAs($enrollment->student)->delete($path)->assertRedirect();
        $withdrawn = $enrollment->fresh()->withdrawn_at;
        $this->assertNotNull($withdrawn);
        $this->travel(1)->day();
        $this->delete($path)->assertRedirect();
        $this->assertTrue($withdrawn->equalTo($enrollment->fresh()->withdrawn_at));
        $this->get("/courses/$course->id/lessons/$completion->lesson_id")->assertForbidden();
        $this->get('/my-courses?status=withdrawn')->assertOk()->assertSee($course->title);
        $this->post("/courses/$course->id/enrollments")->assertRedirect();
        $this->assertNull($enrollment->fresh()->withdrawn_at);
        $this->assertTrue($date->equalTo($enrollment->fresh()->enrolled_at));
        $this->assertSame(1, $course->enrollments()->count());
        $this->assertModelExists($completion);
        $this->get("/courses/$course->id/lessons/$completion->lesson_id")->assertOk();
    }

    public static function managers(): array
    {
        return ['admin' => [UserRole::Admin], 'assigned teacher' => [UserRole::Teacher]];
    }

    #[DataProvider('managers')]
    public function test_staff_can_enroll_withdraw_and_reenroll_eligible_students(UserRole $role): void
    {
        $course = Course::factory()->published()->create();
        $actor = $role === UserRole::Teacher ? $course->teacher : User::factory()->admin()->create();
        $student = User::factory()->create();
        $this->actingAs($actor)->get("/courses/$course->id/enrollments")->assertOk();
        $this->post("/courses/$course->id/enrollments", ['student_id' => $student->id])->assertSessionHasNoErrors()->assertRedirectToRoute('courses.enrollments.index', $course);
        $enrollment = $course->enrollments()->firstOrFail();
        $this->delete("/courses/$course->id/enrollments/$enrollment->id")->assertRedirect();
        $this->assertNotNull($enrollment->fresh()->withdrawn_at);
        $this->post("/courses/$course->id/enrollments", ['student_id' => $student->id])->assertRedirect();
        $this->assertNull($enrollment->fresh()->withdrawn_at);
        $this->assertSame(1, $course->enrollments()->count());
    }

    public static function invalidStudents(): array
    {
        return ['teacher' => [UserRole::Teacher, true], 'admin' => [UserRole::Admin, true], 'inactive student' => [UserRole::Student, false]];
    }

    #[DataProvider('invalidStudents')]
    public function test_managers_cannot_enroll_nonstudents_or_inactive_students(UserRole $role, bool $active): void
    {
        $course = Course::factory()->published()->create();
        $invalid = User::factory()->create(['role' => $role, 'is_active' => $active]);
        $this->actingAs($course->teacher)->postJson("/courses/$course->id/enrollments", ['student_id' => $invalid->id])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertSame(0, $course->enrollments()->count());
    }

    public function test_manager_missing_and_forged_student_ids_are_validation_errors(): void
    {
        $course = Course::factory()->published()->create();
        $this->actingAs($course->teacher)->postJson("/courses/$course->id/enrollments", [])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->postJson("/courses/$course->id/enrollments", ['student_id' => PHP_INT_MAX])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->postJson("/courses/$course->id/enrollments", ['student_id' => ['invalid']])->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $this->assertSame(0, $course->enrollments()->count());
    }

    public function test_students_cannot_supply_identity_or_privileged_fields(): void
    {
        $course = Course::factory()->published()->create();
        $student = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($student);
        foreach (['student_id' => $other->id, 'role' => 'admin', 'course_id' => $course->id, 'withdrawn_at' => now()->toDateTimeString(), 'user_id' => $other->id] as $key => $value) {
            $this->postJson("/courses/$course->id/enrollments", [$key => $value])->assertUnprocessable()->assertJsonValidationErrors($key);
        }
        $this->assertSame(0, $course->enrollments()->count());
        $this->assertSame(UserRole::Student, $student->fresh()->role);
    }

    public function test_other_students_and_unassigned_teachers_cannot_change_or_view_the_roster(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $other = User::factory()->teacher()->create();
        $this->actingAs($other)->get("/courses/$course->id/enrollments")->assertForbidden();
        $this->post("/courses/$course->id/enrollments", ['student_id' => $enrollment->student_id])->assertForbidden();
        $this->delete("/courses/$course->id/enrollments/$enrollment->id")->assertForbidden();
        $this->actingAs(User::factory()->create())->get("/courses/$course->id/enrollments")->assertForbidden();
        $this->delete("/courses/$course->id/enrollments/$enrollment->id")->assertForbidden();
        $this->get('/my-courses?status=all')->assertOk()->assertDontSee($course->title);
        $this->assertNull($enrollment->fresh()->withdrawn_at);
    }

    public function test_nested_enrollment_binding_prevents_cross_course_withdrawal_even_for_admins(): void
    {
        $enrollment = Enrollment::factory()->create();
        $otherCourse = Course::factory()->create();
        $this->actingAs(User::factory()->admin()->create())->delete("/courses/$otherCourse->id/enrollments/$enrollment->id")->assertNotFound();
        $this->assertNull($enrollment->fresh()->withdrawn_at);
    }

    public function test_unpublished_scheduled_and_archived_courses_cannot_receive_enrollments(): void
    {
        $this->freezeTime();
        $draft = Course::factory()->create();
        $scheduled = Course::factory()->create(['published_at' => now()->addDay()]);
        $archived = Course::factory()->published()->create();
        $archived->delete();
        $student = User::factory()->create();
        $this->actingAs($student)->post("/courses/$draft->id/enrollments")->assertForbidden();
        $this->post("/courses/$scheduled->id/enrollments")->assertForbidden();
        $this->post("/courses/$archived->id/enrollments")->assertNotFound();
        $this->actingAs($draft->teacher)->post("/courses/$draft->id/enrollments", ['student_id' => $student->id])->assertForbidden();
        $this->assertSame(0, Enrollment::count());
    }

    public function test_withdrawal_is_allowed_when_a_course_becomes_unpublished(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $course->published_at = null;
        $course->save();
        $this->actingAs($enrollment->student)->get('/my-courses')->assertOk()->assertSee('currently unpublished');
        $this->delete("/courses/$course->id/enrollments/$enrollment->id")->assertRedirect();
        $this->assertNotNull($enrollment->fresh()->withdrawn_at);
        $this->post("/courses/$course->id/enrollments")->assertForbidden();
    }

    public function test_roster_filters_pagination_and_overview_use_real_scoped_records(): void
    {
        $course = Course::factory()->published()->create();
        Enrollment::factory()->count(16)->create(['course_id' => $course->id]);
        $withdrawn = Enrollment::factory()->withdrawn()->create(['course_id' => $course->id]);
        $foreign = Enrollment::factory()->create();
        $this->actingAs($course->teacher)->get("/courses/$course->id/enrollments")->assertOk()->assertViewHas('enrollments', fn ($rows) => $rows->total() === 16 && $rows->count() === 15)->assertViewHas('enrollments', fn ($rows) => ! $rows->contains('id', $foreign->id));
        $this->get("/courses/$course->id/enrollments?status=withdrawn&q=".urlencode($withdrawn->student->email))->assertOk()->assertSee($withdrawn->student->email)->assertViewHas('counts', fn ($counts) => $counts === ['active' => 16, 'withdrawn' => 1]);
        $this->get('/admin/enrollments')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get("/admin/enrollments?course_id=$course->id&status=all")->assertOk()->assertViewHas('enrollments', fn ($rows) => $rows->total() === 17);
        $this->get('/my-courses')->assertForbidden();
    }

    public function test_archived_course_history_is_visible_only_in_its_students_course_list(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        $course->delete();
        $this->actingAs($enrollment->student)->get('/my-courses')->assertOk()->assertSee($course->title)->assertSee('This course is archived.')->assertDontSee('Withdraw</button>', false);
        $this->delete("/courses/$course->id/enrollments/$enrollment->id")->assertNotFound();
    }

    public function test_actions_recheck_assignment_after_course_was_loaded(): void
    {
        $course = Course::factory()->published()->create();
        $staleTeacher = $course->teacher;
        $student = User::factory()->create();
        Course::whereKey($course->id)->update(['teacher_id' => User::factory()->teacher()->create()->id]);
        $this->expectException(AuthorizationException::class);
        app(EnrollmentActions::class)->enroll($staleTeacher, $course, $student->id);
    }

    public function test_guests_cannot_enroll_withdraw_or_open_new_workspaces(): void
    {
        $enrollment = Enrollment::factory()->create();
        $course = $enrollment->course;
        foreach (['/my-courses', '/admin/enrollments', "/courses/$course->id/enrollments", "/courses/$course->id/lessons/create"] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->post("/courses/$course->id/enrollments")->assertRedirect('/login');
        $this->delete("/courses/$course->id/enrollments/$enrollment->id")->assertRedirect('/login');
        $this->assertNull($enrollment->fresh()->withdrawn_at);
    }

    public function test_new_workflow_writes_enforce_csrf_and_throttling(): void
    {
        $course = Course::factory()->published()->create();
        $student = User::factory()->create();
        $this->actingAs($student);
        $this->app->instance('env', 'local');
        $this->post("/courses/$course->id/enrollments")->assertStatus(419);
        $this->app->instance('env', 'testing');
        $this->assertSame(0, $course->enrollments()->count());
        for ($i = 0; $i < 60; $i++) {
            $this->post("/courses/$course->id/enrollments")->assertRedirect();
        }
        $this->postJson("/courses/$course->id/enrollments")->assertTooManyRequests();
        $this->assertSame(1, $course->enrollments()->count());
    }
}
