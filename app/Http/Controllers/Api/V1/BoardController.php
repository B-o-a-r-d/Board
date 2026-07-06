<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BoardResource;
use App\Models\Board;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BoardController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $boards = Board::query()
            ->notArchived()
            ->where('is_template', false)
            ->whereHas('workspace.members', fn ($query) => $query->whereKey($user->id))
            ->where(function ($query) use ($user) {
                $query->where('visibility', BoardVisibility::Workspace)
                    ->orWhereHas('members', fn ($members) => $members->whereKey($user->id));
            })
            ->with('workspace')
            ->latest()
            ->get();

        return BoardResource::collection($boards);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'workspace_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['nullable', Rule::enum(BoardVisibility::class)],
        ]);

        $workspace = $request->user()->workspaces()->where('workspaces.public_id', $data['workspace_id'])->firstOrFail();

        $board = Board::create([
            'workspace_id' => $workspace->id,
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'slug' => Str::slug($data['name']).'-'.Str::lower(Str::random(6)),
            'description' => $data['description'] ?? null,
            'visibility' => $data['visibility'] ?? BoardVisibility::Private->value,
            'position' => (int) $workspace->boards()->max('position') + 1,
        ]);

        $board->members()->attach($request->user()->id, ['role' => Role::Owner->value]);

        return (new BoardResource($board))->response()->setStatusCode(201);
    }

    public function show(Board $board): BoardResource
    {
        $this->authorize('view', $board);

        $board->load([
            'workspace',
            'lists' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.board',
            'lists.cards' => fn ($query) => $query->whereNull('archived_at')->orderBy('position'),
            'lists.cards.board',
            'lists.cards.list',
            'lists.cards.labels',
            'labels',
        ]);

        return new BoardResource($board);
    }

    public function update(Request $request, Board $board): BoardResource
    {
        $this->authorize('update', $board);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'visibility' => ['sometimes', Rule::enum(BoardVisibility::class)],
            'background' => ['nullable', 'string'],
        ]);

        $board->update($data);

        return new BoardResource($board);
    }

    public function destroy(Board $board): Response
    {
        $this->authorize('delete', $board);

        $board->delete();

        return response()->noContent();
    }
}
