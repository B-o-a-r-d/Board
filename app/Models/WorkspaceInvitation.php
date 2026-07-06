<?php

namespace App\Models;

use Database\Factories\WorkspaceInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['workspace_id', 'invited_by', 'email', 'role', 'token', 'accepted_at', 'expires_at'])]
class WorkspaceInvitation extends Model
{
    /** @use HasFactory<WorkspaceInvitationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Resolve a still-usable invitation from its token (null if missing,
     * accepted or expired).
     */
    public static function pendingFromToken(?string $token): ?self
    {
        if (! $token) {
            return null;
        }

        $invitation = static::with('workspace')->where('token', $token)->first();

        return $invitation && $invitation->isPending() ? $invitation : null;
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
