<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['board_id', 'created_by', 'name', 'trigger_type', 'trigger_config', 'action_type', 'action_config', 'actions', 'conditions', 'actor_scope', 'is_active'])]
class Automation extends Model
{
    use HasPublicId;

    public const ACTOR_ANYONE = 'anyone';

    public const ACTOR_ME = 'me';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger_config' => 'array',
            'action_config' => 'array',
            'actions' => 'array',
            'conditions' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * The ordered action pipeline. Falls back to the legacy single
     * `action_type`/`action_config` pair for rows predating the migration.
     *
     * @return array<int, array{type: string, config: array<string, mixed>}>
     */
    public function actionList(): array
    {
        $actions = collect($this->actions ?? [])
            ->filter(fn ($a) => is_array($a) && filled($a['type'] ?? null))
            ->map(fn (array $a) => ['type' => (string) $a['type'], 'config' => (array) ($a['config'] ?? [])])
            ->values()
            ->all();

        if ($actions === [] && filled($this->action_type)) {
            return [['type' => $this->action_type, 'config' => $this->action_config ?? []]];
        }

        return $actions;
    }

    /**
     * The AND-combined conditions guarding the pipeline.
     *
     * @return array<int, array{type: string, config: array<string, mixed>}>
     */
    public function conditionList(): array
    {
        return collect($this->conditions ?? [])
            ->filter(fn ($c) => is_array($c) && filled($c['type'] ?? null))
            ->map(fn (array $c) => ['type' => (string) $c['type'], 'config' => (array) ($c['config'] ?? [])])
            ->values()
            ->all();
    }

    /**
     * Whether the given user id may fire this rule (the "by me" scope).
     */
    public function actorAllowed(?int $userId): bool
    {
        return $this->actor_scope !== self::ACTOR_ME || ($userId !== null && $userId === $this->created_by);
    }
}
