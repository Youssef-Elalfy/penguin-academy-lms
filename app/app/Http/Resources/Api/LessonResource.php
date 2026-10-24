<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'course_id' => $this->course_id, 'title' => $this->title,
            'content' => $this->when(! $request->routeIs('api.v1.lessons.index'), $this->content),
            'position' => $this->position, 'is_published' => $this->is_published,
            'created_at' => $this->created_at?->toISOString(), 'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
