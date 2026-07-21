<?php

namespace App\Livewire;

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Models\Board;
use App\Models\Workspace;
use App\Plugins\WorkspaceTypes;
use App\Services\BoardTemplateService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Tableau de bord')]
class Dashboard extends Component
{
    public string $newWorkspaceName = '';

    /** 'kanban' or a plugin-contributed workspace type key (e.g. 'shelf'). */
    public string $newWorkspaceType = Workspace::TYPE_KANBAN;

    /** @var array<int, string> */
    public array $newBoardName = [];

    public function createWorkspace(): void
    {
        $data = $this->validate([
            'newWorkspaceName' => ['required', 'string', 'max:255'],
        ]);

        // Only the host type or a type currently offered by a loaded plugin.
        $type = $this->newWorkspaceType;

        if ($type !== Workspace::TYPE_KANBAN && app(WorkspaceTypes::class)->find($type) === null) {
            $type = Workspace::TYPE_KANBAN;
        }

        $workspace = Workspace::create([
            'owner_id' => Auth::id(),
            'name' => $data['newWorkspaceName'],
            'slug' => Str::slug($data['newWorkspaceName']).'-'.Str::lower(Str::random(6)),
            'type' => $type,
        ]);

        $workspace->members()->attach(Auth::id(), ['role' => Role::Owner->value]);

        $this->reset('newWorkspaceName');
        $this->newWorkspaceType = Workspace::TYPE_KANBAN;
        $this->dispatch('toast', message: __('Workspace créé'), type: 'success');
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
        $this->templateWorkspaceId = Auth::user()->workspaces()
            ->where('type', Workspace::TYPE_KANBAN)
            ->min('workspaces.id');
    }

    public function createFromTemplate(): mixed
    {
        if ($this->templateToUse === null) {
            return null;
        }

        $template = Board::templates()->findOrFail($this->templateToUse);
        $workspace = Auth::user()->workspaces()
            ->where('type', Workspace::TYPE_KANBAN)
            ->findOrFail($this->templateWorkspaceId);

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

        // Boards only live in kanban workspaces (plugin-typed workspaces own
        // their whole surface).
        if (! $workspace->isKanban()) {
            return null;
        }

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

    // --- Pin boards (per-user, across workspaces) -----------------------------

    public function togglePin(int $boardId): void
    {
        $board = Board::findOrFail($boardId);
        $this->authorize('view', $board);

        $user = Auth::user();

        if ($user->pinnedBoards()->whereKey($boardId)->exists()) {
            $user->pinnedBoards()->detach($boardId);
        } else {
            $user->pinnedBoards()->attach($boardId);
        }
    }

    // --- Board rename / delete from the dashboard ------------------------------

    public ?int $renamingBoardId = null;

    public string $boardNameDraft = '';

    public function startRenameBoard(int $boardId): void
    {
        $board = Board::findOrFail($boardId);
        $this->authorize('update', $board);

        $this->renamingBoardId = $boardId;
        $this->boardNameDraft = $board->name;
    }

    public function renameBoard(): void
    {
        if ($this->renamingBoardId === null) {
            return;
        }

        $board = Board::findOrFail($this->renamingBoardId);
        $this->authorize('update', $board);

        $name = trim($this->boardNameDraft);

        if ($name !== '') {
            $board->update(['name' => $name]);
        }

        $this->renamingBoardId = null;
    }

    public function deleteBoard(int $boardId): void
    {
        $board = Board::findOrFail($boardId);
        $this->authorize('delete', $board);

        $board->delete();
        $this->dispatch('toast', message: __('Board supprimé'), type: 'success');
    }

    // --- Move a board to another workspace --------------------------------------

    public function moveBoardToWorkspace(int $boardId, int $workspaceId): void
    {
        $board = Board::findOrFail($boardId);
        $this->authorize('update', $board);

        // The target must be one of the actor's own KANBAN workspaces.
        $workspace = Auth::user()->workspaces()->findOrFail($workspaceId);

        if ($workspace->id === $board->workspace_id || ! $workspace->isKanban()) {
            return;
        }

        $this->relocateBoard($board, $workspace);
    }

    /** Move a board into a workspace created on the fly. */
    public function moveBoardToNewWorkspace(int $boardId, string $name): void
    {
        $board = Board::findOrFail($boardId);
        $this->authorize('update', $board);

        $name = trim($name);

        if ($name === '' || mb_strlen($name) > 255) {
            return;
        }

        $workspace = Workspace::create([
            'owner_id' => Auth::id(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
        ]);

        $workspace->members()->attach(Auth::id(), ['role' => Role::Owner->value]);

        $this->relocateBoard($board, $workspace);
    }

    private function relocateBoard(Board $board, Workspace $workspace): void
    {
        // Board member role keys resolve against the workspace's roles: a custom
        // role that does not exist in the target degrades to plain member.
        $validKeys = $workspace->roles()->pluck('key');

        foreach ($board->members as $member) {
            if (! $validKeys->contains($member->pivot->role)) {
                $board->members()->updateExistingPivot($member->id, ['role' => Role::Member->value]);
            }
        }

        $board->update([
            'workspace_id' => $workspace->id,
            'position' => (int) $workspace->boards()->max('position') + 1,
        ]);

        $this->dispatch('toast', message: __('Board déplacé vers :workspace', ['workspace' => $workspace->name]), type: 'success');
    }

    // --- Board member management (modal) --------------------------------------

    public ?int $managingMembersBoardId = null;

    public string $memberSearch = '';

    public function openBoardMembers(int $boardId): void
    {
        $board = Board::findOrFail($boardId);
        $this->authorize('manageMembers', $board);

        $this->managingMembersBoardId = $boardId;
        $this->memberSearch = '';
    }

    public function closeBoardMembers(): void
    {
        $this->managingMembersBoardId = null;
        $this->memberSearch = '';
    }

    private function managedBoard(): Board
    {
        $board = Board::findOrFail($this->managingMembersBoardId);
        $this->authorize('manageMembers', $board);

        return $board;
    }

    public function addBoardMember(int $userId): void
    {
        $board = $this->managedBoard();

        // Only workspace members may be added to a board (Trello-style).
        if (! $board->workspace->members()->whereKey($userId)->exists()) {
            return;
        }

        $board->members()->syncWithoutDetaching([$userId => ['role' => Role::Member->value]]);
    }

    public function updateBoardMemberRole(int $userId, string $role): void
    {
        $board = $this->managedBoard();

        $membership = $board->members()->whereKey($userId)->first();
        $assignable = $board->workspace->roles()->where('key', '!=', 'owner')->pluck('key');

        if (! $membership || $membership->pivot->role === Role::Owner->value || ! $assignable->contains($role)) {
            return;
        }

        $board->members()->updateExistingPivot($userId, ['role' => $role]);
    }

    public function removeBoardMember(int $userId): void
    {
        $board = $this->managedBoard();

        $membership = $board->members()->whereKey($userId)->first();

        // The board owner cannot be removed.
        if (! $membership || $membership->pivot->role === Role::Owner->value) {
            return;
        }

        $board->members()->detach($userId);

        // Drop their card assignments on this board so no orphan assignee remains.
        DB::table('card_user')
            ->whereIn('card_id', $board->cards()->select('id'))
            ->where('user_id', $userId)
            ->delete();
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
                    ->with('members')
                    ->withCount([
                        'lists as lists_count' => fn ($lists) => $lists->whereNull('archived_at'),
                        'cards as cards_count' => fn ($cards) => $cards->whereNull('archived_at'),
                    ])
                    ->orderBy('position');
            }])
            ->orderBy('name')
            ->get();

