<?php

use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\CardTemplate;
use App\Models\User;
use Livewire\Livewire;

test('an admin can save a card as a global template but a member cannot', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $owner->update(['is_admin' => true]);
    $card->update(['title' => 'Ticket type', 'description' => 'Gabarit']);
    $checklist = $card->checklists()->create(['title' => 'DoD', 'position' => 0]);
    $checklist->items()->create(['content' => 'Tests écrits', 'position' => 0]);

    Livewire::actingAs($owner)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveAsTemplate');

    $template = CardTemplate::firstOrFail();
    expect($template->title)->toBe('Ticket type')
        ->and($template->checklists)->toBe([['title' => 'DoD', 'items' => ['Tests écrits']]]);

    // A non-admin member is forbidden.
    $member = User::factory()->create();
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)
        ->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('saveAsTemplate')
        ->assertForbidden();
});

test('a member can add a card from a template into a list', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $list = $board->lists()->create(['name' => 'À faire', 'position' => 0]);

    $template = CardTemplate::create([
        'created_by' => $owner->id,
        'name' => 'Bug report',
        'title' => 'Bug : ',
        'description' => 'Étapes de repro…',
        'checklists' => [['title' => 'Vérifs', 'items' => ['Repro', 'Logs']]],
    ]);

    Livewire::actingAs($owner)
        ->test(Show::class, ['board' => $board])
        ->call('addCardFromTemplate', $list->id, $template->id);

    $card = $list->cards()->firstOrFail();
    expect($card->title)->toBe('Bug : ')
        ->and($card->description)->toBe('Étapes de repro…')
        ->and($card->checklists()->count())->toBe(1)
        ->and($card->checklists()->first()->items()->count())->toBe(2);
});
