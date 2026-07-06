<?php

namespace App\Http\Resources\Api\V1;

use App\Models\BoardList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BoardList
 */
class BoardListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'public_id' => $this->public_id,
            'board_id' => $this->board_id,
            'name' => $this->name,
            'cover_color' => $this->cover_color,
            'position' => $this->position,
            'archived_at' => optional($this->archived_at)->toIso8601String(),
            'cards' => CardResource::collection($this->whenLoaded('cards')),
        ];
    }
}
