<?php

namespace App\Livewire\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Http\Middleware\SetLocale;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
#[Title('Profil')]
class Profile extends Component
{
    use WithFileUploads;

    /** Temporary upload bound to the avatar file input. */
    public $avatar = null;

    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $tokenName = '';

    public ?string $newToken = null;

    public bool $mcpEnabled = false;

    public string $locale = '';

    /** @var array<string, bool> */
    public array $notificationPreferences = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->email = $user->email;
        $this->locale = $user->locale ?: app()->getLocale();
        $this->mcpEnabled = Setting::mcpEnabled();
        $this->notificationPreferences = $user->notificationPreferences();
    }

    /**
     * Store the freshly selected avatar on the public disk and point the user at
     * it, removing any previous file. Runs as soon as a file is chosen.
     */
    public function updatedAvatar(): void
    {
        $this->validate([
            'avatar' => ['image', 'max:2048', 'mimes:jpg,jpeg,png,webp,gif'],
        ]);

        $user = Auth::user();
        $old = $user->avatar_path;

        $path = $this->avatar->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        $this->reset('avatar');
        $this->dispatch('toast', message: __('Avatar mis à jour'), type: 'success');
    }

    public function removeAvatar(): void
    {
        $user = Auth::user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        $this->reset('avatar');
        $this->dispatch('toast', message: __('Avatar retiré'), type: 'info');
    }

    public function updateNotificationPreference(string $key, bool $value): void
    {
        abort_unless(array_key_exists($key, User::defaultNotificationPreferences()), 422);

        $prefs = array_merge(Auth::user()->notificationPreferences(), [$key => $value]);
        Auth::user()->update(['notification_preferences' => $prefs]);

        $this->notificationPreferences = $prefs;
        $this->dispatch('toast', message: __('Préférences de notification mises à jour'), type: 'success');
    }

    public function updateLocale(string $locale): void
    {
        abort_unless(in_array($locale, SetLocale::SUPPORTED, true), 422);

        Auth::user()->update(['locale' => $locale]);
        $this->locale = $locale;
        app()->setLocale($locale);
        $this->dispatch('toast', message: __('Langue mise à jour'), type: 'success');
    }

    public function toggleMcp(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $this->mcpEnabled = ! $this->mcpEnabled;
        Setting::set('mcp_enabled', $this->mcpEnabled);
        $this->dispatch('toast', message: $this->mcpEnabled ? 'MCP activé pour l\'instance' : 'MCP désactivé', type: 'success');
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
            'mcpEndpoint' => url('/mcp/board'),
        ]);
    }
}
