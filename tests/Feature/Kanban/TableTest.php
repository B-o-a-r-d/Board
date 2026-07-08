<?php

use App\Livewire\Boards\Show;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use App\Models\User;
use Livewire\Livewire;

test('switching to the table view lists the cards', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte tableau']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'table')
        ->assertSet('view', 'table')
        ->assertSee('Carte tableau');
});

test('sortTable toggles direction on the same column and resets on a new one', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'table')
        ->call('sortTable', 'title')
        ->assertSet('tableSort', 'title')->assertSet('tableDir', 'asc')
        ->call('sortTable', 'title')
        ->assertSet('tableDir', 'desc')
        ->call('sortTable', 'due')
        ->assertSet('tableSort', 'due')->assertSet('tableDir', 'asc');
});

test('sorting by title orders the rows', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Zebra']);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Alpha']);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('setView', 'table')
        ->call('sortTable', 'title')
        ->assertSeeInOrder(['Alpha', 'Zebra'])
        ->call('sortTable', 'title')
        ->assertSeeInOrder(['Zebra', 'Alpha']);
});

test('renameCard updates the title inline and ignores a blank value', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Ancien']);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('renameCard', $card->id, '  Nouveau  ')->assertHasNoErrors();
    expect($card->fresh()->title)->toBe('Nouveau');

    $component->call('renameCard', $card->id, '   ');
    expect($card->fresh()->title)->toBe('Nouveau');
});

test('setCardDue sets and clears the due date while keeping the start date', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['start_at' => now()->setTime(9, 0), 'due_at' => null]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('setCardDue', $card->id, '2026-10-01');
    expect($card->fresh()->due_at->format('Y-m-d H:i'))->toBe('2026-10-01 12:00')
        ->and($card->fresh()->start_at)->not->toBeNull();

    $component->call('setCardDue', $card->id, '');
    expect($card->fresh()->due_at)->toBeNull()
        ->and($card->fresh()->start_at)->not->toBeNull();
});

test('toggleCardMember assigns then unassigns a board member', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('toggleCardMember', $card->id, $member->id);
    expect($card->members()->whereKey($member->id)->exists())->toBeTrue();

    $component->call('toggleCardMember', $card->id, $member->id);
    expect($card->members()->whereKey($member->id)->exists())->toBeFalse();
});

test('toggleCardMember ignores a non board member', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $outsider = User::factory()->create();

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('toggleCardMember', $card->id, $outsider->id);

    expect($card->members()->whereKey($outsider->id)->exists())->toBeFalse();
});

test('toggleCardLabel adds then removes a label', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('toggleCardLabel', $card->id, $label->id);
    expect($card->labels()->whereKey($label->id)->exists())->toBeTrue();

    $component->call('toggleCardLabel', $card->id, $label->id);
    expect($card->labels()->whereKey($label->id)->exists())->toBeFalse();
});

test('setCardCustomField stores a text value and clears it when emptied', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = $board->customFields()->create(['name' => 'Sprint', 'type' => 'text', 'position' => 0]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('setCardCustomField', $card->id, $field->id, 'Sprint 12');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('Sprint 12');

    $component->call('setCardCustomField', $card->id, $field->id, '');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});

test('setCardCustomField only accepts valid select options', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $field = $board->customFields()->create(['name' => 'Priorité', 'type' => 'select', 'options' => ['Basse', 'Haute'], 'position' => 0]);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    $component->call('setCardCustomField', $card->id, $field->id, 'Haute');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->value('value'))->toBe('Haute');

    // An option not in the list is rejected (stored as null → row removed).
    $component->call('setCardCustomField', $card->id, $field->id, 'Inconnue');
    expect($card->customFieldValues()->where('custom_field_id', $field->id)->exists())->toBeFalse();
});
