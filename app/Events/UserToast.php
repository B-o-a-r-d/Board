<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Pushes a toast to one user's open sessions (the toasts container listens on
 * the user's private channel). Used by plugin automation actions to surface
 * their outcome — "GitHub issue created" with an "Open" link button.
 */
class UserToast implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /** Display time bounds (ms) — plugins choose within this window. */
    private const MIN_DURATION = 2000;

    private const MAX_DURATION = 30000;

    /**
     * @param  array<int, array{label: string, url: string}>  $actions
     */
    public function __construct(
        public int $userId,
        public string $message,
        public string $description = '',
        public string $type = 'default',
        public ?int $duration = null,
        public array $actions = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->userId)];
    }

    public function broadcastAs(): string
    {
        return 'user.toast';
    }

    /**
     * @return array{message: string, description: string, type: string, duration: int|null, actions: array<int, array{label: string, url: string}>}
     */
    public function broadcastWith(): array
    {
        $types = ['success', 'info', 'warning', 'danger', 'default'];

        return [
            'message' => mb_substr($this->message, 0, 200),
            'description' => mb_substr($this->description, 0, 200),
            'type' => in_array($this->type, $types, true) ? $this->type : 'default',
            'duration' => $this->duration === null
                ? null
                : max(self::MIN_DURATION, min(self::MAX_DURATION, $this->duration)),
            // Only http(s) links reach the browser — they open in a new tab.
            'actions' => array_values(array_filter(
                array_map(fn (array $action): array => [
                    'label' => mb_substr(trim((string) ($action['label'] ?? '')), 0, 60),
                    'url' => trim((string) ($action['url'] ?? '')),
                ], $this->actions),
                fn (array $action): bool => $action['label'] !== ''
                    && preg_match('#^https?://#i', $action['url']) === 1,
            )),
        ];
    }
}
