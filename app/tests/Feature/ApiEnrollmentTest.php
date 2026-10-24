<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonCompletion;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiEnrollmentTest extends ApiTestCase
{
    public function test_self_enrollment_withdrawal_and_reenrollment_preserve_history_idempotently(): void
    {
        $student = User::factory()->create();
        $course = Course::factory()->published()->create();
        $token = $this->token($student, ['enrollments:read', 'enrollments:write']);
        $response = $this->api('POST', '/enrollments', ['course_id' => $course->id], $token)->assertCreated();
        $id = $response->json('data.id');
        $enrollment = Enrollment::findOrFail($id);
        $joined = $enrollment->enrolled_at;
        $completion = LessonCompletion::factory()->create(['enrollment_id' => $id, 'course_id' => $course->id]);

        $this->api('POST', '/enrollments', ['course_id' => $course->id], $token)->assertOk()->assertJsonPath('data.id', $id);
        $this->api('DELETE', '/enrollments/'.$id, [], $token)->assertNoContent();
        $withdrawn = $enrollment->refresh()->withdrawn_at;
        $this->api('DELETE', '/enrollments/'.$id, [], $token)->assertNoContent();
        $this->assertTrue($withdrawn->equalTo($enrollment->refresh()->withdrawn_at));
        $this->assertModelExists($completion);
        $this->api('GET', '/enrollments?status=withdrawn', [], $token)->assertOk()->assertJsonPath('data.0.status', 'withdrawn');
        $this->api('POST', '/enrollments', ['course_id' => $course->id], $token)->assertOk()->assertJsonPath('data.id', $id)->assertJsonPath('data.progress.percent', 100);
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertDatabaseHas('enrollments', ['id' => $id, 'student_id' => $student->id, 'course_id' => $course->id, 'withdrawn_at' => null]);
        $this->assertTrue($joined->equalTo($enrollment->refresh()->enrolled_at));
    }

    #[TestWith(['admin'])]
    #[TestWith(['teacher'])]
    public function test_authorized_staff_enroll_and_withdraw_students(string $role): void
    {
        $user = $role === 'admin' ? User::factory()->admin()->create() : User::factory()->teacher()->create();
        $course = Course::factory()->published()->create($role === 'teacher' ? ['teacher_id' => $user->id] : []);
        $student = User::factory()->create();
        $token = $this->token($user, ['enrollments:write']);
        $id = $this->api('POST', '/enrollments', ['course_id' => $course->id, 'student_id' => $student->id], $token)
            ->assertCreated()->assertJsonPath('data.student_id', $student->id)->json('data.id');

        $this->api('DELETE', '/enrollments/'.$id, [], $token)->assertNoContent();
        $this->assertNotNull(Enrollment::findOrFail($id)->withdrawn_at);
    }

    public function test_forged_students_inactive_accounts_and_non_student_enrollments_are_rejected(): void
    {
        $student = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $disabled = User::factory()->inactive()->create();
        $course = Course::factory()->published()->create();

        $this->api('POST', '/enrollments', ['course_id' => $course->id, 'student_id' => $other->id], $this->token($student, ['enrollments:write']))
            ->assertUnprocessable()->assertJsonValidationErrors('student_id');
        $token = $this->token($admin, ['enrollments:write']);
        foreach ([$admin->id, $disabled->id] as $id) {
            $this->api('POST', '/enrollments', ['course_id' => $course->id, 'student_id' => $id], $token)->assertUnprocessable()->assertJsonValidationErrors('student_id');
        }
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_rejects_draft_and_archived_enrollments_and_other_students_withdrawals(): void
    {
        $student = User::factory()->create();
        $draft = Course::factory()->create();
        $archived = Course::factory()->published()->create();
        $archived->delete();
        $other = Enrollment::factory()->create();
        $token = $this->token($student, ['enrollments:write']);

        $this->api('POST', '/enrollments', ['course_id' => $draft->id], $token)->assertForbidden();
        $this->api('POST', '/enrollments', ['course_id' => $archived->id], $token)->assertNotFound();
        $this->api('DELETE', '/enrollments/'.$other->id, [], $token)->assertForbidden();
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertNull($other->refresh()->withdrawn_at);
    }

    public function test_teacher_cannot_manage_foreign_enrollments_even_after_reassignment(): void
    {
        $teacher = User::factory()->teacher()->create();
        $enrollment = Enrollment::factory()->create();
        $token = $this->token($teacher, ['enrollments:write']);

        $this->api('POST', '/enrollments', ['course_id' => $enrollment->course_id, 'student_id' => $enrollment->student_id], $token)->assertForbidden();
        $this->api('DELETE', '/enrollments/'.$enrollment->id, [], $token)->assertForbidden();
        $this->assertNull($enrollment->refresh()->withdrawn_at);
    }

    public function test_enrollment_directory_is_paginated_and_role_scoped(): void
    {
        $teacher = User::factory()->teacher()->create();
        $course = Course::factory()->published()->create(['teacher_id' => $teacher->id]);
        $student = User::factory()->create();
        $one = Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $course->id]);
        Enrollment::factory()->count(2)->create(['course_id' => $course->id]);
        $withdrawn = Enrollment::factory()->withdrawn()->create(['course_id' => $course->id]);
        $foreign = Enrollment::factory()->create();
        $token = $this->token($teacher, ['enrollments:read']);

        $page = $this->api('GET', '/enrollments?per_page=2', [], $token)->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(2, 'data');
        $this->assertSame(['id', 'name', 'email'], array_keys($page->json('data.0.student')));
        $this->api('GET', '/enrollments?status=withdrawn', [], $token)->assertOk()->assertJsonPath('data.0.id', $withdrawn->id);
        $this->api('GET', '/enrollments?course_id='.$foreign->course_id, [], $token)->assertOk()->assertJsonCount(0, 'data');
        $this->api('GET', '/enrollments?student_id='.$student->id, [], $token)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $one->id);
        $studentToken = $this->token($student, ['enrollments:read']);
        $this->api('GET', '/enrollments?status=all', [], $studentToken)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.student_id', $student->id);
        $this->api('GET', '/enrollments?student_id='.$foreign->student_id, [], $studentToken)->assertUnprocessable();
    }

    public function test_withdrawal_is_allowed_after_unpublication_but_not_after_course_archival(): void
    {
        $enrollment = Enrollment::factory()->create();
        $token = $this->token($enrollment->student, ['enrollments:write']);
        $enrollment->course->forceFill(['published_at' => null])->save();

        $this->api('DELETE', '/enrollments/'.$enrollment->id, [], $token)->assertNoContent();
        $enrollment->course->delete();
        $this->api('DELETE', '/enrollments/'.$enrollment->id, [], $token)->assertForbidden();
        $this->assertModelExists($enrollment);
    }
}
