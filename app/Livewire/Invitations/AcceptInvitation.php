<?php

namespace App\Livewire\Invitations;

use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Invitation')]
class AcceptInvitation extends Component
{
    public string $token = '';

    public ?WorkspaceInvitation $invitation = null;

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->invitation = WorkspaceInvitation::with('workspace')->where('token', $token)->first();
    }

    public function accept(): mixed
    {
        if ($this->invalidReason() !== null) {
            return null;
        }

        $workspace = $this->invitation->workspace;

        if (! $workspace->hasMember(Auth::user())) {
            $workspace->members()->attach(Auth::id(), ['role' => $this->invitation->role]);
        }

        $this->invitation->update(['accepted_at' => now()]);

        session()->flash('workspace-status', "Vous avez rejoint « {$workspace->name} ».");

        return $this->redirectRoute('workspaces.settings', $workspace, navigate: true);
    }

    public function invalidReason(): ?string
    {
        if (! $this->invitation) {
            return 'Cette invitation est introuvable ou a été révoquée.';
        }

        if ($this->invitation->accepted_at) {
            return 'Cette invitation a déjà été acceptée.';
        }

        if ($this->invitation->isExpired()) {
            return 'Cette invitation a expiré.';
        }

        if (strcasecmp($this->invitation->email, Auth::user()->email) !== 0) {
            return "Cette invitation est destinée à {$this->invitation->email}. Connectez-vous avec cette adresse pour l'accepter.";
        }

        return null;
    }

    public function render(): View
    {
        return view('livewire.invitations.accept-invitation', [
            'reason' => $this->invalidReason(),
        ]);
    }
}
