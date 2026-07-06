<?php

use App\Models\User;

test('the CSV export contains all card data including checklist items', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'DoD', 'position' => 0]);
    $checklist->items()->create(['content' => 'Tests écrits', 'position' => 0, 'is_completed' => true]);
    $card->comments()->create(['user_id' => $owner->id, 'body' => 'Un commentaire']);

    $response = $this->actingAs($owner)->get(route('boards.export', ['board' => $board->id, 'format' => 'csv']));

    $response->assertOk()->assertDownload();

    $content = $response->streamedContent();
    expect($content)->toContain((string) $card->title)
        ->and($content)->toContain('Tests écrits')     // checklist item
        ->and($content)->toContain('Un commentaire');   // comment
});

test('a board can be exported to XLSX', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $this->actingAs($owner)
        ->get(route('boards.export', ['board' => $board->id, 'format' => 'xlsx']))
        ->assertOk()
        ->assertDownload();
});

test('the JSON export is fully structured with nested card data', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'DoD', 'position' => 0]);
    $checklist->items()->create(['content' => 'Tests écrits', 'position' => 0, 'is_completed' => true]);

    $this->actingAs($owner)
        ->get(route('boards.export', ['board' => $board->id, 'format' => 'json']))
        ->assertOk()
        ->assertDownload()
        ->assertJsonPath('board.name', $board->name)
        ->assertJsonPath('lists.0.cards.0.checklists.0.items.0.content', 'Tests écrits');
});

test('an unknown format is 404 and outsiders are forbidden', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $this->actingAs($owner)
        ->get(route('boards.export', ['board' => $board->id, 'format' => 'pdf']))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('boards.export', ['board' => $board->id, 'format' => 'csv']))
        ->assertForbidden();
});
