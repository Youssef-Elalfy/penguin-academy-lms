<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TokenResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'abilities' => $this->abilities,
            'created_at' => $this->created_at?->toISOString(), 'expires_at' => $this->expires_at?->toISOString(),
            'last_used_at' => $this->last_used_at?->toISOString(),
        ];
    }
}
