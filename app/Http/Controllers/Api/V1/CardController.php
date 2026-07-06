<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CardResource;
use App\Models\BoardList;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CardController extends Controller
{
    public function index(BoardList $list): AnonymousResourceCollection
    {
        $this->authorize('view', $list->board);

        return CardResource::collection(
            $list->cards()->whereNull('archived_at')->orderBy('position')->with(['labels', 'members'])->get(),
        );
    }

    public function store(Request $request, BoardList $list): JsonResponse
    {
        $this->authorize('view', $list->board);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
        ]);

        $card = $list->cards()->create([
            'board_id' => $list->board_id,
            'created_by' => $request->user()->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_at' => $data['due_at'] ?? null,
            'position' => (int) $list->cards()->max('position') + 1,
        ]);

        return (new CardResource($card))->response()->setStatusCode(201);
    }

    public function show(Card $card): CardResource
    {
        $this->authorize('view', $card);

        return new CardResource($card->load(['labels', 'members']));
    }

    public function update(Request $request, Card $card): CardResource
    {
        $this->authorize('update', $card);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['nullable', 'date'],
            'cover_color' => ['nullable', 'string', 'max:9'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        $update = array_intersect_key($data, array_flip(['title', 'description', 'due_at', 'cover_color']));

        if (array_key_exists('completed', $data)) {
            $update['completed_at'] = $data['completed'] ? now() : null;
        }

        $card->update($update);

        return new CardResource($card->load(['labels', 'members']));
    }

    public function move(Request $request, Card $card): CardResource
    {
        $this->authorize('update', $card);

        $data = $request->validate([
            'list_id' => ['required', 'integer'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);

        $list = $card->board->lists()->findOrFail($data['list_id']);

        $card->update([
            'board_list_id' => $list->id,
            'position' => $data['position'] ?? (int) $list->cards()->max('position') + 1,
        ]);

        return new CardResource($card);
    }

    public function destroy(Card $card): Response
    {
        $this->authorize('delete', $card);

        $card->delete();

        return response()->noContent();
    }
}
