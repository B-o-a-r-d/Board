<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

class PostCommentAction implements AutomationAction
{
    public static function key(): string
    {
        return 'post_comment';
    }

    public function label(): string
    {
        return 'Publier un commentaire';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'body', 'label' => 'Commentaire ({card} et {list} sont remplacés)', 'type' => 'text'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        // Comments need an author; scheduled (userless) contexts skip quietly.
        if (Auth::id() === null) {
            return;
        }

        $body = trim(strtr((string) ($config['body'] ?? ''), [
            '{card}' => $card->title,
            '{list}' => $card->list?->name ?? '',
        ]));

        if ($body === '') {
            return;
        }

        $card->comments()->create([
            'user_id' => Auth::id(),
            'body' => mb_substr($body, 0, 5000),
        ]);
    }
}
