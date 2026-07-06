<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['url_hash', 'url', 'title', 'description', 'image', 'site_name', 'ok', 'fetched_at'])]
class LinkPreview extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ok' => 'boolean',
            'fetched_at' => 'datetime',
        ];
    }
}
