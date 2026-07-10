<?php

namespace App\Livewire\Boards;

use App\Automations\AutomationRegistry;
use App\Automations\SentenceRenderer;
use App\Models\Automation;
use App\Models\Board;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The full-screen automation builder (Butler-style): sidebar sections (rules,
 * scheduled, due-date, card buttons), a 3-step wizard (trigger → actions →
 * review) and a natural-language listing with per-rule toggles.
 */
class Automations extends Component
{
    /** Trigger keys offered per builder category (event rules). */
    public const TRIGGER_CATEGORIES = [
        'move' => ['card.created', 'card.moved_to_list', 'card.moved_from_list', 'card.archived', 'list.has_n_cards'],
        'changes' => ['card.completed', 'card.label_added', 'card.label_removed', 'card.member_assigned'],
        'dates' => ['card.due_set', 'card.due_soon'],
        'checklists' => ['checklist.added', 'checklist.item_checked', 'checklist.completed'],
        'content' => ['comment.added', 'card.title_contains'],
        'fields' => ['custom_field.changed'],
    ];

    /** Action keys offered per builder category. */
    public const ACTION_CATEGORIES = [
        'move' => ['move_to_list', 'move_in_list', 'sort_list', 'archive_card', 'archive_list_cards'],
        'addremove' => ['assign_label', 'remove_label', 'assign_member', 'unassign_member', 'add_checklist', 'create_card', 'copy_card', 'create_follow_up_card'],
        'dates' => ['set_due_date', 'clear_due_date', 'mark_complete', 'mark_incomplete'],
        'content' => ['post_comment', 'set_custom_field'],
        'output' => ['notify_members', 'send_webhook'],
    ];

    /** Board-scope actions a scheduled (cardless) rule may run. */
    public const SCHEDULED_ACTIONS = ['create_card', 'sort_list', 'archive_list_cards'];

    public const SCHEDULE_FREQS = ['daily', 'days', 'every_n_weeks', 'monthly_first_dow', 'monthly_day', 'yearly'];

    public Board $board;

    public bool $showTrigger = true;

    public bool $showModal = false;

    /** Active sidebar section: rules | scheduled | due | buttons. */
    public string $section = 'rules';

    // --- Wizard state -----------------------------------------------------------

    public bool $building = false;

    public int $step = 1;

    public ?int $editingId = null;

    public string $name = '';

    public string $triggerType = '';

    /** @var array<string, mixed> */
    public array $triggerConfig = [];

    public string $actorScope = Automation::ACTOR_ANYONE;

    /** @var array<int, array{type: string, config: array<string, mixed>}> */
    public array $actions = [];

    /** @var array<int, array{type: string, config: array<string, mixed>}> */
    public array $conditions = [];

    public function mount(Board $board, bool $showTrigger = true): void
    {
        $this->board = $board;
        $this->showTrigger = $showTrigger;
    }

    #[On('open-automations')]
    public function open(): void
    {
        $this->authorize('update', $this->board);
        $this->showModal = true;
    }

    public function close(): void
    {
        $this->showModal = false;
        $this->cancelBuild();
    }

    public function setSection(string $section): void
    {
        if (in_array($section, ['rules', 'scheduled', 'due', 'buttons', 'board_buttons'], true)) {
            $this->section = $section;
            $this->cancelBuild();
        }
    }

    // --- Wizard -----------------------------------------------------------------

    public function startCreate(): void
    {
        $this->authorize('update', $this->board);
        $this->resetForm();
        $this->building = true;

        // Buttons and due-date rules pre-pick their trigger type.
        if ($this->section === 'buttons') {
            $this->triggerType = 'manual';
            $this->step = 2;
        } elseif ($this->section === 'board_buttons') {
            $this->triggerType = 'board_button';
            $this->step = 2;
        } elseif ($this->section === 'due') {
            $this->triggerType = 'card.due_relative';
            $this->triggerConfig = ['days' => 1, 'direction' => 'before'];
        } elseif ($this->section === 'scheduled') {
            $this->triggerType = 'scheduled';
        }
    }

    public function cancelBuild(): void
    {
        $this->building = false;
        $this->resetForm();
    }

    public function startEdit(int $id): void
    {
        $this->authorize('update', $this->board);

        $automation = $this->board->automations()->findOrFail($id);

        $this->resetForm();
        $this->editingId = $automation->id;
        $this->name = $automation->name;
        $this->triggerType = $automation->trigger_type;
        $this->triggerConfig = $automation->trigger_config ?? [];
        $this->actorScope = $automation->actor_scope ?? Automation::ACTOR_ANYONE;
        $this->actions = $automation->actionList();
        $this->conditions = $automation->conditionList();
        $this->section = $this->sectionFor($automation->trigger_type);
        $this->building = true;
        $this->step = 3;
    }

