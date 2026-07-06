<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NotificationsBell extends Component
{
    /**
     * Listen for real-time notifications broadcast on the user's private channel.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            'echo-private:App.Models.User.'.Auth::id().',.card.notification' => 'onNotification',
        ];
    }

    public function onNotification(): void {}

    /**
     * Mark the notification read and open its card on the target board.
     */
    public function openNotification(string $id): mixed
    {
        $notification = Auth::user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return null;
        }

        $notification->markAsRead();

        $data = $notification->data;

        return $this->redirectRoute('boards.show', [
            'board' => $data['board_id'],
            'card' => $data['card_id'],
        ], navigate: true);
    }

    public function markRead(string $id): void
    {
        Auth::user()->notifications()->whereKey($id)->first()?->markAsRead();
    }

    public function markAllRead(): void
    {
        Auth::user()->unreadNotifications->markAsRead();
    }

    public function render(): View
    {
        $user = Auth::user();

        return view('livewire.notifications-bell', [
            'notifications' => $user->notifications()->latest()->limit(12)->get(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }
}
