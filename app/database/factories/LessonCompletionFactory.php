<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;

class LessonCompletionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'course_id' => fn (array $attributes): int => Enrollment::findOrFail($attributes['enrollment_id'])->course_id,
            'lesson_id' => fn (array $attributes): int => Lesson::factory()->published()->create(['course_id' => $attributes['course_id']])->id,
            'completed_at' => now(),
        ];
    }
}
