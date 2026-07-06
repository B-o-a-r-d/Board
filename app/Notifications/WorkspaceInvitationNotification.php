<?php

namespace App\Notifications;

use App\Models\WorkspaceInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkspaceInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkspaceInvitation $invitation) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $workspace = $this->invitation->workspace;
        $url = route('invitations.accept', $this->invitation->token);

        return (new MailMessage)
            ->subject("Invitation à rejoindre {$workspace->name}")
            ->greeting('Bonjour,')
            ->line("Vous avez été invité à rejoindre le workspace « {$workspace->name} » sur ".config('app.name').'.')
            ->action("Rejoindre {$workspace->name}", $url)
            ->line('Cette invitation expirera le '.$this->invitation->expires_at?->translatedFormat('d F Y').'.');
    }
}
