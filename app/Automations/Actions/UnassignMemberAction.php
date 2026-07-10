<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

class UnassignMemberAction implements AutomationAction
{
    public static function key(): string
    {
        return 'unassign_member';
    }

    public function label(): string
    {
        return 'Retirer un membre';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'user_id', 'label' => "Membre ('me' = l'utilisateur qui déclenche)", 'type' => 'member'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $userId = ($config['user_id'] ?? null) === 'me'
            ? (int) Auth::id()
            : (int) ($config['user_id'] ?? 0);

        if ($userId > 0) {
            $card->members()->detach($userId);
        }
    }
}
