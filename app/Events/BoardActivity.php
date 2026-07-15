<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

class BoardActivity implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<int, int>  $listIds  lists whose cards changed (empty = board-level);
     *                                    lets a ListColumn refresh only when affected.
     */
    public function __construct(
        public int $boardId,
        public string $action,
        public ?int $actorId = null,
        public array $listIds = [],
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('board.'.$this->boardId)];
    }

    public function broadcastAs(): string
    {
        return 'board.activity';
    }

    /**
     * @return array{action: string, actorId: int|null, listIds: array<int, int>}
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'actorId' => $this->actorId,
            'listIds' => $this->listIds,
        ];
    }
}
