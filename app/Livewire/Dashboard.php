<?php

namespace App\Livewire;

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Models\Board;
use App\Models\Workspace;
use App\Services\BoardTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public string $newWorkspaceName = '';

    /** @var array<int, string> */
    public array $newBoardName = [];

    public function createWorkspace(): void
    {
        $data = $this->validate([
            'newWorkspaceName' => ['required', 'string', 'max:255'],
        ]);

        $workspace = Workspace::create([
            'owner_id' => Auth::id(),
            'name' => $data['newWorkspaceName'],
            'slug' => Str::slug($data['newWorkspaceName']).'-'.Str::lower(Str::random(6)),
        ]);

        $workspace->members()->attach(Auth::id(), ['role' => Role::Owner->value]);

        $this->newWorkspaceName = '';
        $this->dispatch('toast', message: 'Workspace créé', type: 'success');
    }

    public ?int $renamingWorkspaceId = null;

    public string $workspaceNameDraft = '';

    public function startRenameWorkspace(int $workspaceId): void
    {
        $workspace = Auth::user()->workspaces()->findOrFail($workspaceId);
        $this->authorize('update', $workspace);

        $this->renamingWorkspaceId = $workspaceId;
        $this->workspaceNameDraft = $workspace->name;
    }

    public function renameWorkspace(): void
    {
        if ($this->renamingWorkspaceId === null) {
            return;
        }

        $workspace = Auth::user()->workspaces()->findOrFail($this->renamingWorkspaceId);
        $this->authorize('update', $workspace);

        $name = trim($this->workspaceNameDraft);

        if ($name !== '') {
            $workspace->update(['name' => $name]);
        }

        $this->renamingWorkspaceId = null;
    }

    public function deleteWorkspace(int $workspaceId): void
    {
        $workspace = Auth::user()->workspaces()->findOrFail($workspaceId);
        $this->authorize('delete', $workspace);

        $workspace->delete();
    }

    public ?int $templateToUse = null;

    public ?int $templateWorkspaceId = null;

    public string $templateBoardName = '';

    public function openTemplateModal(int $templateId): void
    {
        $template = Board::templates()->findOrFail($templateId);

        $this->templateToUse = $template->id;
        $this->templateBoardName = $template->name;
        $this->templateWorkspaceId = Auth::user()->workspaces()->min('workspaces.id');
    }

    public function createFromTemplate(): mixed
    {
        if ($this->templateToUse === null) {
            return null;
        }

        $template = Board::templates()->findOrFail($this->templateToUse);
        $workspace = Auth::user()->workspaces()->findOrFail($this->templateWorkspaceId);

        $board = app(BoardTemplateService::class)->instantiate(
            $template,
            $workspace,
            Auth::user(),
            $this->templateBoardName,
        );

        $this->templateToUse = null;

        return $this->redirectRoute('boards.show', $board, navigate: true);
    }

    public function createBoard(int $workspaceId): mixed
    {
        $workspace = Auth::user()->workspaces()->findOrFail($workspaceId);

        $name = trim($this->newBoardName[$workspaceId] ?? '');

        if ($name === '') {
            return null;
        }

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'created_by' => Auth::id(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'visibility' => BoardVisibility::Private,
            'position' => (int) $workspace->boards()->max('position') + 1,
        ]);

        $board->members()->attach(Auth::id(), ['role' => Role::Owner->value]);

        foreach (['À faire', 'En cours', 'Terminé'] as $index => $listName) {
            $board->lists()->create(['name' => $listName, 'position' => $index]);
        }

        return $this->redirectRoute('boards.show', $board, navigate: true);
    }

    public function render(): View
    {
        $user = Auth::user();

        $workspaces = $user->workspaces()
            ->with(['boards' => function ($query) use ($user) {
                $query->notArchived()
                    ->where('is_template', false)
                    ->where(function ($scoped) use ($user) {
                        $scoped->where('visibility', BoardVisibility::Workspace)
                            ->orWhereHas('members', fn ($members) => $members->whereKey($user->getKey()));
                    })
                    ->orderBy('position');
            }])
            ->orderBy('name')
            ->get();

        return view('livewire.dashboard', [
            'workspaces' => $workspaces,
            'templates' => Board::templates()->orderBy('name')->get(),
        ]);
    }
}
