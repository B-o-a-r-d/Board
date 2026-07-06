<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Label;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Label
 */
class LabelResource extends JsonResource
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
            'color' => $this->color,
        ];
    }
}
