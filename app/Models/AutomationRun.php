<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One execution of an automation pipeline — the journal behind the builder's
 * Activity tab. `status` is success (all actions ran), partial (some failed)
 * or failed (nothing ran).
 */
#[Fillable(['automation_id', 'board_id', 'card_id', 'status', 'actions_run', 'actions_failed', 'error'])]
class AutomationRun extends Model
{
    public const UPDATED_AT = null;

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }
}
