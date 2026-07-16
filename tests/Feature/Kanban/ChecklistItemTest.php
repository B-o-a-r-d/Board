<?php

use App\Enums\Role;
use App\Livewire\Cards\CardDetail;
use App\Models\Card;
use App\Models\ChecklistItem;
use App\Models\User;
use Livewire\Livewire;

function makeChecklistItem(Card $card): ChecklistItem
{
    $checklist = $card->checklists()->create(['title' => 'Checklist', 'position' => 0]);

    return $checklist->items()->create(['content' => 'Sous-tâche', 'position' => 0]);
}

test('checking an item never changes the checklist order, even with tied positions', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'Checklist', 'position' => 0]);

    // Legacy data: every item at position 0 — order must still be stable (id tiebreaker).
    $first = $checklist->items()->create(['content' => 'Premier', 'position' => 0]);
    $second = $checklist->items()->create(['content' => 'Deuxième', 'position' => 0]);
    $third = $checklist->items()->create(['content' => 'Troisième', 'position' => 0]);

    $before = $checklist->items()->pluck('id')->all();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('toggleChecklistItem', $second->id);

    expect($second->fresh()->is_completed)->toBeTrue()
        ->and($checklist->items()->pluck('id')->all())->toBe($before)
        ->and($before)->toBe([$first->id, $second->id, $third->id]);
});

test('a checklist item can be assigned to a board member then unassigned', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $item = makeChecklistItem($card);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])->call('openCard', $card->id);

    $component->call('assignChecklistItem', $item->id, $member->id);
    expect($item->fresh()->assigned_to)->toBe($member->id);

    $component->call('assignChecklistItem', $item->id, null);
    expect($item->fresh()->assigned_to)->toBeNull();
});

test('a checklist item cannot be assigned to a non board member', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $item = makeChecklistItem($card);
    $outsider = User::factory()->create();

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('assignChecklistItem', $item->id, $outsider->id);

    expect($item->fresh()->assigned_to)->toBeNull();
});

test('a checklist item due date is stored at noon and can be cleared', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $item = makeChecklistItem($card);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])->call('openCard', $card->id);

    $component->call('setChecklistItemDue', $item->id, '2026-11-03');
    expect($item->fresh()->due_at->format('Y-m-d H:i'))->toBe('2026-11-03 12:00');

    $component->call('setChecklistItemDue', $item->id, '');
    expect($item->fresh()->due_at)->toBeNull();
});

// --- Conversion d'un item en carte + réordonnancement --------------------------

test('a checklist item converts into a card in the same list, Trello-style', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'Checklist', 'position' => 0]);
    $item = $checklist->items()->create([
        'content' => 'Devenir une carte',
        'position' => 0,
        'assigned_to' => $member->id,
        'due_at' => now()->addDays(3)->setTime(12, 0),
    ]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('convertChecklistItemToCard', $item->id)
        ->assertHasNoErrors();

    $created = $card->list->cards()->where('title', 'Devenir une carte')->firstOrFail();

    // Title, due date and assignee carried over; the item is gone.
    expect($created->due_at->format('Y-m-d H:i'))->toBe($item->due_at->format('Y-m-d H:i'))
        ->and($created->members()->whereKey($member->id)->exists())->toBeTrue()
        ->and($created->position)->toBeGreaterThan($card->position)
        ->and(ChecklistItem::whereKey($item->id)->exists())->toBeFalse()
        ->and($board->activities()->where('type', 'checklist.item.converted')->exists())->toBeTrue();
});

test('a read-only observer cannot convert a checklist item', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $observer = User::factory()->create();
    $board->members()->attach($observer, ['role' => Role::Observer->value]);
    $item = makeChecklistItem($card);

    Livewire::actingAs($observer)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('convertChecklistItemToCard', $item->id)
        ->assertForbidden();

    expect(ChecklistItem::whereKey($item->id)->exists())->toBeTrue();
});

test('a checklist item reorders by drag and drop, with clamped positions', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $checklist = $card->checklists()->create(['title' => 'Checklist', 'position' => 0]);
    $a = $checklist->items()->create(['content' => 'A', 'position' => 0]);
    $b = $checklist->items()->create(['content' => 'B', 'position' => 1]);
    $c = $checklist->items()->create(['content' => 'C', 'position' => 2]);

    $component = Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id);

    // C dragged to the top.
    $component->call('moveChecklistItem', $c->id, 0);
    expect($checklist->items()->pluck('id')->all())->toBe([$c->id, $a->id, $b->id]);

    // A dragged past the end: clamped to the last slot, positions resequenced.
    $component->call('moveChecklistItem', $a->id, 99);
    expect($checklist->items()->pluck('id')->all())->toBe([$c->id, $b->id, $a->id])
        ->and($checklist->items()->pluck('position')->all())->toBe([0, 1, 2]);
});
