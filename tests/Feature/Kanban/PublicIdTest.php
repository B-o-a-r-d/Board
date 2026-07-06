<?php

use Laravel\Sanctum\Sanctum;

test('entities get a unique ULID public_id on creation', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();

    expect($board->public_id)->toBeString()->toHaveLength(26)
        ->and($card->public_id)->toBeString()->toHaveLength(26)
        ->and($card->list->public_id)->toBeString()->toHaveLength(26)
        ->and($board->workspace->public_id)->toBeString()->toHaveLength(26)
        ->and($board->public_id)->not->toBe($card->public_id);
});

test('the board page resolves by public_id, not the integer id', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $this->actingAs($owner)->get('/boards/'.$board->public_id)->assertOk();

    // The internal integer id is not a valid public route key.
    $this->actingAs($owner)->get('/boards/'.$board->id)->assertNotFound();
});

test('deep-linking a card by public_id opens its modal', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $this->actingAs($owner)
        ->get(route('boards.show', ['board' => $board, 'card' => $card->public_id]))
        ->assertOk()
        ->assertSee('Commentaires');
});

test('the API exposes the public_id', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/boards/{$board->id}")
        ->assertOk()
        ->assertJsonPath('data.public_id', $board->public_id);
});
