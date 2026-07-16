<?php

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
