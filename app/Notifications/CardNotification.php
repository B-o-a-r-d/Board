<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
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
     * Resolve the delivery channels from the recipient's notification
     * preferences (per-event toggles + in-app / email channels).
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $prefs = method_exists($notifiable, 'notificationPreferences')
            ? $notifiable->notificationPreferences()
            : User::defaultNotificationPreferences();

        // In "mentions only" mode, plain comment notifications are dropped —
        // the user still receives the separate 'mention' notification when tagged.
        if ($this->type === 'comment' && ($prefs['mentions_only'] ?? false)) {
            return [];
        }

        $eventKey = match ($this->type) {
            'comment' => 'comments',
            'mention' => 'mentions',
            'reaction' => 'reactions',
            'assigned' => 'assignments',
            default => null, // due_soon and future types are always delivered
        };

        if ($eventKey !== null && ! ($prefs[$eventKey] ?? true)) {
            return [];
        }

        $channels = [];

        if ($prefs['inapp'] ?? true) {
            $channels[] = 'database';
            $channels[] = 'broadcast';
        }

        if ($prefs['email'] ?? false) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->message())
            ->line($this->message());

        if ($this->excerpt !== null && $this->excerpt !== '') {
            $mail->line("« {$this->excerpt} »");
        }

        return $mail->action(
            __('Voir la carte'),
            route('boards.show', ['board' => $this->card->board, 'card' => $this->card->public_id]),
        );
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
        $actor = $this->actor->name;
        $title = $this->card->title;

        return match ($this->type) {
            'assigned' => __(':actor vous a assigné à « :title »', ['actor' => $actor, 'title' => $title]),
            'comment' => __(':actor a commenté « :title »', ['actor' => $actor, 'title' => $title]),
            'mention' => __(':actor vous a mentionné dans « :title »', ['actor' => $actor, 'title' => $title]),
            'reaction' => __(':actor a réagi à votre commentaire sur « :title »', ['actor' => $actor, 'title' => $title]),
            'due_soon' => __('« :title » arrive à échéance', ['title' => $title]),
            'automation' => __('Automatisation sur « :title »', ['title' => $title]),
            default => __('Activité sur « :title »', ['title' => $title]),
        };
    }
}
