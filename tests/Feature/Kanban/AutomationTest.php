<?php

use App\Automations\AutomationEngine;
use App\Automations\AutomationRegistry;
use App\Enums\Role;
use App\Livewire\Boards\Automations;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Models\Automation;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User}
 */
function makeAutoBoard(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    return ['board' => $board, 'owner' => $owner];
}

test('the registry exposes the code-defined trigger and action library', function () {
    $registry = app(AutomationRegistry::class);

    expect($registry->trigger('card.moved_to_list'))->not->toBeNull()
        ->and($registry->action('mark_complete'))->not->toBeNull()
        ->and($registry->actions())->toHaveKey('assign_label')
        ->and($registry->triggers())->toHaveKey('manual');
});

test('moving a card into a target list runs its automation', function () {
    ['board' => $board, 'owner' => $owner] = makeAutoBoard();
    $source = BoardList::factory()->create(['board_id' => $board->id]);
    $done = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Terminé']);
    $card = Card::factory()->create(['board_list_id' => $source->id, 'board_id' => $board->id, 'position' => 0]);

    Automation::create([
        'board_id' => $board->id, 'name' => 'Auto-terminé', 'is_active' => true,
        'trigger_type' => 'card.moved_to_list', 'trigger_config' => ['list_id' => $done->id],
        'action_type' => 'mark_complete', 'action_config' => [],
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('moveCard', $card->id, 0, $done->id);

    expect($card->fresh()->completed_at)->not->toBeNull();
});

test('creating a card in a list runs its automation (assign a label)', function () {
    ['board' => $board, 'owner' => $owner] = makeAutoBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $label = $board->labels()->create(['name' => 'Auto', 'color' => '#000000']);

    Automation::create([
        'board_id' => $board->id, 'name' => 'Tag à la création', 'is_active' => true,
        'trigger_type' => 'card.created', 'trigger_config' => ['list_id' => $list->id],
        'action_type' => 'assign_label', 'action_config' => ['label_id' => $label->id],
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set("newCardTitle.{$list->id}", 'Nouvelle carte')
        ->call('addCard', $list->id);

    expect($list->cards()->firstOrFail()->labels()->whereKey($label->id)->exists())->toBeTrue();
});

test('a manual automation runs only on demand', function () {
    ['board' => $board] = makeAutoBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id]);

    $automation = Automation::create([
        'board_id' => $board->id, 'name' => 'Bouton archiver', 'is_active' => true,
        'trigger_type' => 'manual', 'trigger_config' => [],
        'action_type' => 'archive_card', 'action_config' => [],
    ]);

    app(AutomationEngine::class)->runManual($automation, $card);

    expect($card->fresh()->archived_at)->not->toBeNull();
});

test('a board admin can build and delete an automation from the UI', function () {
    ['board' => $board, 'owner' => $owner] = makeAutoBoard();
    $done = BoardList::factory()->create(['board_id' => $board->id]);

    $component = Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])
        ->call('startCreate')
        ->set('name', 'Terminer dans Fait')
        ->set('triggerType', 'card.moved_to_list')
        ->set('triggerConfig.list_id', $done->id)
        ->set('actionType', 'mark_complete')
        ->call('save')
        ->assertHasNoErrors();

    $automation = $board->automations()->firstOrFail();
    expect($automation->name)->toBe('Terminer dans Fait')
        ->and($automation->trigger_config)->toBe(['list_id' => $done->id]);

    $component->call('deleteAutomation', $automation->id);
    expect($board->automations()->count())->toBe(0);
});

test('a board admin can edit an existing automation', function () {
    ['board' => $board, 'owner' => $owner] = makeAutoBoard();
    $done = BoardList::factory()->create(['board_id' => $board->id]);

    $automation = Automation::create([
        'board_id' => $board->id, 'name' => 'Ancien nom', 'is_active' => true,
        'trigger_type' => 'card.moved_to_list', 'trigger_config' => ['list_id' => $done->id],
        'action_type' => 'mark_complete', 'action_config' => [],
    ]);

    Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])
        ->call('startEdit', $automation->id)
        ->assertSet('editingId', $automation->id)
        ->assertSet('name', 'Ancien nom')
        ->assertSet('triggerType', 'card.moved_to_list')
        ->set('name', 'Nouveau nom')
        ->set('actionType', 'archive_card')
        ->call('save')
        ->assertHasNoErrors();

    $automation->refresh();
    expect($automation->name)->toBe('Nouveau nom')
        ->and($automation->action_type)->toBe('archive_card')
        ->and($board->automations()->count())->toBe(1);
});

test('a manual automation runs as a card button', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $automation = Automation::create([
        'board_id' => $board->id, 'name' => 'Archiver', 'is_active' => true,
        'trigger_type' => 'manual', 'trigger_config' => [],
        'action_type' => 'archive_card', 'action_config' => [],
    ]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('runAutomation', $automation->id);

    expect($card->fresh()->archived_at)->not->toBeNull();
});

test('the scheduled command runs due-soon automations', function () {
    ['board' => $board] = makeAutoBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $label = $board->labels()->create(['name' => 'Urgent', 'color' => '#ef4444']);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'due_at' => now()->addHours(3)]);

    Automation::create([
        'board_id' => $board->id, 'name' => 'Tag due soon', 'is_active' => true,
        'trigger_type' => 'card.due_soon', 'trigger_config' => [],
        'action_type' => 'assign_label', 'action_config' => ['label_id' => $label->id],
    ]);

    $this->artisan('automations:run-scheduled')->assertSuccessful();

    expect($card->fresh()->labels()->whereKey($label->id)->exists())->toBeTrue();
});

test('an inactive automation does not run', function () {
    ['board' => $board, 'owner' => $owner] = makeAutoBoard();
    $source = BoardList::factory()->create(['board_id' => $board->id]);
    $done = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_list_id' => $source->id, 'board_id' => $board->id, 'position' => 0]);

    Automation::create([
        'board_id' => $board->id, 'name' => 'Désactivée', 'is_active' => false,
        'trigger_type' => 'card.moved_to_list', 'trigger_config' => ['list_id' => $done->id],
        'action_type' => 'mark_complete', 'action_config' => [],
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('moveCard', $card->id, 0, $done->id);

    expect($card->fresh()->completed_at)->toBeNull();
});
