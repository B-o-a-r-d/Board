<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Card $card,
        public string $type,
        public User $actor,
        public ?string $excerpt = null,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type,
            'card_id' => $this->card->id,
            'board_id' => $this->card->board_id,
            'card_title' => $this->card->title,
            'actor_id' => $this->actor->id,
            'actor_name' => $this->actor->name,
            'excerpt' => $this->excerpt,
            'message' => $this->message(),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }

    /**
     * Custom broadcast event name (client listens for ".card.notification").
     */
    public function broadcastType(): string
    {
        return 'card.notification';
    }

    private function message(): string
    {
        return match ($this->type) {
            'assigned' => "{$this->actor->name} vous a assigné à « {$this->card->title} »",
            'comment' => "{$this->actor->name} a commenté « {$this->card->title} »",
            'mention' => "{$this->actor->name} vous a mentionné dans « {$this->card->title} »",
            'due_soon' => "« {$this->card->title} » arrive à échéance",
            default => "Activité sur « {$this->card->title} »",
        };
    }
}
