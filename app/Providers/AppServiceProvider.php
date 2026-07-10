<?php

namespace App\Providers;

use App\Automations\Actions;
use App\Automations\AutomationRegistry;
use App\Automations\Triggers;
use App\Models\User;
use App\Plugins\PluginContext;
use Board\PluginSdk\Contracts\PluginContext as PluginContextContract;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(AutomationRegistry::class, function (): AutomationRegistry {
            $registry = new AutomationRegistry;

            foreach ([
                Triggers\CardCreatedTrigger::class,
                Triggers\CardMovedToListTrigger::class,
                Triggers\CardCompletedTrigger::class,
                Triggers\CardDueSoonTrigger::class,
                Triggers\ManualTrigger::class,
            ] as $trigger) {
                $registry->registerTrigger(new $trigger);
            }

            foreach ([
                Actions\MarkCompleteAction::class,
                Actions\ArchiveCardAction::class,
                Actions\AssignLabelAction::class,
                Actions\AssignMemberAction::class,
                Actions\MoveToListAction::class,
            ] as $action) {
                $registry->registerAction(new $action);
            }

            return $registry;
        });

        // Plugins (Power-Ups) register themselves into this singleton from their
        // own package service providers (Laravel auto-discovery), so installing
        // a plugin is just `composer require`.
        $this->app->singleton(PluginRegistry::class, fn (): PluginRegistry => new PluginRegistry);

        // Bridge decoupled plugin code (MCP tools) back to host state.
        $this->app->singleton(PluginContextContract::class, PluginContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Gate::define('admin', fn (User $user): bool => $user->isAdmin());

        $this->translateAuthMails();
    }

    /**
     * Localise (French) the email verification and password reset e-mails.
     */
    private function translateAuthMails(): void
    {
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            return (new MailMessage)
                ->subject('Vérifiez votre adresse e-mail')
                ->greeting('Bonjour '.$notifiable->name.',')
                ->line('Merci de votre inscription sur '.config('app.name').'. Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail.')
                ->action('Vérifier mon adresse e-mail', $url)
                ->line("Si vous n'êtes pas à l'origine de cette inscription, aucune action n'est requise.")
                ->salutation('À bientôt,'."\n".config('app.name'));
        });

        ResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Réinitialisation de votre mot de passe')
                ->greeting('Bonjour,')
                ->line('Vous recevez cet e-mail car une réinitialisation du mot de passe de votre compte a été demandée.')
                ->action('Réinitialiser le mot de passe', $url)
                ->line("Ce lien de réinitialisation expirera dans {$expire} minutes.")
                ->line("Si vous n'avez pas demandé cette réinitialisation, aucune action n'est requise.")
                ->salutation('Cordialement,'."\n".config('app.name'));
        });
    }
}
