<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'title' => $this->title, 'slug' => $this->slug,
            'summary' => $this->summary, 'description' => $this->description,
            'is_published' => $this->isPublished(), 'published_at' => $this->published_at?->toISOString(),
            'teacher' => $this->whenLoaded('teacher', fn (): ?array => $this->teacher ? ['id' => $this->teacher->id, 'name' => $this->teacher->name] : null),
            'lessons_count' => $this->whenCounted('lessons'),
            'enrollments_count' => $this->whenCounted('enrollments'),
            'is_enrolled' => $this->whenHas('is_enrolled', fn (mixed $value): bool => (bool) $value),
            'lesson_order_version' => $this->when($request->user()->can('update', $this->resource), $this->lesson_order_version),
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
