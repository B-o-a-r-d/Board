<?php

namespace App\Livewire\Boards;

use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Component;

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
        $board = $this->board;

        $lists = $board->lists()
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
        ])->layout('components.layouts.public', [
            'title' => $board->name,
            'ogTitle' => $board->name,
            'ogDescription' => $this->socialDescription($lists),
            'ogImage' => $this->socialImage(),
            'ogUrl' => route('boards.public', $board->share_token),
        ]);
    }

    /**
     * A plain-text summary for the Open Graph / Twitter preview: the board's own
     * description when set, otherwise a generated one-liner.
     *
     * @param  Collection<int, BoardList>  $lists
     */
    private function socialDescription($lists): string
    {
        $description = (string) $this->board->description;

        if (trim($description) !== '') {
            return Str::limit(trim(strip_tags(Str::markdown($description))), 180);
        }

        return $this->board->workspace->name.' · '.__('Partagé en lecture seule · :lists listes · :cards cartes', [
            'lists' => $lists->count(),
            'cards' => $lists->sum(fn ($list) => $list->cards->count()),
        ]);
    }

    /** Absolute URL for the preview image: the board background if any, else the logo. */
    private function socialImage(): string
    {
        $image = $this->board->backgroundImageUrl($this->board->share_token)
            ?? asset('logo.png');

        return Str::startsWith($image, ['http://', 'https://']) ? $image : url($image);
    }
}
