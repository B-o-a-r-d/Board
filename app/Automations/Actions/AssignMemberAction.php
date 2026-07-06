<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;

class AssignMemberAction implements AutomationAction
{
    public static function key(): string
    {
        return 'assign_member';
    }

    public function label(): string
    {
        return 'Assigner un membre';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'user_id', 'label' => 'Membre', 'type' => 'member'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $userId = (int) ($config['user_id'] ?? 0);

        if ($userId === 0) {
            return;
        }

        // Only board members can be assigned.
        if ($card->board->members()->whereKey($userId)->exists()) {
            $card->members()->syncWithoutDetaching([$userId]);
        }
    }
}
