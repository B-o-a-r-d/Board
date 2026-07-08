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
            ->subject(__('Invitation à rejoindre :workspace', ['workspace' => $workspace->name]))
            ->greeting(__('Bonjour,'))
            ->line(__('Vous avez été invité à rejoindre le workspace « :workspace » sur :app.', ['workspace' => $workspace->name, 'app' => config('app.name')]))
            ->action(__('Rejoindre :workspace', ['workspace' => $workspace->name]), $url)
            ->line(__('Cette invitation expirera le :date.', ['date' => $this->invitation->expires_at?->translatedFormat('d F Y')]));
    }
}
