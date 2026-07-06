<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['created_by', 'name', 'title', 'description', 'cover_color', 'checklists'])]
class CardTemplate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'checklists' => 'array',
        ];
    }
}