        $pinnedIds = $user->pinnedBoards()->pluck('boards.id')->all();

        // Pinned boards the user can still view, across every workspace.
        $pinnedBoards = $user->pinnedBoards()
            ->notArchived()
            ->where('is_template', false)
            ->with(['members', 'workspace'])
            ->withCount([
                'lists as lists_count' => fn ($lists) => $lists->whereNull('archived_at'),
                'cards as cards_count' => fn ($cards) => $cards->whereNull('archived_at'),
            ])
            ->orderBy('boards.name')
            ->get()
            ->filter(fn (Board $board): bool => Gate::allows('view', $board))
            ->values();

        $managingBoard = null;
        $memberCandidates = collect();
        $assignableRoles = collect();

        if ($this->managingMembersBoardId !== null) {
            $candidate = Board::with(['members', 'workspace.roles'])->find($this->managingMembersBoardId);

            if ($candidate && Gate::allows('manageMembers', $candidate)) {
                $managingBoard = $candidate;
                $search = trim($this->memberSearch);

                $memberCandidates = $candidate->workspace->members()
                    ->whereNotIn('users.id', $candidate->members->pluck('id'))
                    ->when($search !== '', fn ($query) => $query->where(fn ($where) => $where
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('email', 'like', '%'.$search.'%')))
                    ->orderBy('name')
                    ->limit(8)
                    ->get();

                $assignableRoles = $candidate->workspace->roles()->where('key', '!=', 'owner')->orderBy('position')->get();
            }
        }

        return view('livewire.dashboard', [
            'workspaces' => $workspaces,
            // Plugin-contributed workspace types (e.g. Shelf), keyed by type key.
            'workspaceTypes' => app(WorkspaceTypes::class)->all(),
            'templates' => Board::templates()->orderBy('name')->get(),
            'pinnedBoards' => $pinnedBoards,
            'pinnedIds' => $pinnedIds,
            'managingBoard' => $managingBoard,
            'memberCandidates' => $memberCandidates,
            'assignableRoles' => $assignableRoles,
        ]);
    }
}
