<?php

use App\Livewire\Boards\Automations;
use App\Models\Automation;
use App\Models\BoardList;
use App\Models\Label;
use Livewire\Livewire;

/** Phase 5: the Butler-style builder (sections, 3-step wizard, listing). */
test('opening the builder requires board update permission', function () {
    ['board' => $board, 'member' => $member] = makeCardContext();

    Livewire::actingAs($member)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->assertForbidden();
});

test('the wizard creates a multi-action rule with condition and actor scope', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Terminé']);
    $label = Label::factory()->create(['board_id' => $board->id, 'name' => 'Urgent']);

    Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('startCreate')
        ->call('pickTrigger', 'card.moved_to_list')
        ->set('triggerConfig.list_id', $target->id)
        ->set('actorScope', 'me')
        ->call('goToStep', 2)
        ->call('addAction', 'assign_label')
        ->set('actions.0.config.label_id', $label->id)
        ->call('addAction', 'archive_card')
        ->call('addCondition', 'has_label')
        ->set('conditions.0.config.label_id', $label->id)
        ->call('goToStep', 3)
        ->call('save')
        ->assertHasNoErrors();

    $automation = $board->automations()->first();

    expect($automation->trigger_type)->toBe('card.moved_to_list')
        ->and($automation->actor_scope)->toBe('me')
        ->and(array_column($automation->actionList(), 'type'))->toBe(['assign_label', 'archive_card'])
        ->and($automation->conditionList()[0]['type'])->toBe('has_label')
        // The default name is the natural-language sentence.
        ->and($automation->name)->toContain('Terminé')
        ->and($automation->name)->toContain('par moi');
});

test('the listing renders the rule as a natural-language sentence', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $target = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Backlog']);

    Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Ma règle',
        'trigger_type' => 'card.moved_to_list',
        'trigger_config' => ['list_id' => $target->id],
        'action_type' => 'archive_card',
        'actions' => [['type' => 'archive_card', 'config' => []]],
        'is_active' => true,
    ]);

    Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->assertSee('Backlog')
        ->assertSee(__('archiver la carte'));
});

test('a scheduled rule only accepts board-scope actions', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('setSection', 'scheduled')
        ->call('startCreate')
        ->call('pickSchedule', 'daily')
        ->call('addAction', 'archive_card')   // card action → refused
        ->call('addAction', 'create_card')
        ->set('actions.0.config.title', 'Standup')
        ->set('actions.0.config.list_id', $card->board_list_id)
        ->call('save')
        ->assertHasNoErrors();

    $automation = $board->automations()->first();

    expect($automation->trigger_type)->toBe('scheduled')
        ->and($automation->trigger_config['freq'])->toBe('daily')
        ->and(array_column($automation->actionList(), 'type'))->toBe(['create_card']);
});

test('a card button requires a name and stores a manual trigger', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('setSection', 'buttons')
        ->call('startCreate')
        ->assertSet('step', 2)
        ->call('addAction', 'mark_complete')
        ->call('save')
        ->assertHasErrors('name');

    $component->set('name', 'Terminer et ranger')
        ->call('save')
        ->assertHasNoErrors();

    expect($board->automations()->where('trigger_type', 'manual')->where('name', 'Terminer et ranger')->exists())->toBeTrue();
});

test('saving without any action is refused', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('startCreate')
        ->call('pickTrigger', 'card.completed')
        ->call('save')
        ->assertHasErrors('actions');

    expect($board->automations()->count())->toBe(0);
});

test('rules can be toggled, duplicated (disabled) and deleted from the listing', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Règle',
        'trigger_type' => 'card.completed',
        'action_type' => 'archive_card',
        'actions' => [['type' => 'archive_card', 'config' => []]],
        'is_active' => true,
    ]);

    $component = Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])->call('open');

    $component->call('toggleActive', $automation->id);
    expect($automation->fresh()->is_active)->toBeFalse();

    $component->call('duplicateAutomation', $automation->id);
    $copy = $board->automations()->whereKeyNot($automation->id)->first();
    expect($copy->name)->toContain(__('(copie)'))
        ->and($copy->is_active)->toBeFalse()
        ->and($copy->runs_count)->toBe(0);

    $component->call('deleteAutomation', $automation->id);
    expect($board->automations()->whereKey($automation->id)->exists())->toBeFalse();
});