    public function pickTrigger(string $key): void
    {
        $allowed = $this->section === 'rules'
            ? array_merge(...array_values(self::TRIGGER_CATEGORIES))
            : [];

        if (! in_array($key, $allowed, true)) {
            return;
        }

        if ($this->triggerType !== $key) {
            $this->triggerType = $key;
            $this->triggerConfig = [];
        }
    }

    public function pickSchedule(string $freq): void
    {
        if ($this->section === 'scheduled' && in_array($freq, self::SCHEDULE_FREQS, true)) {
            $this->triggerConfig = ['freq' => $freq, 'at' => $this->triggerConfig['at'] ?? '09:00'];
        }
    }

    public function goToStep(int $step): void
    {
        $hasTriggerStep = ! in_array($this->section, ['buttons', 'board_buttons'], true);

        if ($step === 1 && ! $hasTriggerStep) {
            return;
        }

        if ($step <= 1 || ($step === 2 && $this->triggerReady()) || ($step === 3 && $this->triggerReady() && $this->actions !== [])) {
            $this->step = $step;
        }
    }

    public function addAction(string $key): void
    {
        if (! in_array($key, $this->allowedActionKeys(), true)) {
            return;
        }

        $this->actions[] = ['type' => $key, 'config' => []];
    }

    public function removeAction(int $index): void
    {
        unset($this->actions[$index]);
        $this->actions = array_values($this->actions);
    }

