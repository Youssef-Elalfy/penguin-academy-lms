<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'course_id' => $this->id, 'title' => $this->title, 'is_published' => $this->isPublished(),
            'lessons' => (int) $this->lessons_count, 'eligible_lessons' => (int) $this->eligible_lessons_count,
            'active_enrollments' => (int) $this->enrollments_count,
            'completed_lessons' => (int) $this->completed_lessons_count,
            'completed_enrollments' => (int) $this->completed_enrollments_count,
            'average_progress' => (float) $this->average_progress, 'completion_rate' => (float) $this->completion_rate,
            'last_completed_at' => $this->last_completed_at,
        ];
    }
}
