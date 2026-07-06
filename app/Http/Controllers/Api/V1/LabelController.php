<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\LabelResource;
use App\Models\Board;
use App\Models\Label;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class LabelController extends Controller
{
    public function index(Board $board): AnonymousResourceCollection
    {
        $this->authorize('view', $board);

        return LabelResource::collection($board->labels()->get());
    }

    public function store(Request $request, Board $board): JsonResponse
    {
        $this->authorize('view', $board);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:9'],
        ]);

        $label = $board->labels()->create([
            'name' => $data['name'] ?? null,
            'color' => $data['color'],
        ]);

        return (new LabelResource($label))->response()->setStatusCode(201);
    }

    public function update(Request $request, Label $label): LabelResource
    {
        $this->authorize('view', $label->board);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'max:9'],
        ]);

        $label->update($data);

        return new LabelResource($label);
    }

    public function destroy(Label $label): Response
    {
        $this->authorize('view', $label->board);

        $label->delete();

        return response()->noContent();
    }
}
