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

    /** Comma-separated allow-lists edited in the "Contrôles d'accès" card. */
    public string $allowedInviteDomains = '';

    public string $allowedAttachmentExtensions = '';

    public function mount(Workspace $workspace): void
    {
        $this->authorize('view', $workspace);

        $this->workspace = $workspace;
        $this->allowedInviteDomains = implode(', ', (array) $workspace->allowed_invite_domains);
        $this->allowedAttachmentExtensions = implode(', ', (array) $workspace->allowed_attachment_extensions);
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
            $this->addError('inviteEmail', __('Cet utilisateur est déjà membre du workspace.'));

            return;
        }

        if (! $this->workspace->invitationDomainAllowed($data['inviteEmail'])) {
            $this->addError('inviteEmail', __('Ce domaine e-mail n\'est pas autorisé pour ce workspace.'));

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
        session()->flash('workspace-status', __('Invitation envoyée à :email.', ['email' => $invitation->email]));
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

    /**
     * Deactivate a member: they keep their account but lose access to this
     * workspace and its boards until reactivated. The owner cannot be deactivated.
     */
    public function deactivateMember(int $userId): void
    {
        $this->authorize('manageMembers', $this->workspace);

        if ($userId === $this->workspace->owner_id) {
            return;
        }

        $this->workspace->members()->updateExistingPivot($userId, ['deactivated_at' => now()]);
        $this->dispatch('toast', message: __('Membre désactivé'), type: 'info');
    }

    public function reactivateMember(int $userId): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $this->workspace->members()->updateExistingPivot($userId, ['deactivated_at' => null]);
        $this->dispatch('toast', message: __('Membre réactivé'), type: 'success');
    }

    /**
     * Persist the invite-domain and attachment-type allow-lists.
     */
    public function saveAccessControls(): void
    {
        $this->authorize('manageMembers', $this->workspace);

        $this->workspace->update([
            'allowed_invite_domains' => $this->parseList($this->allowedInviteDomains, '@'),
            'allowed_attachment_extensions' => $this->parseList($this->allowedAttachmentExtensions, '.'),
        ]);

        $this->dispatch('toast', message: __("Contrôles d'accès enregistrés"), type: 'success');
    }

    /**
     * Split a comma/space/newline-separated string into a normalized, unique list
     * (lower-cased, with the given leading character stripped). Empty = null.
     *
     * @return array<int, string>|null
     */
    private function parseList(string $raw, string $strip): ?array
    {
        $items = collect(preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY))
            ->map(fn (string $item): string => Str::lower(ltrim(trim($item), $strip)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items === [] ? null : $items;
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
