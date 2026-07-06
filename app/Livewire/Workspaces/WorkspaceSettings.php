<?php

namespace App\Livewire\Workspaces;

use App\Enums\Role;
use App\Models\Workspace;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Paramètres du workspace')]
class WorkspaceSettings extends Component
{
    public Workspace $workspace;

    public string $inviteEmail = '';

    public string $inviteRole = 'member';

    public function mount(Workspace $workspace): void
    {
        $this->authorize('view', $workspace);

        $this->workspace = $workspace;
    }

    public function invite(): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $data = $this->validate([
            'inviteEmail' => [
                'required', 'email', 'max:255',
                Rule::unique('workspace_invitations', 'email')->where('workspace_id', $this->workspace->id),
            ],
            'inviteRole' => ['required', Rule::in([Role::Admin->value, Role::Member->value])],
        ], attributes: ['inviteEmail' => 'adresse e-mail']);

        if ($this->workspace->members()->where('email', $data['inviteEmail'])->exists()) {
            $this->addError('inviteEmail', 'Cet utilisateur est déjà membre du workspace.');

            return;
        }

        $invitation = $this->workspace->invitations()->create([
            'invited_by' => Auth::id(),
            'email' => $data['inviteEmail'],
            'role' => $data['inviteRole'],
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        $this->reset('inviteEmail');
        $this->inviteRole = 'member';
        session()->flash('workspace-status', "Invitation envoyée à {$invitation->email}.");
    }

    public function revokeInvitation(int $invitationId): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $this->workspace->invitations()->whereKey($invitationId)->delete();
    }

    public function updateRole(int $userId, string $role): void
    {
        $this->authorize('manageMembers', $this->workspace);

        if ($userId === $this->workspace->owner_id || ! in_array($role, [Role::Admin->value, Role::Member->value], true)) {
            return;
        }

        $this->workspace->members()->updateExistingPivot($userId, ['role' => $role]);
    }

    public function removeMember(int $userId): void
    {
        $this->authorize('manageMembers', $this->workspace);

        if ($userId === $this->workspace->owner_id) {
            return;
        }

        $this->workspace->members()->detach($userId);
    }

    public function deleteWorkspace(): mixed
    {
        $this->authorize('delete', $this->workspace);

        $this->workspace->delete();

        return $this->redirectRoute('dashboard', navigate: true);
    }

    public function render(): View
    {
        return view('livewire.workspaces.workspace-settings', [
            'members' => $this->workspace->members()->orderBy('name')->get(),
            'invitations' => $this->workspace->invitations()->whereNull('accepted_at')->latest()->get(),
            'canManage' => Auth::user()->can('manageMembers', $this->workspace),
        ]);
    }
}
