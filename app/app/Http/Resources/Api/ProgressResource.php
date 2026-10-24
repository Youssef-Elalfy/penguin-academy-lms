<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'completed_lessons' => $this->completed, 'eligible_lessons' => $this->total,
            'percent' => $this->percent, 'is_complete' => $this->isComplete,
            'state' => $this->state, 'next_lesson_id' => $this->nextLessonId,
        ];
    }
}
