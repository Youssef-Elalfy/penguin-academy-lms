<?php

namespace App\Http\Resources\Api;

use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'course_id' => $this->course_id, 'student_id' => $this->student_id,
            'status' => $this->withdrawn_at === null ? 'active' : 'withdrawn',
            'enrolled_at' => $this->enrolled_at?->toISOString(), 'withdrawn_at' => $this->withdrawn_at?->toISOString(),
            'course' => $this->whenLoaded('course', fn (): array => ['id' => $this->course->id, 'title' => $this->course->title, 'is_published' => $this->course->isPublished()]),
            'student' => $this->whenLoaded('student', fn (): array => ['id' => $this->student->id, 'name' => $this->student->name, 'email' => $this->student->email]),
            'progress' => $this->whenHas('eligible_lessons_count', fn (): ProgressResource => new ProgressResource(app(ProgressService::class)->summary($this->resource))),
        ];
    }
}
