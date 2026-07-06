<?php

namespace App\Livewire;

use App\Enums\BoardVisibility;
use App\Models\Board;
use App\Models\Card;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public function clear(): void
    {
        $this->reset('query');
    }

    public function render(): View
    {
        $term = trim($this->query);
        $boards = collect();
        $cards = collect();

        if (mb_strlen($term) >= 2) {
            $like = '%'.mb_strtolower($term).'%';
            $boardIds = $this->accessibleBoardIds();

            $boards = Board::query()
                ->whereIn('id', $boardIds)
                ->whereRaw('LOWER(name) LIKE ?', [$like])
                ->limit(5)
                ->get();

            $cards = Card::query()
                ->whereIn('board_id', $boardIds)
                ->whereNull('archived_at')
                ->where(function ($scoped) use ($like) {
                    $scoped->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(description) LIKE ?', [$like]);
                })
                ->with('board')
                ->limit(8)
                ->get();
        }

        return view('livewire.global-search', [
            'boards' => $boards,
            'cards' => $cards,
            'term' => $term,
        ]);
    }

    /**
     * IDs of non-archived boards the current user can access.
     *
     * @return Collection<int, int>
     */
    private function accessibleBoardIds(): Collection
    {
        $user = Auth::user();

        return Board::query()
            ->notArchived()
            ->where(function ($query) use ($user) {
                $query->whereHas('members', fn ($members) => $members->whereKey($user->getKey()))
                    ->orWhere(function ($scoped) use ($user) {
                        $scoped->where('visibility', BoardVisibility::Workspace)
                            ->whereHas('workspace.members', fn ($members) => $members->whereKey($user->getKey()));
                    });
            })
            ->pluck('id');
    }
}
