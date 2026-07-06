<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Board
 */
class BoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workspace_id' => $this->workspace_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'visibility' => $this->visibility->value,
            'background' => $this->background,
            'is_template' => $this->is_template,
            'archived_at' => optional($this->archived_at)->toIso8601String(),
            'lists' => BoardListResource::collection($this->whenLoaded('lists')),
            'labels' => LabelResource::collection($this->whenLoaded('labels')),
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
