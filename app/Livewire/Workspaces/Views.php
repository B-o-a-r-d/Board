<?php

namespace App\Livewire\Workspaces;

use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Workspace-level Calendar & Table views: cards aggregated across every board in
 * the workspace the current user may view, filtered by board / member / due state.
 */
#[Layout('components.layouts.app')]
#[Title('Vues du workspace')]
class Views extends Component
{
    public Workspace $workspace;

    public string $view = 'calendar';

    public string $calendarMonth = '';

    /** @var array<int, int> Selected board ids (empty = all accessible boards). */
    public array $filterBoards = [];

    /** @var array<int, int> Selected member (user) ids. */
    public array $filterMembers = [];

    /** '' | overdue | withdue | nodue */
    public string $filterDue = '';

    public string $tableSort = 'due';

    public string $tableDir = 'asc';

    public function mount(Workspace $workspace, string $view = 'calendar'): void
    {
        $this->authorize('view', $workspace);

        $this->workspace = $workspace;
        $this->view = in_array($view, ['calendar', 'table'], true) ? $view : 'calendar';

        if ($this->calendarMonth === '') {
            $this->calendarMonth = now()->format('Y-m');
        }
    }

    public function calendarStep(int $months): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth.'-01')->addMonths($months)->format('Y-m');
    }

    public function calendarToday(): void
    {
        $this->calendarMonth = now()->format('Y-m');
    }

    public function sortTable(string $column): void
    {
        if (! in_array($column, ['title', 'board', 'list', 'due', 'created'], true)) {
            return;
        }

        if ($this->tableSort === $column) {
            $this->tableDir = $this->tableDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tableSort = $column;
            $this->tableDir = 'asc';
        }
    }

    public function resetFilters(): void
    {
        $this->reset('filterBoards', 'filterMembers', 'filterDue');
    }

    /**
     * Boards in this workspace the current user may view (RBAC/observer/deactivation
     * are all resolved by the `view` ability).
     *
     * @return EloquentCollection<int, Board>
     */
    private function accessibleBoards(): EloquentCollection
    {
        return $this->workspace->boards()
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get()
            ->filter(fn (Board $board): bool => Auth::user()->can('view', $board))
            ->values();
    }

    /**
     * Base card query across the accessible boards, with the active filters applied.
     *
     * @param  array<int, int>  $boardIds
     * @return Builder<Card>
     */
    private function cardsQuery(array $boardIds): Builder
    {
        $selected = array_values(array_intersect($this->filterBoards, $boardIds));

        $query = Card::query()
            ->whereIn('board_id', $selected !== [] ? $selected : $boardIds)
            ->whereNull('archived_at')
            ->with(['board:id,name,public_id', 'list:id,name', 'labels', 'members']);

        if ($this->filterMembers !== []) {
            $query->whereHas('members', fn (Builder $q) => $q->whereIn('users.id', $this->filterMembers));
        }

        match ($this->filterDue) {
            'overdue' => $query->whereNotNull('due_at')->whereNull('completed_at')->where('due_at', '<', now()),
            'withdue' => $query->whereNotNull('due_at'),
            'nodue' => $query->whereNull('due_at'),
            default => null,
        };

        return $query;
    }

    /**
     * @param  array<int, int>  $boardIds
     * @return array{label: string, weekDays: array<int, string>, weeks: array<int, mixed>}
     */
    private function buildCalendar(array $boardIds): array
    {
        $month = Carbon::parse($this->calendarMonth.'-01');
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $rangeEnd = $gridEnd->copy()->endOfDay();

        $byDay = $this->cardsQuery($boardIds)
            ->where(function (Builder $q) use ($gridStart, $rangeEnd) {
                $q->whereBetween('due_at', [$gridStart, $rangeEnd])
                    ->orWhere(function (Builder $nested) use ($gridStart, $rangeEnd) {
                        $nested->whereNull('due_at')->whereBetween('start_at', [$gridStart, $rangeEnd]);
                    });
            })
            ->get()
            ->groupBy(fn (Card $card) => ($card->due_at ?? $card->start_at)->toDateString());

        $days = [];
        for ($day = $gridStart->copy(); $day <= $gridEnd; $day->addDay()) {
            $days[] = [
                'date' => $day->copy(),
                'day' => $day->day,
                'inMonth' => $day->month === $month->month,
                'isToday' => $day->isToday(),
                'cards' => $byDay->get($day->toDateString(), collect()),
            ];
        }

        $weekDays = [];
        for ($wd = $gridStart->copy(), $i = 0; $i < 7; $i++, $wd->addDay()) {
            $weekDays[] = $wd->translatedFormat('D');
        }

        return [
            'label' => $month->translatedFormat('F Y'),
            'weekDays' => $weekDays,
            'weeks' => array_chunk($days, 7),
        ];
    }

    /**
     * @param  array<int, int>  $boardIds
     * @return EloquentCollection<int, Card>
     */
    private function buildTable(array $boardIds): EloquentCollection
    {
        $query = $this->cardsQuery($boardIds);

        return (match ($this->tableSort) {
            'title' => $query->orderBy('title', $this->tableDir),
            'board' => $query->orderBy(Board::select('name')->whereColumn('boards.id', 'cards.board_id'), $this->tableDir),
            'list' => $query->orderBy(BoardList::select('name')->whereColumn('board_lists.id', 'cards.board_list_id'), $this->tableDir),
            'created' => $query->orderBy('created_at', $this->tableDir),
            default => $query->orderByRaw('due_at is null asc')->orderBy('due_at', $this->tableDir),
        })->get();
    }

    public function render(): View
    {
        $boards = $this->accessibleBoards();
        $boardIds = $boards->pluck('id')->all();

        return view('livewire.workspaces.views', [
            'boards' => $boards,
            'members' => $this->workspace->members()->wherePivotNull('deactivated_at')->orderBy('name')->get(),
            'calendar' => $this->view === 'calendar' ? $this->buildCalendar($boardIds) : null,
            'tableCards' => $this->view === 'table' ? $this->buildTable($boardIds) : new EloquentCollection,
            'hasFilters' => $this->filterBoards !== [] || $this->filterMembers !== [] || $this->filterDue !== '',
        ]);
    }
}
