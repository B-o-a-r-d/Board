<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Profil')]
class Profile extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $tokenName = '';

    public ?string $newToken = null;

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function createToken(): void
    {
        $this->validate(['tokenName' => ['required', 'string', 'max:255']]);

        $this->newToken = Auth::user()->createToken($this->tokenName)->plainTextToken;
        $this->tokenName = '';
        $this->dispatch('toast', message: 'Token API créé', type: 'success');
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();
        $this->dispatch('toast', message: 'Token révoqué', type: 'info');
    }

    public function updateProfileInformation(UpdateUserProfileInformation $updater): void
    {
        $updater->update(Auth::user(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);

        $this->dispatch('profile-updated');
        session()->flash('profile-status', 'Profil mis à jour.');
    }

    public function updatePassword(UpdateUserPassword $updater): void
    {
        $updater->update(Auth::user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('password-status', 'Mot de passe mis à jour.');
    }

    public function render(): View
    {
        return view('livewire.settings.profile', [
            'tokens' => Auth::user()->tokens()->latest()->get(),
        ]);
    }
}