    public function moveAction(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->actions[$index], $this->actions[$target])) {
            return;
        }

        [$this->actions[$index], $this->actions[$target]] = [$this->actions[$target], $this->actions[$index]];
    }

    public function addCondition(string $key): void
    {
        if (in_array($this->section, ['scheduled', 'board_buttons'], true)) {
            return; // conditions are card-based; these rules run on a phantom card
        }

        if (app(AutomationRegistry::class)->condition($key) !== null) {
            $this->conditions[] = ['type' => $key, 'config' => []];
        }
    }

    public function removeCondition(int $index): void
    {
        unset($this->conditions[$index]);
        $this->conditions = array_values($this->conditions);
    }

    /**
     * Whether step 1 is complete enough to continue.
     */
    public function triggerReady(): bool
    {
        if ($this->triggerType === '') {
            return false;
        }

        return match ($this->triggerType) {
            'scheduled' => in_array($this->triggerConfig['freq'] ?? '', self::SCHEDULE_FREQS, true),
            'card.due_relative' => (int) ($this->triggerConfig['days'] ?? 0) >= 1
                && in_array($this->triggerConfig['direction'] ?? '', ['before', 'after'], true),
            default => true,
        };
    }

    /**
     * The live natural-language preview shown in the review step.
     */
    public function previewSentence(): string
    {
        $automation = new Automation([
            'trigger_type' => $this->triggerType,
            'trigger_config' => $this->cleanConfig($this->triggerConfig),
            'actions' => array_map(fn (array $a) => ['type' => $a['type'], 'config' => $this->cleanConfig($a['config'] ?? [])], $this->actions),
            'conditions' => array_map(fn (array $c) => ['type' => $c['type'], 'config' => $this->cleanConfig($c['config'] ?? [])], $this->conditions),
            'actor_scope' => $this->actorScope,
        ]);
        $automation->setRelation('board', $this->board);

        return app(SentenceRenderer::class)->render($automation);
    }

    public function save(): void
    {
        $this->authorize('update', $this->board);

        $registry = app(AutomationRegistry::class);

        $this->validate([
            'triggerType' => ['required', Rule::in(array_keys($registry->triggers()))],
            'actorScope' => ['required', Rule::in([Automation::ACTOR_ANYONE, Automation::ACTOR_ME])],
            'name' => [in_array($this->section, ['buttons', 'board_buttons'], true) ? 'required' : 'nullable', 'string', 'max:255'],
        ], [], ['name' => __('nom du bouton')]);

        if (! $this->triggerReady()) {
            $this->addError('triggerType', __('Complétez la configuration du déclencheur.'));

            return;
        }

        $actions = collect($this->actions)
            ->filter(fn (array $a) => $registry->action($a['type'] ?? '') !== null)
            ->map(fn (array $a) => ['type' => $a['type'], 'config' => $this->cleanConfig($a['config'] ?? [])])
            ->values()
            ->all();

        if ($actions === []) {
            $this->addError('actions', __('Ajoutez au moins une action.'));

            return;
        }

        $conditions = collect($this->conditions)
            ->filter(fn (array $c) => $registry->condition($c['type'] ?? '') !== null)
            ->map(fn (array $c) => ['type' => $c['type'], 'config' => $this->cleanConfig($c['config'] ?? [])])
            ->values()
            ->all();

        $attributes = [
            'name' => trim($this->name) !== '' ? trim($this->name) : Str::limit($this->previewSentence(), 250),
            'trigger_type' => $this->triggerType,
            'trigger_config' => $this->cleanConfig($this->triggerConfig),
            'actions' => $actions,
            'conditions' => $conditions,
            'actor_scope' => $this->actorScope,
            // Legacy single-action columns kept in sync for compatibility.
            'action_type' => $actions[0]['type'],
            'action_config' => $actions[0]['config'],
        ];

        if ($this->editingId !== null) {
            $this->board->automations()->whereKey($this->editingId)->firstOrFail()->update($attributes);
            $this->dispatch('toast', message: __('Automation mise à jour'), type: 'success');
        } else {
            $this->board->automations()->create($attributes + ['created_by' => Auth::id(), 'is_active' => true]);
            $this->dispatch('toast', message: __('Automation créée'), type: 'success');
        }

        $this->cancelBuild();
    }

    // --- Listing actions ----------------------------------------------------------

    public function toggleActive(int $id): void
    {
        $this->authorize('update', $this->board);

        $automation = $this->board->automations()->findOrFail($id);
        $automation->update(['is_active' => ! $automation->is_active]);
    }

    public function duplicateAutomation(int $id): void
    {
        $this->authorize('update', $this->board);

        $automation = $this->board->automations()->findOrFail($id);

        $copy = $automation->replicate(['runs_count', 'failures_count', 'last_run_at', 'public_id']);
        $copy->name = Str::limit($automation->name, 240).' '.__('(copie)');
        $copy->created_by = Auth::id();
        // Duplicates start disabled so the rule never fires twice by surprise.
        $copy->is_active = false;
        $copy->save();

        $this->dispatch('toast', message: __('Automation dupliquée (désactivée)'), type: 'success');
    }

    public function deleteAutomation(int $id): void
    {
        $this->authorize('update', $this->board);

        $this->board->automations()->whereKey($id)->delete();
        $this->dispatch('toast', message: __('Automation supprimée'), type: 'info');
    }

    // --- Internals ------------------------------------------------------------------

    /**
     * Action keys the current rule type may use (scheduled rules are cardless).
     */
    public function allowedActionKeys(): array
    {
        return in_array($this->section, ['scheduled', 'board_buttons'], true)
            ? self::SCHEDULED_ACTIONS
            : array_merge(...array_values(self::ACTION_CATEGORIES));
    }

    private function sectionFor(string $triggerType): string
    {
        return match ($triggerType) {
            'manual' => 'buttons',
            'board_button' => 'board_buttons',
            'scheduled' => 'scheduled',
            'card.due_relative' => 'due',
            default => 'rules',
        };
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cleanConfig(array $config): array
    {
        return array_filter($config, fn ($value) => $value !== '' && $value !== null && $value !== []);
    }

    private function resetForm(): void
    {
        $this->reset('name', 'triggerType', 'editingId');
        $this->step = 1;
        $this->triggerConfig = [];
        $this->actorScope = Automation::ACTOR_ANYONE;
        $this->actions = [];
        $this->conditions = [];
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $registry = app(AutomationRegistry::class);
        $renderer = app(SentenceRenderer::class);

        $sectionTriggers = match ($this->section) {
            'buttons' => ['manual'],
            'board_buttons' => ['board_button'],
            'scheduled' => ['scheduled'],
            'due' => ['card.due_relative'],
            default => array_merge(...array_values(self::TRIGGER_CATEGORIES)),
        };

        $items = $this->showModal
            ? $this->board->automations()
                ->whereIn('trigger_type', $sectionTriggers)
                ->latest()
                ->get()
                ->map(fn (Automation $a) => ['model' => $a, 'sentence' => $renderer->render($a)])
            : collect();

        return view('livewire.boards.automations', [
            'items' => $items,
            'registryTriggers' => $registry->triggers(),
            'registryActions' => $registry->actions(),
            'registryConditions' => $registry->conditions(),
            'lists' => $this->board->lists()->whereNull('archived_at')->whereNull('source_plugin_id')->orderBy('position')->get(),
            'labels' => $this->board->labels,
            'members' => $this->board->members,
            'customFields' => $this->board->customFields()->orderBy('position')->get(),
        ]);
    }
}
