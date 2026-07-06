<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BoardListResource;
use App\Models\Board;
use App\Models\BoardList;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class BoardListController extends Controller
{
    public function index(Board $board): AnonymousResourceCollection
    {
        $this->authorize('view', $board);

        return BoardListResource::collection(
            $board->lists()->whereNull('archived_at')->orderBy('position')->with('board')->get(),
        );
    }

    public function store(Request $request, Board $board): JsonResponse
    {
        $this->authorize('view', $board);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'cover_color' => ['nullable', 'string', 'max:9'],
        ]);

        $list = $board->lists()->create([
            'name' => $data['name'],
            'cover_color' => $data['cover_color'] ?? null,
            'position' => (int) $board->lists()->max('position') + 1,
        ]);

        return (new BoardListResource($list))->response()->setStatusCode(201);
    }

    public function update(Request $request, BoardList $list): BoardListResource
    {
        $this->authorize('view', $list->board);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'cover_color' => ['nullable', 'string', 'max:9'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);

        $list->update($data);

        return new BoardListResource($list);
    }

    public function destroy(BoardList $list): Response
    {
        $this->authorize('view', $list->board);

        $list->delete();

        return response()->noContent();
    }
}
