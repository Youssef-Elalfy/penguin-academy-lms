<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\User;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\ApiTestCase;

class ApiContractRegressionTest extends ApiTestCase
{
    #[TestWith(['admin'])]
    #[TestWith(['teacher'])]
    public function test_new_course_response_includes_the_persisted_integer_order_version(string $role): void
    {
        $user = User::factory()->create(['role' => UserRole::from($role)]);
        $response = $this->api('POST', '/courses', ['title' => 'Contract course', 'slug' => 'contract-course'], $this->token($user, ['courses:write']))
            ->assertCreated()->assertJsonPath('data.lesson_order_version', 0);
        $course = Course::findOrFail($response->json('data.id'));
        $this->assertSame($course->lesson_order_version, $response->json('data.lesson_order_version'));
        $this->assertNull($response->json('data.published_at'));
    }
}
