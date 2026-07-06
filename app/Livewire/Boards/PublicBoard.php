<?php

namespace App\Livewire\Boards;

use App\Models\Board;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.public')]
class PublicBoard extends Component
{
    public Board $board;

    public function mount(string $token): void
    {
        abort_unless((bool) config('board.public_sharing'), 404);

        $this->board = Board::query()
            ->whereNull('archived_at')
            ->where('share_token', $token)
            ->firstOrFail();
    }

    public function render(): View
    {
        $lists = $this->board->lists()
            ->whereNull('archived_at')
            ->with([
                'cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
                'cards.members',
                'cards.labels',
                'cards.checklists.items',
            ])
            ->orderBy('position')
            ->get();

        return view('livewire.boards.public-board', [
            'lists' => $lists,
        ])->title($this->board->name);
    }
}
