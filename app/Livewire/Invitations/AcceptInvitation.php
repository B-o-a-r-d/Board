<?php

namespace App\Livewire\Invitations;

use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.guest')]
#[Title('Invitation')]
class AcceptInvitation extends Component
{
    public string $token = '';

    public ?WorkspaceInvitation $invitation = null;

    public function mount(string $token): mixed
    {
        $this->token = $token;
        $this->invitation = WorkspaceInvitation::with('workspace')->where('token', $token)->first();

        // A guest whose invited address has no account yet is routed to
        // registration, which is gated by this invitation (email pre-filled,
        // workspace joined on submit). Logged-in visitors see the normal page.
        if ($this->invitation && $this->invitation->isPending()
            && ! Auth::check()
            && ! User::where('email', $this->invitation->email)->exists()) {
            session()->put('invitation_token', $this->token);

            return $this->redirect(route('register'));
        }

        return null;
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

        session()->flash('workspace-status', __('Vous avez rejoint « :name ».', ['name' => $workspace->name]));

        return $this->redirectRoute('workspaces.settings', $workspace, navigate: true);
    }

    public function invalidReason(): ?string
    {
        if (! $this->invitation) {
            return __('Cette invitation est introuvable ou a été révoquée.');
        }

        if ($this->invitation->accepted_at) {
            return __('Cette invitation a déjà été acceptée.');
        }

        if ($this->invitation->isExpired()) {
            return __('Cette invitation a expiré.');
        }

        if (! Auth::check()) {
            return __('Un compte existe déjà pour :email. Connectez-vous avec cette adresse pour rejoindre le workspace.', ['email' => $this->invitation->email]);
        }

        if (strcasecmp($this->invitation->email, Auth::user()->email) !== 0) {
            return __('Cette invitation est destinée à :email. Connectez-vous avec cette adresse pour l\'accepter.', ['email' => $this->invitation->email]);
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
