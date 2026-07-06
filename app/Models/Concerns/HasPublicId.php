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

    /**
     * Resolve route-model bindings by the public ULID, never the internal
     * bigint primary key. This makes the public_id the sole public identifier
     * across web and API routes; the integer PK is never exposed nor accepted.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
