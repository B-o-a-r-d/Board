<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use App\Models\User;
use App\Notifications\CardNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotifyMembersAction implements AutomationAction
{
    public static function key(): string
    {
        return 'notify_members';
    }

    public function label(): string
    {
        return 'Notifier les membres de la carte';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'message', 'label' => 'Message (optionnel)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $actor = Auth::user();

        // Notifications carry an actor; scheduled (userless) contexts skip quietly.
        if ($actor === null) {
            return;
        }

        $excerpt = Str::limit(trim((string) ($config['message'] ?? '')), 120) ?: null;

        // No self-notification: the triggering user already knows what they did.
        $recipientIds = $card->members()->pluck('users.id')
            ->merge($card->watchers()->pluck('users.id'))
            ->unique()
            ->reject(fn ($id) => $id === $actor->getKey());

        User::whereKey($recipientIds)
            ->get()
            ->each(fn (User $user) => $user->notify(new CardNotification($card, 'automation', $actor, $excerpt)));
    }
}
