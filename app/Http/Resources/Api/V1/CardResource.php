<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Card
 */
class CardResource extends JsonResource
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
            'list_id' => $this->board_list_id,
            'title' => $this->title,
            'description' => $this->description,
            'position' => $this->position,
            'cover_color' => $this->cover_color,
            'due_at' => optional($this->due_at)->toIso8601String(),
            'completed_at' => optional($this->completed_at)->toIso8601String(),
            'archived_at' => optional($this->archived_at)->toIso8601String(),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'members' => $this->whenLoaded('members', fn () => $this->members->map(fn ($member) => [
                'id' => $member->id,
                'name' => $member->name,
            ])),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
