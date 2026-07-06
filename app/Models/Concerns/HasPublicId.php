<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Gives a model a short, unique, non-guessable public identifier (a ULID) kept
 * alongside its bigint primary key. Internal relations keep using the integer
 * PK; the public_id is what users copy/share and what public routes resolve.
 */
trait HasPublicId
{
    protected static function bootHasPublicId(): void
    {
        static::creating(function ($model): void {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }
}
