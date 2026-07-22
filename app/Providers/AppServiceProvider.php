<?php

namespace App\Providers;

use App\Automations\Actions;
use App\Automations\AutomationRegistry;
use App\Automations\Conditions;
use App\Automations\PluginAutomationAction;
use App\Automations\Triggers;
use App\Models\User;
use App\Plugins\PluginAssets;
use App\Plugins\PluginContext;
use Board\PluginSdk\Contracts\AssetRegistrar;
use Board\PluginSdk\Contracts\PluginContext as PluginContextContract;
use Board\PluginSdk\Contracts\ProvidesAutomationActions;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
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
                // Card Move
                Triggers\CardCreatedTrigger::class,
                Triggers\CardMovedToListTrigger::class,
                Triggers\CardMovedFromListTrigger::class,
                Triggers\CardArchivedTrigger::class,
                Triggers\ListHasNCardsTrigger::class,
                // Card Changes
                Triggers\CardCompletedTrigger::class,
                Triggers\CardLabelAddedTrigger::class,
                Triggers\CardLabelRemovedTrigger::class,
                Triggers\CardMemberAssignedTrigger::class,
                // Dates
                Triggers\CardDueSoonTrigger::class,
                Triggers\CardDueDateSetTrigger::class,
                Triggers\CardDueRelativeTrigger::class,
                // Time-driven (no app event — evaluated by automations:run-scheduled)
                Triggers\ScheduledTrigger::class,
                // Checklists
                Triggers\ChecklistAddedTrigger::class,
                Triggers\ChecklistItemCheckedTrigger::class,
                Triggers\ChecklistCompletedTrigger::class,
                // Content & Fields
                Triggers\CardCommentAddedTrigger::class,
                Triggers\CardTitleContainsTrigger::class,
                Triggers\CustomFieldChangedTrigger::class,
                // Manual (card + board buttons)
                Triggers\ManualTrigger::class,
                Triggers\BoardButtonTrigger::class,
            ] as $trigger) {
                $registry->registerTrigger(new $trigger);
            }

            foreach ([
                Conditions\HasLabelCondition::class,
                Conditions\InListCondition::class,
                Conditions\AssignedToCondition::class,
                Conditions\CustomFieldEqualsCondition::class,
                Conditions\TitleContainsCondition::class,
                Conditions\HasDueDateCondition::class,
            ] as $condition) {
                $registry->registerCondition(new $condition);
            }

            foreach ([
                // Move / organisation
                Actions\MoveToListAction::class,
                Actions\MoveInListAction::class,
                Actions\SortListAction::class,
                Actions\ArchiveCardAction::class,
                Actions\ArchiveListCardsAction::class,
                // Add / remove
                Actions\AssignLabelAction::class,
                Actions\RemoveLabelAction::class,
                Actions\AssignMemberAction::class,
                Actions\UnassignMemberAction::class,
                Actions\AddChecklistAction::class,
                Actions\CreateCardAction::class,
                Actions\CopyCardAction::class,
                Actions\CreateFollowUpCardAction::class,
                // Dates
                Actions\SetDueDateAction::class,
                Actions\ClearDueDateAction::class,
                Actions\MarkCompleteAction::class,
                Actions\MarkIncompleteAction::class,
                // Content / fields
                Actions\PostCommentAction::class,
                Actions\SetCustomFieldAction::class,
                // Output
                Actions\NotifyMembersAction::class,
                Actions\SendWebhookAction::class,
            ] as $action) {
                $registry->registerAction(new $action);
            }

            // Power-Ups contribute their own actions (ProvidesAutomationActions):
            // registered under "plugin:<plugin>:<action>", sandboxed at run time.
            foreach ($this->app->make(PluginRegistry::class)->all() as $plugin) {
                if (! $plugin instanceof ProvidesAutomationActions) {
                    continue;
                }

                try {
                    foreach ($plugin->automationActions() as $declaration) {
                        $key = mb_substr(trim((string) ($declaration['key'] ?? '')), 0, 60);
                        $label = trim((string) ($declaration['label'] ?? ''));

                        if ($key === '' || $label === '') {
                            continue;
                        }

                        $adapter = new PluginAutomationAction(
                            $plugin,
                            $key,
                            $label,
                            array_values(array_filter((array) ($declaration['configFields'] ?? []), 'is_array')),
                        );
                        $registry->registerAction($adapter, $adapter->qualifiedKey());
                    }
                } catch (\Throwable $e) {
                    report($e); // a broken plugin never blocks the core registry
                }
            }

            return $registry;
        });

        // Plugins (Power-Ups) register themselves into this singleton from their
        // own package service providers (Laravel auto-discovery), so installing
        // a plugin is just `composer require`.
        $this->app->singleton(PluginRegistry::class, fn (): PluginRegistry => new PluginRegistry);

        // Sink for plugin front-end assets (ProvidesAssets): plugin providers
        // feed it at boot; the asset route + <x-plugin-assets> component read it.
        // One shared instance under both names — the SDK writes through the
        // AssetRegistrar contract, the host reads through PluginAssets.
        $this->app->singleton(PluginAssets::class);
        $this->app->alias(PluginAssets::class, AssetRegistrar::class);

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

        // Authenticated API and MCP endpoints are unbounded by default in Laravel
        // 11+; throttle them per token (fall back to IP) so a leaked token can't
        // hammer the app. MCP is chattier (agent tool loops), hence a higher cap.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('mcp', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

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
