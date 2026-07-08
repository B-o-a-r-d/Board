<?php

use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use Livewire\Livewire;

test('the dashboard view renders the breakdown sections and names', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Backlog']);
    $label = Label::factory()->create(['board_id' => $board->id, 'name' => 'Urgent']);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);
    $card->labels()->attach($label);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'dashboard')
        ->assertSet('view', 'dashboard')
        ->assertSee('Par liste')
        ->assertSee('Par membre')
        ->assertSee('Par label')
        ->assertSee('Backlog')
        ->assertSee('Urgent');
});

test('the dashboard computes the completion rate', function () {
    // makeCardContext seeds one (incomplete) card; add one completed → 1/2 = 50%.
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'completed_at' => now()]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'dashboard')
        ->assertSee('50%');
});

test('the dashboard respects the active filters', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $seed] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $label = Label::factory()->create(['board_id' => $board->id]);
    $tagged = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'completed_at' => now()]);
    $tagged->labels()->attach($label);

    // Filtered to the label: only the (completed) tagged card counts → 100%.
    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'dashboard')
        ->call('toggleLabel', $label->id)
        ->assertSee('100%');
});
