<?php

namespace App\Livewire\Boards;

use App\Automations\AutomationRegistry;
use App\Models\Board;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

class Automations extends Component
{
    public Board $board;

    public bool $showTrigger = true;

    public bool $showModal = false;

    public bool $building = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $triggerType = '';

    /** @var array<string, mixed> */
    public array $triggerConfig = [];

    public string $actionType = '';

    /** @var array<string, mixed> */
    public array $actionConfig = [];

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
        $this->building = false;
        $this->resetForm();
    }

    public function startCreate(): void
    {
        $this->authorize('update', $this->board);
        $this->resetForm();
        $this->building = true;
    }

    public function startEdit(int $id): void
    {
        $this->authorize('update', $this->board);

        $automation = $this->board->automations()->findOrFail($id);

        $this->editingId = $automation->id;
        $this->name = $automation->name;
        $this->triggerType = $automation->trigger_type;
        $this->triggerConfig = $this->stringifyConfig($automation->trigger_config ?? []);
        $this->actionType = $automation->action_type;
        $this->actionConfig = $this->stringifyConfig($automation->action_config ?? []);
        $this->building = true;
    }

    public function save(): void
    {
        $this->authorize('update', $this->board);

        $registry = app(AutomationRegistry::class);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'triggerType' => ['required', Rule::in(array_keys($registry->triggers()))],
            'actionType' => ['required', Rule::in(array_keys($registry->actions()))],
        ]);

        $attributes = [
            'name' => $this->name,
            'trigger_type' => $this->triggerType,
            'trigger_config' => $this->cleanConfig($this->triggerConfig),
            'action_type' => $this->actionType,
            'action_config' => $this->cleanConfig($this->actionConfig),
        ];

        if ($this->editingId !== null) {
            $this->board->automations()->whereKey($this->editingId)->firstOrFail()->update($attributes);
            $this->dispatch('toast', message: __('Automation mise à jour'), type: 'success');
        } else {
            $this->board->automations()->create($attributes + ['created_by' => Auth::id(), 'is_active' => true]);
            $this->dispatch('toast', message: __('Automation créée'), type: 'success');
        }

        $this->building = false;
        $this->resetForm();
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('update', $this->board);

        $automation = $this->board->automations()->findOrFail($id);
        $automation->update(['is_active' => ! $automation->is_active]);
    }

    public function deleteAutomation(int $id): void
    {
        $this->authorize('update', $this->board);

        $this->board->automations()->whereKey($id)->delete();
        $this->dispatch('toast', message: __('Automation supprimée'), type: 'info');
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function cleanConfig(array $config): array
    {
        return array_filter($config, fn ($value) => $value !== '' && $value !== null);
    }

    /**
     * Cast stored config values to strings so they match <select> option values.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function stringifyConfig(array $config): array
    {
        return array_map(fn ($value) => (string) $value, $config);
    }

    private function resetForm(): void
    {
        $this->reset('name', 'triggerType', 'actionType', 'editingId');
        $this->triggerConfig = [];
        $this->actionConfig = [];
    }

    public function render(): View
    {
        $registry = app(AutomationRegistry::class);

        return view('livewire.boards.automations', [
            'automations' => $this->board->automations()->latest()->get(),
            'triggers' => $registry->triggers(),
            'actions' => $registry->actions(),
            'lists' => $this->board->lists()->whereNull('archived_at')->orderBy('position')->get(),
            'labels' => $this->board->labels,
            'members' => $this->board->members,
        ]);
    }
}
