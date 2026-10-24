<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CourseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'title' => fake()->sentence(4),
            'slug' => fake()->unique()->slug(),
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['published_at' => now()->subMinute()]);
    }
}
