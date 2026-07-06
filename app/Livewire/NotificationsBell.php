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
