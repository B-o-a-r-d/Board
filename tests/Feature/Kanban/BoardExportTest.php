<?php

use App\Livewire\Boards\Show;
use App\Models\User;
use App\Services\BoardExportService;
use Livewire\Livewire;

test('the board export links resolve the public id, not the integer id', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->assertSee(route('boards.export', ['board' => $board, 'format' => 'json']), escape: false)
        ->assertDontSee('/boards/'.$board->id.'/export/');
});

test('the CSV export contains all card data including checklist items', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'DoD', 'position' => 0]);
    $checklist->items()->create(['content' => 'Tests écrits', 'position' => 0, 'is_completed' => true]);
    $card->comments()->create(['user_id' => $owner->id, 'body' => 'Un commentaire']);

    $response = $this->actingAs($owner)->get(route('boards.export', ['board' => $board, 'format' => 'csv']));

    $response->assertOk()->assertDownload();

    $content = $response->streamedContent();
    expect($content)->toContain((string) $card->title)
        ->and($content)->toContain('Tests écrits')     // checklist item
        ->and($content)->toContain('Un commentaire');   // comment
});

test('a board can be exported to XLSX', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $this->actingAs($owner)
        ->get(route('boards.export', ['board' => $board, 'format' => 'xlsx']))
        ->assertOk()
        ->assertDownload();
});

test('the JSON export is fully structured with nested card data', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'DoD', 'position' => 0]);
    $checklist->items()->create(['content' => 'Tests écrits', 'position' => 0, 'is_completed' => true]);

    $this->actingAs($owner)
        ->get(route('boards.export', ['board' => $board, 'format' => 'json']))
        ->assertOk()
        ->assertDownload()
        ->assertJsonPath('board.name', $board->name)
        ->assertJsonPath('lists.0.cards.0.checklists.0.items.0.content', 'Tests écrits');
});

test('spreadsheet cells starting with a formula character are neutralised', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $card->update([
        'title' => '=HYPERLINK("http://evil.test","clic")',
        'description' => '+1+1 should be inert',
    ]);

    $row = app(BoardExportService::class)->rows($board->fresh())->firstWhere('Position', $card->position);

    // A leading single quote forces the spreadsheet to read the cell as text.
    expect($row['Titre'])->toBe("'=HYPERLINK(\"http://evil.test\",\"clic\")")
        ->and($row['Description'])->toStartWith("'+")
        // A benign cell is untouched.
        ->and($row['Terminée'])->toBe('Non');
});

test('an unknown format is 404 and outsiders are forbidden', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $this->actingAs($owner)
        ->get(route('boards.export', ['board' => $board, 'format' => 'pdf']))
        ->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get(route('boards.export', ['board' => $board, 'format' => 'csv']))
        ->assertForbidden();
});
