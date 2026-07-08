<?php

namespace App\Livewire\Boards;

use App\Automations\AutomationEngine;
use App\Enums\CustomFieldType;
use App\Enums\Role;
use App\Events\BoardActivity;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\CardLink;
use App\Models\CardTemplate;
use App\Models\User;
use App\Notifications\CardNotification;
use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Show extends Component
{
    use WithFileUploads;

    public Board $board;

    public string $newListName = '';

    /** @var array<int, string> */
    public array $newCardTitle = [];

    public string $search = '';

    /** @var array<int, int> Selected label ids (a card matching ANY is kept). */
    public array $filterLabels = [];

    /** @var array<int, int> Selected member ids (a card assigned to ANY is kept). */
    public array $filterMembers = [];

    /** "Sans membre": keep only cards with no assignee. */
    public bool $filterUnassigned = false;

    public string $filterDue = '';

    /** Active view mode: 'board' or 'calendar' (synced to the URL). */
    #[Url]
    public string $view = 'board';

    /** Currently displayed calendar month as 'Y-m'. */
    public string $calendarMonth = '';

    /** Timeline window start as 'Y-m-d' (Monday-aligned). */
    public string $timelineStart = '';

    /** Table view sort column ('title'|'list'|'due'|'created') and direction. */
    public string $tableSort = 'list';

    public string $tableDir = 'asc';

    /** Whether the current user may edit content (false for read-only Observers). */
    public bool $canContribute = false;

    public bool $showTrash = false;

    public function toggleTrash(): void
    {
        $this->showTrash = ! $this->showTrash;
    }

    public bool $showMembers = false;

    public function toggleMembers(): void
    {
        $this->authorize('view', $this->board);

        $this->showMembers = ! $this->showMembers;
    }

    public bool $showActivity = false;

    public function toggleActivity(): void
    {
        $this->authorize('view', $this->board);

        $this->showActivity = ! $this->showActivity;
    }

    /**
     * Jump from an activity row to its subject: close the slide-over and open
     * the target card, optionally focusing a comment or a named section.
     */
    public function focusActivity(int $cardId, ?string $section = null, ?int $comment = null): void
    {
        $this->authorize('view', $this->board);

        $this->showActivity = false;
        $this->dispatch('open-card', cardId: $cardId, section: $section, comment: $comment);
    }

    public bool $showCustomFields = false;

    public string $newFieldName = '';

    public string $newFieldType = 'text';

    /** Comma-separated options, only used when the new field type is "select". */
    public string $newFieldOptions = '';

    public function toggleCustomFields(): void
    {
        $this->authorize('update', $this->board);

        $this->showCustomFields = ! $this->showCustomFields;
    }

    public function addCustomField(): void
    {
        $this->authorize('update', $this->board);

        $data = $this->validate([
            'newFieldName' => ['required', 'string', 'max:60'],
            'newFieldType' => ['required', 'string', 'in:text,number,date,select,checkbox'],
        ]);

        $type = CustomFieldType::from($data['newFieldType']);

        $options = null;

        if ($type->hasOptions()) {
            $options = collect(explode(',', $this->newFieldOptions))
                ->map(fn (string $option): string => trim($option))
                ->filter()
                ->values()
                ->all();

            if (empty($options)) {
                $this->addError('newFieldOptions', __('Ajoutez au moins une option.'));

                return;
            }
        }

        $this->board->customFields()->create([
            'name' => $data['newFieldName'],
            'type' => $type,
            'options' => $options,
            'position' => (int) $this->board->customFields()->max('position') + 1,
        ]);

        $this->reset('newFieldName', 'newFieldOptions');
        $this->newFieldType = 'text';
        $this->dispatch('board-refresh');
    }

    public function deleteCustomField(int $fieldId): void
    {
        $this->authorize('update', $this->board);

        $this->board->customFields()->whereKey($fieldId)->delete();
        $this->dispatch('board-refresh');
    }

    public bool $showPlugins = false;

    /** The installed plugin instance currently being turned into a list. */
    public ?int $configuringPluginId = null;

    public string $newPluginListName = '';

    public string $newPluginListMode = '';

    /** @var array<string, mixed> */
    public array $newPluginListConfig = [];

    public function togglePlugins(): void
    {
        $this->authorize('managePlugins', $this->board);

        $this->showPlugins = ! $this->showPlugins;
        $this->configuringPluginId = null;
    }

    public function installPlugin(string $pluginKey): void
    {
        $this->authorize('managePlugins', $this->board);

        $plugin = app(PluginRegistry::class)->get($pluginKey);

        // One instance per plugin key per board (keeps the UI simple for now).
        if ($plugin === null || $this->board->plugins()->where('plugin_key', $pluginKey)->exists()) {
            return;
        }

        $this->board->plugins()->create([
            'plugin_key' => $pluginKey,
            'name' => $plugin->label(),
            'config' => [],
            'is_active' => true,
        ]);

        $this->dispatch('board-refresh');
    }

    public function uninstallPlugin(int $pluginId): void
    {
        $this->authorize('managePlugins', $this->board);

        // Plugin lists keep existing but lose their source (nullOnDelete),
        // degrading to empty normal lists.
        $this->board->plugins()->whereKey($pluginId)->delete();
        $this->configuringPluginId = null;
        $this->dispatch('board-refresh');
    }

    public function togglePluginActive(int $pluginId): void
    {
        $this->authorize('managePlugins', $this->board);

        $plugin = $this->board->plugins()->findOrFail($pluginId);
        $plugin->update(['is_active' => ! $plugin->is_active]);
        $this->dispatch('board-refresh');
    }

    /** The installed plugin instance whose credentials are being edited. */
    public ?int $editingPluginId = null;

    /** @var array<string, mixed> */
    public array $pluginConfigDraft = [];

    public function startPluginConfig(int $pluginId): void
    {
        $this->authorize('managePlugins', $this->board);

        $instance = $this->board->plugins()->findOrFail($pluginId);
        $definition = app(PluginRegistry::class)->get($instance->plugin_key);

        $this->editingPluginId = $pluginId;
        $this->pluginConfigDraft = [];

        // Prefill non-secret fields; secrets stay blank (never sent to the client).
        foreach ($definition?->configFields($instance->config ?? []) ?? [] as $field) {
            $this->pluginConfigDraft[$field['key']] = ($field['type'] ?? 'text') === 'password'
                ? ''
                : (string) ($instance->config[$field['key']] ?? '');
        }
    }

    public function savePluginConfig(): void
    {
        $this->authorize('managePlugins', $this->board);

        $instance = $this->board->plugins()->findOrFail($this->editingPluginId);
        $definition = app(PluginRegistry::class)->get($instance->plugin_key);
        $config = $instance->config ?? [];

        foreach ($definition?->configFields($config) ?? [] as $field) {
            $key = $field['key'];
            $value = trim((string) ($this->pluginConfigDraft[$key] ?? ''));

            // A blank secret keeps the stored value (so it isn't wiped on edit).
            if (($field['type'] ?? 'text') === 'password' && $value === '') {
                continue;
            }

            $config[$key] = $value;
        }

        $instance->update(['config' => $config]);

        $this->editingPluginId = null;
        $this->reset('pluginConfigDraft');
        $this->dispatch('board-refresh');
        $this->dispatch('toast', message: __('Configuration enregistrée'), type: 'success');
    }

    public function startPluginList(int $pluginId): void
    {
        $this->authorize('managePlugins', $this->board);

        $this->configuringPluginId = $pluginId;
        $this->reset('newPluginListName', 'newPluginListMode', 'newPluginListConfig');
    }

    public function createPluginList(): void
    {
        $this->authorize('managePlugins', $this->board);

        $instance = $this->board->plugins()->findOrFail($this->configuringPluginId);
        $plugin = app(PluginRegistry::class)->get($instance->plugin_key);

        if (! $plugin instanceof ProvidesListSource) {
            return;
        }

        $data = $this->validate([
            'newPluginListName' => ['required', 'string', 'max:60'],
            'newPluginListMode' => ['required', 'string'],
        ]);

        $validModes = collect($plugin->sourceModes())->pluck('key')->all();

        if (! in_array($data['newPluginListMode'], $validModes, true)) {
            $this->addError('newPluginListMode', __('Mode de source invalide.'));

            return;
        }

        $this->board->lists()->create([
            'name' => $data['newPluginListName'],
            'position' => (int) $this->board->lists()->max('position') + 1,
            'source_plugin_id' => $instance->id,
            'source_mode' => $data['newPluginListMode'],
            'source_config' => array_filter(
                $this->newPluginListConfig,
                fn ($value): bool => $value !== '' && $value !== null,
            ),
        ]);

        $this->configuringPluginId = null;
        $this->reset('newPluginListName', 'newPluginListMode', 'newPluginListConfig');
        $this->dispatch('board-refresh');
        $this->dispatch('toast', message: __('Liste créée depuis le plugin'), type: 'success');
    }

    /**
     * Add a workspace member to this board so they become assignable/mentionable.
     */
    public function addBoardMember(int $userId): void
    {
        $this->authorize('manageMembers', $this->board);

        // Only members of the board's workspace may be added.
        if (! $this->board->workspace->members()->whereKey($userId)->exists()) {
            return;
        }

        $this->board->members()->syncWithoutDetaching([$userId => ['role' => Role::Member->value]]);
        $this->broadcastActivity('board.members');
    }

    public function updateBoardMemberRole(int $userId, string $role): void
    {
        $this->authorize('manageMembers', $this->board);

        $membership = $this->board->members()->whereKey($userId)->first();

        // Any of the workspace's roles may be assigned, except Owner (protected).
        $assignable = $this->board->workspace->roles()->where('key', '!=', 'owner')->pluck('key');

        if (! $membership || $membership->pivot->role === Role::Owner->value || ! $assignable->contains($role)) {
            return;
        }

        $this->board->members()->updateExistingPivot($userId, ['role' => $role]);
        $this->broadcastActivity('board.members');
    }

    public function removeBoardMember(int $userId): void
    {
        $this->authorize('manageMembers', $this->board);

        $membership = $this->board->members()->whereKey($userId)->first();

        // Not a member, or the board owner — cannot be removed.
        if (! $membership || $membership->pivot->role === Role::Owner->value) {
            return;
        }

        $this->board->members()->detach($userId);

        // Drop their card assignments on this board so no orphan assignee remains.
        DB::table('card_user')
            ->whereIn('card_id', $this->board->cards()->select('id'))
            ->where('user_id', $userId)
            ->delete();

        $this->broadcastActivity('board.members');
    }

    /**
     * Whether classic-list cards have been loaded. Starts false so the board
     * paints instantly with a skeleton, then `wire:init="loadCards"` flips it.
     * Eager in tests (no JS to fire wire:init) so rendered assertions still see
     * cards; the deferred path is covered by a dedicated test.
     */
    public bool $cardsReady = false;

    public function mount(Board $board): void
    {
        $this->authorize('view', $board);

        $this->board = $board;
        $this->canContribute = Auth::user()->can('contribute', $board);

        if ($this->calendarMonth === '') {
            $this->calendarMonth = now()->format('Y-m');
        }

        if (! in_array($this->view, ['board', 'calendar', 'timeline', 'table', 'dashboard'], true)) {
            $this->view = 'board';
        }

        if ($this->timelineStart === '') {
            $this->timelineStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
        }

        $this->cardsReady = app()->runningUnitTests();
    }

    public function loadCards(): void
    {
        $this->cardsReady = true;
    }

    /**
     * Guard a content mutation: contributors only. Read-only roles (Observer)
     * can `view` but not `contribute`, so this 403s them.
     */
    private function authorizeContribution(): void
    {
        $this->authorize('contribute', $this->board);
    }

    public function setView(string $view): void
    {
        $this->view = in_array($view, ['board', 'calendar', 'timeline', 'table', 'dashboard'], true) ? $view : 'board';
    }

    public function calendarStep(int $months): void
    {
        $this->calendarMonth = Carbon::parse($this->calendarMonth.'-01')->addMonths($months)->format('Y-m');
    }

    public function calendarToday(): void
    {
        $this->calendarMonth = now()->format('Y-m');
    }

    public function timelineStep(int $weeks): void
    {
        $this->timelineStart = Carbon::parse($this->timelineStart)->addWeeks($weeks)->format('Y-m-d');
    }

    public function timelineToday(): void
    {
        $this->timelineStart = now()->startOfWeek(Carbon::MONDAY)->format('Y-m-d');
    }

    /**
     * Set a card's start and/or due date from the timeline (drag to move, drag an
     * edge to resize). Dates keep any existing time-of-day, else default to noon.
     */
    public function setCardSchedule(int $cardId, ?string $startDate, ?string $dueDate): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);

        $start = $startDate ? $this->dateWithTime($card->start_at, $startDate) : null;
        $due = $dueDate ? $this->dateWithTime($card->due_at, $dueDate) : null;

        if ($start !== null && $due !== null && $due->lt($start)) {
            return;
        }

        $hadDue = $card->due_at !== null;

        $card->update(['start_at' => $start, 'due_at' => $due]);

        if ($due !== null) {
            $this->logActivity($hadDue ? 'card.due_changed' : 'card.due_set', $card->id, ['value' => $due->translatedFormat('d M Y')]);
        }

        $this->broadcastActivity('card.due_changed');
    }

    private function dateWithTime(?Carbon $existing, string $date): Carbon
    {
        $parsed = Carbon::parse($date);

        return $existing
            ? $parsed->setTime($existing->hour, $existing->minute)
            : $parsed->setTime(12, 0);
    }

    /**
     * Toggle the table sort: same column flips direction, a new column sorts
     * ascending.
     */
    public function sortTable(string $column): void
    {
        if (! in_array($column, ['title', 'list', 'due', 'created'], true)) {
            return;
        }

        if ($this->tableSort === $column) {
            $this->tableDir = $this->tableDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tableSort = $column;
            $this->tableDir = 'asc';
        }
    }

    /**
     * Inline-rename a card from the table view.
     */
    public function renameCard(int $cardId, string $title): void
    {
        $this->authorizeContribution();

        $title = trim($title);

        if ($title === '') {
            return;
        }

        $card = $this->cardForBoard($cardId);
        $card->update(['title' => mb_substr($title, 0, 255)]);

        $this->broadcastActivity('card.updated');
    }

    /**
     * Set (or clear, with null) a card's due date from the table view, keeping
     * the existing time-of-day and leaving the start date untouched.
     */
    public function setCardDue(int $cardId, ?string $date): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $hadDue = $card->due_at !== null;
        $newDue = ($date !== null && $date !== '') ? $this->dateWithTime($card->due_at, $date) : null;

        $card->update(['due_at' => $newDue]);

        if ($newDue === null && $hadDue) {
            $this->logActivity('card.due_removed', $card->id);
        } elseif ($newDue !== null) {
            $this->logActivity($hadDue ? 'card.due_changed' : 'card.due_set', $card->id, ['value' => $newDue->translatedFormat('d M Y')]);
        }

        $this->broadcastActivity('card.due_changed');
    }

    /**
     * Toggle a board member on a card from the table view (assign / unassign),
     * mirroring the card modal: notifies a freshly-assigned member.
     */
    public function toggleCardMember(int $cardId, int $userId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);

        if (! $this->board->hasMember(User::findOrNew($userId))) {
            return;
        }

        $result = $card->members()->toggle($userId);

        if (in_array($userId, $result['attached'], true) && $userId !== Auth::id()) {
            $assignee = User::find($userId);
            $this->logActivity('member.assigned', $card->id, ['user_id' => $userId, 'user_name' => $assignee?->name]);
            $assignee?->notify(new CardNotification($card, 'assigned', Auth::user()));
        }

        $this->broadcastActivity('card.members');
    }

    public function toggleCardLabel(int $cardId, int $labelId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $label = $this->board->labels()->findOrFail($labelId);

        $card->labels()->toggle($label->id);

        $this->broadcastActivity('card.labels');
    }

    /**
     * Set (or clear) a card custom field value from the table view, coercing by
     * field type exactly like the card modal.
     */
    public function setCardCustomField(int $cardId, int $fieldId, mixed $value): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $field = $this->board->customFields()->findOrFail($fieldId);

        $stored = match ($field->type) {
            CustomFieldType::Checkbox => $value ? '1' : null,
            CustomFieldType::Select => in_array($value, $field->options ?? [], true) ? $value : null,
            default => ($value === '' || $value === null) ? null : (string) $value,
        };

        if ($stored === null) {
            $card->customFieldValues()->where('custom_field_id', $field->id)->delete();
        } else {
            $card->customFieldValues()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $stored],
            );
        }

        $this->broadcastActivity('card.updated');
    }

    /**
     * Move a card to another day from the calendar (drag & drop). Keeps the
     * time-of-day and shifts whichever date anchors the card on the grid — its
     * due date, falling back to its start date.
     */
    public function rescheduleCard(int $cardId, string $date): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $target = Carbon::parse($date);
        $hadDue = $card->due_at !== null;

        if ($card->due_at) {
            $card->due_at = $card->due_at->copy()->setDate($target->year, $target->month, $target->day);
        } elseif ($card->start_at) {
            $card->start_at = $card->start_at->copy()->setDate($target->year, $target->month, $target->day);
        } else {
            $card->due_at = $target->copy()->setTime(12, 0);
        }

        $card->save();

        $anchor = $card->due_at ?? $card->start_at;
        $this->logActivity($hadDue ? 'card.due_changed' : 'card.due_set', $card->id, ['value' => $anchor->translatedFormat('d M Y')]);
        $this->broadcastActivity('card.due_changed');
    }

    /**
     * Create a dated card straight from an empty calendar day. Lands in the
     * board's first list (calendar is not list-scoped) with the day as due date.
     */
    public function createCardOnDate(string $date, string $title): void
    {
        $this->authorizeContribution();

        $title = trim($title);

        if ($title === '') {
            return;
        }

        $list = $this->board->lists()->whereNull('archived_at')->orderBy('position')->first();

        if (! $list) {
            $this->dispatch('toast', message: __('Créez d’abord une liste.'), type: 'error');

            return;
        }

        $card = $list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $title,
            'due_at' => Carbon::parse($date)->setTime(12, 0),
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        $this->logActivity('card.created', $card->id, ['card_title' => $card->title, 'list' => $list->name]);

        app(AutomationEngine::class)->fire('card.created', $card, ['list_id' => $list->id]);

        $this->broadcastActivity('card.created');

        $this->dispatch('toast', message: __('Carte créée'), type: 'success');
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'filterLabels', 'filterMembers', 'filterUnassigned', 'filterDue');
    }

    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->filterLabels !== []
            || $this->filterMembers !== []
            || $this->filterUnassigned
            || $this->filterDue !== '';
    }

    /**
     * Number of active dropdown filters (labels / members / due) — drives the
     * mobile "Filtres" toggle badge. Text search is shown separately.
     */
    public function activeFilterCount(): int
    {
        return (int) ($this->filterLabels !== [])
            + (int) ($this->filterMembers !== [] || $this->filterUnassigned)
            + (int) ($this->filterDue !== '');
    }

    /**
     * Set the (single-select) due filter from a styled dropdown.
     */
    public function applyFilter(string $field, string $value): void
    {
        if ($field === 'filterDue') {
            $this->filterDue = $value;
        }
    }

    public function toggleLabel(int $labelId): void
    {
        $this->filterLabels = in_array($labelId, $this->filterLabels, true)
            ? array_values(array_diff($this->filterLabels, [$labelId]))
            : [...$this->filterLabels, $labelId];
    }

    public function toggleMember(int $memberId): void
    {
        $this->filterMembers = in_array($memberId, $this->filterMembers, true)
            ? array_values(array_diff($this->filterMembers, [$memberId]))
            : [...$this->filterMembers, $memberId];

        // Picking a specific member and "no member" are mutually exclusive.
        if ($this->filterMembers !== []) {
            $this->filterUnassigned = false;
        }
    }

    public function toggleUnassigned(): void
    {
        $this->filterUnassigned = ! $this->filterUnassigned;

        if ($this->filterUnassigned) {
            $this->filterMembers = [];
        }
    }

    public string $newViewName = '';

    public function saveView(): void
    {
        $this->authorize('view', $this->board);

        $data = $this->validate(['newViewName' => ['required', 'string', 'max:60']], attributes: ['newViewName' => 'nom']);

        $this->board->views()->create([
            'user_id' => Auth::id(),
            'name' => $data['newViewName'],
            'filters' => [
                'view' => $this->view,
                'search' => $this->search,
                'labels' => $this->filterLabels,
                'members' => $this->filterMembers,
                'unassigned' => $this->filterUnassigned,
                'due' => $this->filterDue,
            ],
        ]);

        $this->newViewName = '';
        $this->dispatch('toast', message: __('Vue enregistrée'), type: 'success');
    }

    public function applyView(int $viewId): void
    {
        $this->authorize('view', $this->board);

        $view = $this->board->views()->where('user_id', Auth::id())->findOrFail($viewId);

        $this->view = ($view->filters['view'] ?? 'board') === 'calendar' ? 'calendar' : 'board';
        $this->search = $view->filters['search'] ?? '';
        // Multi-select, with a fallback for views saved under the old single keys.
        $this->filterLabels = $view->filters['labels']
            ?? (! empty($view->filters['label']) ? [(int) $view->filters['label']] : []);
        $this->filterMembers = $view->filters['members']
            ?? (! empty($view->filters['member']) ? [(int) $view->filters['member']] : []);
        $this->filterUnassigned = (bool) ($view->filters['unassigned'] ?? false);
        $this->filterDue = $view->filters['due'] ?? '';

        if ($this->view === 'calendar' && $this->calendarMonth === '') {
            $this->calendarMonth = now()->format('Y-m');
        }
    }

    public function renameView(int $viewId, string $name): void
    {
        $this->authorize('view', $this->board);

        $name = trim($name);

        if ($name === '') {
            return;
        }

        $this->board->views()->where('user_id', Auth::id())->whereKey($viewId)
            ->update(['name' => mb_substr($name, 0, 60)]);
    }

    public function deleteView(int $viewId): void
    {
        $this->authorize('view', $this->board);

        $this->board->views()->where('user_id', Auth::id())->whereKey($viewId)->delete();
    }

    public bool $renamingBoard = false;

    public string $boardNameDraft = '';

    public function startRenameBoard(): void
    {
        $this->authorize('update', $this->board);

        $this->boardNameDraft = $this->board->name;
        $this->renamingBoard = true;
    }

    public function renameBoard(): void
    {
        $this->authorize('update', $this->board);

        $name = trim($this->boardNameDraft);

        if ($name !== '') {
            $this->board->update(['name' => $name]);
            $this->broadcastActivity('board.renamed');
        }

        $this->renamingBoard = false;
    }

    public bool $showShare = false;

    public function openShare(): void
    {
        $this->authorize('update', $this->board);
        abort_unless((bool) config('board.public_sharing'), 404);

        $this->showShare = true;
    }

    public function toggleShare(): void
    {
        $this->authorize('update', $this->board);
        abort_unless((bool) config('board.public_sharing'), 404);

        if ($this->board->isShared()) {
            $this->board->disableSharing();
            $this->dispatch('toast', message: 'Partage public désactivé', type: 'info');
        } else {
            $this->board->enableSharing();
            $this->dispatch('toast', message: 'Lien de partage public activé', type: 'success');
        }
    }

    public bool $showBackground = false;

    public mixed $backgroundUpload = null;

    public function openBackground(): void
    {
        $this->authorize('update', $this->board);

        $this->showBackground = true;
    }

    public function setBackground(?string $key): void
    {
        $this->authorize('update', $this->board);

        if ($key !== null && ! array_key_exists($key, config('board.backgrounds', []))) {
            return;
        }

        // A gradient preset (or "none") replaces any uploaded image.
        $this->board->update(['background' => $key, 'background_image' => null]);
        $this->broadcastActivity('board.background');
    }

    public function uploadBackground(): void
    {
        $this->authorize('update', $this->board);

        $this->validate([
            'backgroundUpload' => ['required', 'image', 'max:10240'],
        ]);

        $path = $this->backgroundUpload->store("board-backgrounds/{$this->board->id}", 'public');

        $this->board->update(['background_image' => $path, 'background' => null]);
        $this->reset('backgroundUpload');
        $this->broadcastActivity('board.background');
        $this->dispatch('toast', message: 'Fond du board mis à jour', type: 'success');
    }

    public function toggleTemplate(): void
    {
        abort_unless(Auth::user()->isAdmin(), 403);

        $this->board->update(['is_template' => ! $this->board->is_template]);
        $this->dispatch('toast', message: $this->board->is_template ? 'Board défini comme modèle global' : 'Modèle retiré', type: 'success');
    }

    public function deleteBoard(): mixed
    {
        $this->authorize('delete', $this->board);

        $this->board->delete();

        return $this->redirectRoute('dashboard', navigate: true);
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return [
            "echo-private:board.{$this->board->id},.board.activity" => 'onBoardActivity',
            'board-refresh' => 'onBoardActivity',
        ];
    }

    /**
     * Both remote broadcasts and local card edits simply trigger a re-render,
     * which re-queries fresh data.
     */
    public function onBoardActivity(): void {}

    public function addList(): void
    {
        $this->authorizeContribution();

        $data = $this->validate([
            'newListName' => ['required', 'string', 'max:255'],
        ]);

        $this->board->lists()->create([
            'name' => $data['newListName'],
            'position' => (int) $this->board->lists()->max('position') + 1,
        ]);

        $this->newListName = '';
        $this->broadcastActivity('list.created');
    }

    public function renameList(int $listId, string $name): void
    {
        $this->authorizeContribution();

        $name = trim($name);

        if ($name === '') {
            return;
        }

        $this->listForBoard($listId)->update(['name' => $name]);
        $this->broadcastActivity('list.renamed');
    }

    public function setListColor(int $listId, ?string $color): void
    {
        $this->authorizeContribution();

        $this->listForBoard($listId)->update(['cover_color' => $color ?: null]);
        $this->broadcastActivity('list.recolored');
    }

    public mixed $listCoverUpload = null;

    public ?int $coverListId = null;

    public function openListCover(int $listId): void
    {
        $this->authorizeContribution();

        $this->coverListId = $this->listForBoard($listId)->id;
    }

    public function closeListCover(): void
    {
        $this->reset('coverListId', 'listCoverUpload');
    }

    public function uploadListCover(): void
    {
        $this->authorizeContribution();

        $this->validate(['listCoverUpload' => ['required', 'image', 'max:10240']]);

        $list = $this->listForBoard((int) $this->coverListId);

        if ($list->cover_path) {
            Storage::disk('public')->delete($list->cover_path);
        }

        $list->update(['cover_path' => $this->listCoverUpload->store("list-covers/{$this->board->id}", 'public')]);

        $this->reset('listCoverUpload');
        $this->broadcastActivity('list.recolored');
        $this->dispatch('toast', message: __('Couverture de la liste mise à jour'), type: 'success');
    }

    public function removeListCover(int $listId): void
    {
        $this->authorizeContribution();

        $list = $this->listForBoard($listId);

        if ($list->cover_path) {
            Storage::disk('public')->delete($list->cover_path);
            $list->update(['cover_path' => null]);
            $this->broadcastActivity('list.recolored');
        }
    }

    public function setWipLimit(int $listId, mixed $limit): void
    {
        $this->authorizeContribution();

        $limit = (int) $limit;

        $this->listForBoard($listId)->update(['wip_limit' => $limit > 0 ? min($limit, 999) : null]);
        $this->broadcastActivity('list.recolored');
    }

    public function archiveList(int $listId): void
    {
        $this->authorizeContribution();

        $this->listForBoard($listId)->update(['archived_at' => now()]);
        $this->broadcastActivity('list.archived');
    }

    public function restoreList(int $listId): void
    {
        $this->authorizeContribution();

        $this->board->lists()->whereKey($listId)->update(['archived_at' => null]);
        $this->broadcastActivity('list.restored');
    }

    public function deleteListPermanently(int $listId): void
    {
        $this->authorizeContribution();

        $this->board->lists()->whereKey($listId)->delete();
        $this->broadcastActivity('list.deleted');
    }

    public function duplicateList(int $listId): void
    {
        $this->authorizeContribution();

        $source = $this->board->lists()->with(['cards' => fn ($q) => $q->whereNull('archived_at')->orderBy('position'), 'cards.labels', 'cards.members'])->findOrFail($listId);

        $copy = $this->board->lists()->create([
            'name' => $source->name.' (copie)',
            'cover_color' => $source->cover_color,
            'position' => (int) $this->board->lists()->max('position') + 1,
        ]);

        foreach ($source->cards as $index => $card) {
            $newCard = $copy->cards()->create([
                'board_id' => $this->board->id,
                'created_by' => Auth::id(),
                'title' => $card->title,
                'description' => $card->description,
                'cover_path' => $card->cover_path,
                'cover_color' => $card->cover_color,
                'due_at' => $card->due_at,
                'position' => $index,
            ]);
            $newCard->labels()->attach($card->labels->pluck('id'));
            $newCard->members()->attach($card->members->pluck('id'));
        }

        $this->broadcastActivity('list.duplicated');
        $this->dispatch('toast', message: 'Liste dupliquée', type: 'success');
    }

    public function reorderLists(int $id, int $position): void
    {
        $this->authorizeContribution();

        $ids = $this->board->lists()
            ->where('id', '!=', $id)
            ->orderBy('position')
            ->pluck('id')
            ->all();

        $position = max(0, min($position, count($ids)));
        array_splice($ids, $position, 0, [$id]);

        foreach ($ids as $index => $listId) {
            BoardList::whereKey($listId)->update(['position' => $index]);
        }

        $this->broadcastActivity('list.reordered');

        // Optimistic UI: SortableJS already moved the DOM to the final order,
        // so skip our own re-render to avoid a morph flicker. Other viewers
        // still re-render from the broadcast.
        $this->skipRender();
    }

    public function addCard(int $listId): void
    {
        $this->authorizeContribution();

        $title = trim($this->newCardTitle[$listId] ?? '');

        if ($title === '') {
            return;
        }

        $list = $this->listForBoard($listId);

        $card = $list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $title,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        $this->logActivity('card.created', $card->id, ['card_title' => $card->title, 'list' => $list->name]);

        app(AutomationEngine::class)->fire('card.created', $card, ['list_id' => $list->id]);

        $this->newCardTitle[$listId] = '';
        $this->broadcastActivity('card.created');

        // Open the new card's detail modal so the user can fill it in right away.
        $this->dispatch('open-card', cardId: $card->id);
    }

    public function addCardFromTemplate(int $listId, int $templateId): void
    {
        $this->authorizeContribution();

        $list = $this->listForBoard($listId);
        $template = CardTemplate::findOrFail($templateId);

        $card = $list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $template->title,
            'description' => $template->description,
            'cover_color' => $template->cover_color,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        foreach ($template->checklists ?? [] as $checklistIndex => $checklist) {
            $newChecklist = $card->checklists()->create([
                'title' => $checklist['title'] ?? 'Checklist',
                'position' => $checklistIndex,
            ]);

            foreach ($checklist['items'] ?? [] as $itemIndex => $content) {
                $newChecklist->items()->create(['content' => $content, 'position' => $itemIndex]);
            }
        }

        $this->logActivity('card.created', $card->id, ['card_title' => $card->title, 'list' => $list->name, 'from_template' => true]);
        $this->broadcastActivity('card.created');
        $this->dispatch('toast', message: 'Carte créée depuis le modèle', type: 'success');
    }

    public function archiveCard(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $card->update(['archived_at' => now()]);
        $this->logActivity('card.archived', $cardId, ['card_title' => $card->title, 'list' => $card->list?->name]);
        $this->broadcastActivity('card.archived');
    }

    public function restoreCard(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $card->update(['archived_at' => null]);
        $this->logActivity('card.restored', $cardId, ['card_title' => $card->title, 'list' => $card->list?->name]);
        $this->broadcastActivity('card.restored');
    }

    public function deleteCardPermanently(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);

        // The activities.card_id FK is cascadeOnDelete, so a deletion log must
        // NOT reference the card — keep the context in the properties instead.
        $this->logActivity('card.deleted', null, [
            'number' => $card->id,
            'card_title' => $card->title,
            'list' => $card->list?->name,
        ]);

        $card->delete();
        $this->broadcastActivity('card.deleted');
    }

    public function duplicateCard(int $cardId): void
    {
        $this->authorizeContribution();

        $card = $this->board->cards()->with(['labels', 'members'])->findOrFail($cardId);

        $copy = $card->list->cards()->create([
            'board_id' => $this->board->id,
            'created_by' => Auth::id(),
            'title' => $card->title.' (copie)',
            'description' => $card->description,
            'cover_path' => $card->cover_path,
            'cover_color' => $card->cover_color,
            'due_at' => $card->due_at,
            'position' => (int) $card->list->cards()->max('position') + 1,
        ]);

        $copy->labels()->attach($card->labels->pluck('id'));
        $copy->members()->attach($card->members->pluck('id'));

        $this->logActivity('card.duplicated', $copy->id, ['from' => $card->id]);
        $this->broadcastActivity('card.duplicated');
        $this->dispatch('toast', message: 'Carte dupliquée', type: 'success');
    }

    public function moveCard(int $id, int $position, int $listId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($id);
        $targetList = $this->listForBoard($listId);
        $sourceListId = $card->board_list_id;
        $sourceListName = $card->list?->name;

        $card->board_list_id = $targetList->id;
        $card->save();

        $this->resequence($targetList->id, $id, $position);

        $ranAutomations = 0;

        if ($sourceListId !== $targetList->id) {
            $this->resequence($sourceListId);
            $this->logActivity('card.moved', $card->id, ['card_title' => $card->title, 'from_list' => $sourceListName, 'to_list' => $targetList->name]);

            $ranAutomations = app(AutomationEngine::class)->fire('card.moved', $card->fresh(), [
                'to_list_id' => $targetList->id,
                'from_list_id' => $sourceListId,
            ]);
        }

        $this->broadcastActivity('card.moved');

        // Optimistic UI: the card is already in place client-side, so skip the
        // acting user's re-render to avoid a morph flicker (others re-render
        // from the broadcast). But if an automation mutated the card, re-render
        // so the actor sees the result.
        if ($ranAutomations === 0) {
            $this->skipRender();
        }
    }

    /**
     * Move a card to the end of another list without drag & drop — the reliable
     * path on touch devices, exposed via the card's "Déplacer vers…" menu. Unlike
     * moveCard this always re-renders so the actor sees the card jump columns.
     */
    public function moveCardToList(int $cardId, int $listId): void
    {
        $this->authorizeContribution();

        $card = $this->cardForBoard($cardId);
        $targetList = $this->listForBoard($listId);
        $sourceListId = $card->board_list_id;
        $sourceListName = $card->list?->name;

        if ($sourceListId === $targetList->id) {
            return;
        }

        $card->board_list_id = $targetList->id;
        $card->position = (int) $targetList->cards()->max('position') + 1;
        $card->save();

        $this->resequence($targetList->id);
        $this->resequence($sourceListId);

        $this->logActivity('card.moved', $card->id, ['card_title' => $card->title, 'from_list' => $sourceListName, 'to_list' => $targetList->name]);

        app(AutomationEngine::class)->fire('card.moved', $card->fresh(), [
            'to_list_id' => $targetList->id,
            'from_list_id' => $sourceListId,
        ]);

        $this->broadcastActivity('card.moved');
    }

    /**
     * Bulk-archive the given cards (from the multi-select action bar).
     *
     * @param  array<int, int>  $cardIds
     */
    public function bulkArchive(array $cardIds): void
    {
        $this->authorizeContribution();

        $cards = $this->board->cards()->whereIn('id', $cardIds)->whereNull('archived_at')->get();

        foreach ($cards as $card) {
            $card->update(['archived_at' => now()]);
        }

        $cards->pluck('board_list_id')->unique()->each(fn ($listId) => $this->resequence($listId));

        if ($cards->isNotEmpty()) {
            $this->broadcastActivity('card.archived');
        }
    }

    /**
     * Bulk-move the given cards to a list, appended in order.
     *
     * @param  array<int, int>  $cardIds
     */
    public function bulkMove(array $cardIds, int $listId): void
    {
        $this->authorizeContribution();

        $target = $this->listForBoard($listId);
        $cards = $this->board->cards()->whereIn('id', $cardIds)->whereNull('archived_at')->get();
        $sourceListIds = $cards->pluck('board_list_id')->unique();
        $position = (int) $target->cards()->max('position');

        foreach ($cards as $card) {
            $card->update(['board_list_id' => $target->id, 'position' => ++$position]);
        }

        $sourceListIds->reject(fn ($id) => $id === $target->id)->each(fn ($id) => $this->resequence($id));
        $this->resequence($target->id);

        if ($cards->isNotEmpty()) {
            $this->broadcastActivity('card.moved');
        }
    }

    /**
     * Bulk-attach a label to the given cards.
     *
     * @param  array<int, int>  $cardIds
     */
    public function bulkAddLabel(array $cardIds, int $labelId): void
    {
        $this->authorizeContribution();

        if (! $this->board->labels()->whereKey($labelId)->exists()) {
            return;
        }

        $this->board->cards()->whereIn('id', $cardIds)->whereNull('archived_at')->get()
            ->each(fn (Card $card) => $card->labels()->syncWithoutDetaching([$labelId]));

        $this->broadcastActivity('card.labels');
    }

    /**
     * Renumber a list's cards, optionally inserting a moved card at a position.
     */
    private function resequence(int $listId, ?int $movedId = null, ?int $position = null): void
    {
        $ids = Card::query()
            ->where('board_list_id', $listId)
            ->when($movedId !== null, fn ($query) => $query->where('id', '!=', $movedId))
            ->orderBy('position')
            ->pluck('id')
            ->all();

        if ($movedId !== null && $position !== null) {
            $position = max(0, min($position, count($ids)));
            array_splice($ids, $position, 0, [$movedId]);
        }

        foreach ($ids as $index => $cardId) {
            Card::whereKey($cardId)->update(['position' => $index]);
        }
    }

    private function listForBoard(int $listId): BoardList
    {
        return $this->board->lists()->findOrFail($listId);
    }

    private function cardForBoard(int $cardId): Card
    {
        return $this->board->cards()->findOrFail($cardId);
    }

    private function broadcastActivity(string $action): void
    {
        broadcast(new BoardActivity($this->board->id, $action, Auth::id()))->toOthers();
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logActivity(string $type, ?int $cardId = null, array $properties = []): void
    {
        Activity::create([
            'board_id' => $this->board->id,
            'card_id' => $cardId,
            'user_id' => Auth::id(),
            'type' => $type,
            'properties' => $properties,
        ]);
    }

    public function render(): View
    {
        $lists = $this->view === 'board'
            ? $this->board->lists()
                ->whereNull('archived_at')
                // Counts stay available (and cheap) even while cards lazy-load.
                ->withCount(['cards' => function ($query) {
                    $query->whereNull('archived_at');
                    $this->applyCardFilters($query);
                }])
                // Cards themselves are deferred until loadCards() (skeleton first).
                ->when($this->cardsReady, fn ($q) => $q->with([
                    'cards' => function ($query) {
                        $query->whereNull('archived_at')->orderBy('position')->withCount('attachments');
                        $this->applyCardFilters($query);
                    },
                    'cards.members',
                    'cards.labels',
                    'cards.checklists.items',
                    'cards.customFieldValues',
                ]))
                ->orderBy('position')
                ->get()
            // The table view needs the lists (name + move target) but not the
            // heavy card eager-loads the board view uses.
            : ($this->view === 'table'
                ? $this->board->lists()->whereNull('archived_at')->orderBy('position')->get(['id', 'name', 'position'])
                : collect());

        $boardMembers = $this->board->members()->orderBy('name')->get();

        // Dedicated slide-over tabs for plugins that have logged activity here.
        $activityTabs = [];

        if ($this->showActivity) {
            foreach (app(PluginRegistry::class)->all() as $key => $plugin) {
                if ($plugin instanceof DefinesActivities
                    && $this->board->activities()->whereIn('type', $plugin->activityTypes())->exists()) {
                    $activityTabs[] = ['plugin_key' => $key, 'label' => $plugin->activityTab()['label']];
                }
            }
        }

        return view('livewire.boards.show', [
            'lists' => $lists,
            'calendar' => $this->view === 'calendar' ? $this->buildCalendar() : null,
            'timeline' => $this->view === 'timeline' ? $this->buildTimeline() : null,
            'tableCards' => $this->view === 'table' ? $this->buildTable() : null,
            'dashboard' => $this->view === 'dashboard' ? $this->buildDashboard() : null,
            'labels' => $this->board->labels,
            'members' => $boardMembers,
            'boardMembers' => $boardMembers,
            'boardRoles' => $this->board->workspace->roles()->orderBy('position')->get(),
            'addableMembers' => $this->showMembers
                ? $this->board->workspace->members()->whereNotIn('users.id', $boardMembers->pluck('id'))->orderBy('name')->get()
                : collect(),
            'canManageMembers' => Auth::user()->can('manageMembers', $this->board),
            'archivedLists' => $this->showTrash ? $this->board->lists()->whereNotNull('archived_at')->orderBy('name')->get() : collect(),
            'archivedCards' => $this->showTrash ? $this->board->cards()->whereNotNull('archived_at')->with('list')->latest('archived_at')->get() : collect(),
            'cardTemplates' => CardTemplate::orderBy('name')->get(),
            'views' => $this->board->views()->where('user_id', Auth::id())->latest()->get(),
            'activities' => $this->showActivity
                ? $this->board->activities()->with(['user', 'card'])->latest()->limit(60)->get()
                : collect(),
            'activityTabs' => $activityTabs,
            'customFields' => $this->board->customFields,
            'pluginRegistry' => app(PluginRegistry::class),
            'availablePlugins' => $this->showPlugins ? app(PluginRegistry::class)->all() : [],
            'installedPlugins' => $this->showPlugins ? $this->board->plugins()->get() : collect(),
        ]);
    }

    /**
     * Build the timeline (Gantt) view: an 8-week day window with one swimlane per
     * list, each dated card rendered as a bar spanning start_at → due_at (a single
     * date yields a one-day bar). Positions/spans are in day units; the blade
     * multiplies by a fixed day width. Respects the active card filters.
     *
     * @return array{start: Carbon, days: int, dayList: array<int, array{date: Carbon, day: int, weekday: string, month: string, isToday: bool, isWeekend: bool, isMonthStart: bool}>, todayOffset: int|null, lanes: array<int, array{list: BoardList, bars: array<int, array{card: Card, offset: int, span: int, overdue: bool}>}>, edges: array<int, array{fx: int, fy: int, tx: int, ty: int}>, gridWidth: int, gridHeight: int}
     */
    private function buildTimeline(): array
    {
        $days = 56;
        $start = Carbon::parse($this->timelineStart)->startOfDay();
        $end = $start->copy()->addDays($days - 1)->endOfDay();

        $dayList = [];
        for ($i = 0; $i < $days; $i++) {
            $day = $start->copy()->addDays($i);
            $dayList[] = [
                'date' => $day,
                'day' => $day->day,
                'weekday' => $day->translatedFormat('D'),
                'month' => $day->translatedFormat('MMM'),
                'isToday' => $day->isToday(),
                'isWeekend' => $day->isWeekend(),
                'isMonthStart' => $day->day === 1,
            ];
        }

        $query = $this->board->cards()
            ->whereNull('archived_at')
            ->where(fn ($q) => $q->whereNotNull('due_at')->orWhereNotNull('start_at'))
            ->with(['labels', 'members']);

        $this->applyCardFilters($query);

        $cards = $query->get()->groupBy('board_list_id');

        // Layout constants — MUST match timeline.blade (day width, label column,
        // header height, per-bar stride) so the SVG dependency arrows line up.
        $labelWidth = 176;
        $dayWidth = 40;
        $rowStride = 36;

        $lanes = [];
        $coords = []; // card id => ['left', 'right', 'y'] in grid pixels
        $laneTop = 45; // header (h-11 = 44px) + its 1px bottom border

        foreach ($this->board->lists()->whereNull('archived_at')->orderBy('position')->get() as $list) {
            $bars = [];
            $index = 0;

            foreach ($cards->get($list->id, collect()) as $card) {
                $cardStart = ($card->start_at ?? $card->due_at)->copy()->startOfDay();
                $cardEnd = ($card->due_at ?? $card->start_at)->copy()->startOfDay();

                if ($cardEnd->lt($start) || $cardStart->gt($end->copy()->startOfDay())) {
                    continue;
                }

                $spanStart = $cardStart->lt($start) ? $start->copy() : $cardStart;
                $spanEnd = $cardEnd->gt($end) ? $end->copy()->startOfDay() : $cardEnd;

                $offset = (int) $start->diffInDays($spanStart);
                $span = (int) $spanStart->diffInDays($spanEnd) + 1;

                $bars[] = [
                    'card' => $card,
                    'offset' => $offset,
                    'span' => $span,
                    'overdue' => $card->due_at && ! $card->completed_at && $card->due_at->isPast(),
                ];

                $left = $labelWidth + $offset * $dayWidth;
                $coords[$card->id] = [
                    'left' => $left,
                    'right' => $left + max($span * $dayWidth - 4, 24),
                    'y' => $laneTop + $index * $rowStride + 20,
                ];
                $index++;
            }

            if ($bars !== []) {
                $lanes[] = ['list' => $list, 'bars' => $bars];
                $laneTop += count($bars) * $rowStride + 8 + 1; // track height + border-b
            }
        }

        $today = now()->startOfDay();

        // Dependency arrows: 'blocks' links where BOTH cards are visible in the window.
        $edges = [];
        $visibleIds = array_keys($coords);

        if (count($visibleIds) > 1) {
            $links = CardLink::where('type', 'blocks')
                ->whereIn('card_id', $visibleIds)
                ->whereIn('related_card_id', $visibleIds)
                ->get(['card_id', 'related_card_id']);

            foreach ($links as $link) {
                $from = $coords[$link->card_id];
                $to = $coords[$link->related_card_id];
                $edges[] = ['fx' => $from['right'], 'fy' => $from['y'], 'tx' => $to['left'], 'ty' => $to['y']];
            }
        }

        return [
            'start' => $start,
            'days' => $days,
            'dayList' => $dayList,
            'todayOffset' => ($today->gte($start) && $today->lte($end)) ? (int) $start->diffInDays($today) : null,
            'lanes' => $lanes,
            'edges' => $edges,
            'gridWidth' => $labelWidth + $days * $dayWidth,
            'gridHeight' => $laneTop,
        ];
    }

    /**
     * Build the dashboard (reporting) view: headline totals plus per-list,
     * per-member and per-label breakdowns. Respects the active card filters.
     *
     * @return array<string, mixed>
     */
    private function buildDashboard(): array
    {
        $query = $this->board->cards()->whereNull('archived_at')->with(['members', 'labels']);
        $this->applyCardFilters($query);
        $cards = $query->get();

        $soonThreshold = now()->addDays(7);
        $total = $cards->count();
        $completed = $cards->whereNotNull('completed_at')->count();

        $byList = $this->board->lists()->whereNull('archived_at')->orderBy('position')->get()
            ->map(fn (BoardList $list): array => [
                'name' => $list->name,
                'color' => $list->cover_color,
                'count' => $cards->where('board_list_id', $list->id)->count(),
            ])->values()->all();

        $byMember = $this->board->members()->orderBy('name')->get()
            ->map(fn (User $member): array => [
                'name' => $member->name,
                'user' => $member,
                'count' => $cards->filter(fn (Card $card): bool => $card->members->contains('id', $member->id))->count(),
            ])
            ->sortByDesc('count')->values()->all();

        $unassigned = $cards->filter(fn (Card $card): bool => $card->members->isEmpty())->count();

        if ($unassigned > 0) {
            $byMember[] = ['name' => __('Sans membre'), 'user' => null, 'count' => $unassigned];
        }

        $byLabel = $this->board->labels
            ->map(fn ($label): array => [
                'name' => $label->name ?: __('Sans nom'),
                'color' => $label->color,
                'count' => $cards->filter(fn (Card $card): bool => $card->labels->contains('id', $label->id))->count(),
            ])
            ->sortByDesc('count')->values()->all();

        return [
            'total' => $total,
            'completed' => $completed,
            'completionRate' => $total > 0 ? (int) round($completed / $total * 100) : 0,
            'overdue' => $cards->filter(fn (Card $card): bool => $card->due_at && ! $card->completed_at && $card->due_at->isPast())->count(),
            'dueSoon' => $cards->filter(fn (Card $card): bool => $card->due_at && ! $card->completed_at && ! $card->due_at->isPast() && $card->due_at->lte($soonThreshold))->count(),
            'noDate' => $cards->filter(fn (Card $card): bool => ! $card->due_at && ! $card->start_at)->count(),
            'byList' => $byList,
            'byMember' => $byMember,
            'byLabel' => $byLabel,
        ];
    }

    /**
     * Build the table (spreadsheet) view: the board's cards, filtered and sorted
     * by the active column, with the relations the columns render.
     *
     * @return Collection<int, Card>
     */
    private function buildTable(): Collection
    {
        $query = $this->board->cards()
            ->whereNull('archived_at')
            ->with(['list', 'members', 'labels', 'customFieldValues']);

        $this->applyCardFilters($query);

        return (match ($this->tableSort) {
            'title' => $query->orderBy('title', $this->tableDir),
            'due' => $query->orderByRaw('due_at is null asc')->orderBy('due_at', $this->tableDir),
            'created' => $query->orderBy('created_at', $this->tableDir),
            default => $query->orderBy(
                BoardList::select('position')->whereColumn('board_lists.id', 'cards.board_list_id'),
                $this->tableDir
            )->orderBy('position'),
        })->get();
    }

    /**
     * Build the month grid for the calendar view: 6 weeks of day cells with the
     * board's dated cards (grouped by due date, falling back to start date).
     *
     * @return array{label: string, weekDays: array<int, string>, weeks: array<int, array<int, array{date: Carbon, day: int, inMonth: bool, isToday: bool, cards: Collection<int, Card>}>>}
     */
    private function buildCalendar(): array
    {
        $month = Carbon::parse($this->calendarMonth.'-01');
        $gridStart = $month->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $gridEnd = $month->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
        $rangeEnd = $gridEnd->copy()->endOfDay();

        $query = $this->board->cards()
            ->whereNull('archived_at')
            ->where(function ($q) use ($gridStart, $rangeEnd) {
                $q->whereBetween('due_at', [$gridStart, $rangeEnd])
                    ->orWhere(function ($nested) use ($gridStart, $rangeEnd) {
                        $nested->whereNull('due_at')->whereBetween('start_at', [$gridStart, $rangeEnd]);
                    });
            })
            ->with(['labels', 'members']);

        $this->applyCardFilters($query);

        $byDay = $query->get()->groupBy(fn (Card $card) => ($card->due_at ?? $card->start_at)->toDateString());

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
     * Apply the board's card filters (text, label, member, due state).
     *
     * @param  HasMany<Card, BoardList>  $query
     */
    private function applyCardFilters($query): void
    {
        if ($this->search !== '') {
            $term = '%'.mb_strtolower(trim($this->search)).'%';
            $query->where(function ($scoped) use ($term) {
                $scoped->whereRaw('LOWER(title) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$term]);
            });
        }

        if ($this->filterLabels !== []) {
            $query->whereHas('labels', fn ($labels) => $labels->whereIn('labels.id', $this->filterLabels));
        }

        if ($this->filterUnassigned) {
            $query->whereDoesntHave('members');
        } elseif ($this->filterMembers !== []) {
            $query->whereHas('members', fn ($members) => $members->whereIn('users.id', $this->filterMembers));
        }

        match ($this->filterDue) {
            'overdue' => $query->whereNotNull('due_at')->whereNull('completed_at')->where('due_at', '<', now()),
            'due' => $query->whereNotNull('due_at'),
            'none' => $query->whereNull('due_at'),
            default => null,
        };
    }
}
