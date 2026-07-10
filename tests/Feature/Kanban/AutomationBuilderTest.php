<?php

use App\Enums\Role;
use App\Events\BoardActivity;
use App\Livewire\Boards\Automations;
use App\Livewire\Boards\Show;
use App\Models\Automation;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Label;
use Illuminate\Support\Facades\Event;
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

test('a board button is created from its own section with an icon and board-scope actions', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('setSection', 'board_buttons')
        ->call('startCreate')
        ->assertSet('step', 2)
        ->call('addAction', 'archive_card')   // card action → refused (board scope only)
        ->call('addAction', 'sort_list')
        ->set('actions.0.config.list_id', $card->board_list_id)
        ->set('actions.0.config.by', 'due')
        ->set('name', 'Trier le backlog')
        ->set('triggerConfig.icon', 'broom')
        ->call('save')
        ->assertHasNoErrors();

    $button = $board->automations()->where('trigger_type', 'board_button')->first();

    expect($button->name)->toBe('Trier le backlog')
        ->and($button->trigger_config['icon'])->toBe('broom')
        ->and(array_column($button->actionList(), 'type'))->toBe(['sort_list']);
});

test('a board button renders in the topbar and runs its pipeline on click', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $button = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Carte du jour',
        'trigger_type' => 'board_button',
        'trigger_config' => ['icon' => 'rocket'],
        'action_type' => 'create_card',
        'actions' => [['type' => 'create_card', 'config' => ['title' => 'Daily', 'list_id' => $card->board_list_id]]],
        'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->assertSee('Carte du jour')
        ->call('runBoardButton', $button->id);

    expect(Card::where('title', 'Daily')->count())->toBe(1)
        ->and($button->fresh()->runs_count)->toBe(1);
});

test('observers neither see nor run board buttons', function () {
    ['board' => $board, 'owner' => $owner, 'outsider' => $observer, 'card' => $card] = makeCardContext();
    $board->workspace->members()->attach($observer, ['role' => Role::Observer->value]);
    $board->members()->attach($observer, ['role' => Role::Observer->value]);

    $button = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Bouton privé',
        'trigger_type' => 'board_button',
        'action_type' => 'create_card',
        'actions' => [['type' => 'create_card', 'config' => ['title' => 'X', 'list_id' => $card->board_list_id]]],
        'is_active' => true,
    ]);

    Livewire::actingAs($observer)->test(Show::class, ['board' => $board])
        ->assertDontSee('Bouton privé')
        ->call('runBoardButton', $button->id)
        ->assertForbidden();

    expect(Card::where('title', 'X')->count())->toBe(0);
});

test('saving, toggling and deleting a rule broadcast a board activity for live updates', function () {
    Event::fake([BoardActivity::class]);
    ['board' => $board, 'owner' => $owner] = makeCardContext();

    $component = Livewire::actingAs($owner)
        ->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('startCreate')
        ->call('pickTrigger', 'card.completed')
        ->call('addAction', 'archive_card')
        ->call('save')
        ->assertHasNoErrors();

    Event::assertDispatched(BoardActivity::class,
        fn ($e) => $e->boardId === $board->id && $e->action === 'automations.changed');

    $automation = $board->automations()->first();
    Event::fake([BoardActivity::class]);
    $component->call('toggleActive', $automation->id);
    Event::assertDispatched(BoardActivity::class);
});
