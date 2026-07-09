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
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
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

    public string $biography = '';

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $tokenName = '';

    public ?string $newToken = null;

    public bool $mcpEnabled = false;

    /** Whether 2FA is fully enabled (secret confirmed) for the current user. */
    public bool $twoFactorEnabled = false;

    /** Setup in progress: the QR code / secret are being shown, awaiting confirmation. */
    public bool $showingQrCode = false;

    /** Whether the recovery codes block is currently revealed. */
    public bool $showingRecoveryCodes = false;

    /** The TOTP code typed to confirm 2FA activation. */
    public string $twoFactorCode = '';

    public string $locale = '';

    /** @var array<string, bool> */
    public array $notificationPreferences = [];

    public function mount(): void
    {
        $user = Auth::user();

        $this->name = $user->name;
        $this->biography = (string) $user->biography;
        $this->email = $user->email;
        $this->locale = $user->locale ?: app()->getLocale();
        $this->mcpEnabled = Setting::mcpEnabled();
        $this->twoFactorEnabled = ! is_null($user->two_factor_confirmed_at);
        $this->notificationPreferences = $user->notificationPreferences();
    }

    /**
     * Debounced auto-save for the "Profil" tab: name + biography persist as the
     * user types (no "Enregistrer" button), confirmed by a toast. Email and
     * password keep their explicit buttons (handled separately).
     */
    public function updated(string $property): void
    {
        if (in_array($property, ['name', 'biography'], true)) {
            $this->autosaveProfile();
        }
    }

    public function autosaveProfile(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'biography' => ['nullable', 'string', 'max:500'],
        ]);

        Auth::user()->update([
            'name' => $data['name'],
            'biography' => $data['biography'] !== '' ? $data['biography'] : null,
        ]);

        $this->dispatch('toast', message: __('Profil enregistré'), type: 'success');
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
        $this->dispatch('toast', message: $this->mcpEnabled ? __('MCP activé pour l\'instance') : __('MCP désactivé'), type: 'success');
    }

    /**
     * Begin 2FA setup: generate the secret + recovery codes and reveal the QR
     * code. The user must confirm with a valid TOTP code before it takes effect.
     */
    public function enableTwoFactorAuthentication(EnableTwoFactorAuthentication $enable): void
    {
        $enable(Auth::user());

        $this->showingQrCode = true;
        $this->showingRecoveryCodes = false;
        $this->twoFactorCode = '';
    }

    /**
     * Confirm the freshly generated secret with a code from the authenticator app.
     */
    public function confirmTwoFactorAuthentication(ConfirmTwoFactorAuthentication $confirm): void
    {
        $confirm(Auth::user(), $this->twoFactorCode);

        $this->twoFactorEnabled = true;
        $this->showingQrCode = false;
        $this->showingRecoveryCodes = true;
        $this->twoFactorCode = '';
        $this->dispatch('toast', message: __('Authentification à deux facteurs activée'), type: 'success');
    }

    public function showRecoveryCodes(): void
    {
        $this->showingRecoveryCodes = true;
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate(Auth::user());

        $this->showingRecoveryCodes = true;
        $this->dispatch('toast', message: __('Codes de récupération régénérés'), type: 'success');
    }

    public function disableTwoFactorAuthentication(DisableTwoFactorAuthentication $disable): void
    {
        $disable(Auth::user());

        $this->twoFactorEnabled = false;
        $this->showingQrCode = false;
        $this->showingRecoveryCodes = false;
        $this->twoFactorCode = '';
        $this->dispatch('toast', message: __('Authentification à deux facteurs désactivée'), type: 'info');
    }

    public function createToken(): void
    {
        $this->validate(['tokenName' => ['required', 'string', 'max:255']]);

        $this->newToken = Auth::user()->createToken($this->tokenName)->plainTextToken;
        $this->tokenName = '';
        $this->dispatch('toast', message: __('Token API créé'), type: 'success');
    }

    public function revokeToken(int $tokenId): void
    {
        Auth::user()->tokens()->whereKey($tokenId)->delete();
        $this->dispatch('toast', message: __('Token révoqué'), type: 'info');
    }

    public function toggleIcalFeed(): void
    {
        abort_unless((bool) config('board.ical_feeds'), 404);

        $user = Auth::user();

        if ($user->hasIcalFeed()) {
            $user->disableIcalFeed();
            $this->dispatch('toast', message: __('Flux calendrier désactivé'), type: 'info');
        } else {
            $user->enableIcalFeed();
            $this->dispatch('toast', message: __('Flux calendrier activé'), type: 'success');
        }
    }

    public function regenerateIcalFeed(): void
    {
        abort_unless((bool) config('board.ical_feeds'), 404);

        Auth::user()->regenerateIcalFeed();
        $this->dispatch('toast', message: __('Lien du flux calendrier régénéré'), type: 'success');
    }

    public function updateProfileInformation(UpdateUserProfileInformation $updater): void
    {
        $updater->update(Auth::user(), [
            'name' => $this->name,
            'email' => $this->email,
        ]);

        $this->dispatch('profile-updated');
        session()->flash('profile-status', __('Profil mis à jour.'));
    }

    public function updatePassword(UpdateUserPassword $updater): void
    {
        $updater->update(Auth::user(), [
            'current_password' => $this->current_password,
            'password' => $this->password,
            'password_confirmation' => $this->password_confirmation,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');
        session()->flash('password-status', __('Mot de passe mis à jour.'));
    }

    public function render(): View
    {
        $user = Auth::user();
        $hasSecret = ! is_null($user->two_factor_secret);

        return view('livewire.settings.profile', [
            'tokens' => $user->tokens()->latest()->get(),
            'icalUrl' => $user->icalUrl(),
            'mcpEndpoint' => url('/mcp/board'),
            'twoFactorQrCode' => ($this->showingQrCode && $hasSecret) ? $user->twoFactorQrCodeSvg() : null,
            'twoFactorSecretKey' => ($this->showingQrCode && $hasSecret) ? decrypt($user->two_factor_secret) : null,
            'recoveryCodes' => ($this->showingRecoveryCodes && $hasSecret) ? $user->recoveryCodes() : [],
        ]);
    }
}
