<?php

use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Livewire\Dashboard;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use App\Services\BoardTemplateService;
use Livewire\Livewire;

function makeTemplateBoard(): Board
{
    $admin = User::factory()->create(['is_admin' => true]);
    $workspace = Workspace::factory()->create(['owner_id' => $admin->id]);
    $workspace->members()->attach($admin, ['role' => Role::Owner->value]);

    $board = Board::factory()->create(['workspace_id' => $workspace->id, 'is_template' => true, 'name' => 'Modèle Scrum']);
    $board->members()->attach($admin, ['role' => Role::Owner->value]);

    $label = $board->labels()->create(['name' => 'Bug', 'color' => '#ef4444']);
    $list = BoardList::factory()->create(['board_id' => $board->id, 'name' => 'À faire', 'position' => 0]);
    $card = Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Tâche modèle', 'position' => 0]);
    $card->labels()->attach($label);
    $checklist = $card->checklists()->create(['title' => 'Étapes', 'position' => 0]);
    $checklist->items()->create(['content' => 'Étape 1', 'position' => 0]);

    return $board;
}

/**
 * @return array{user: User, workspace: Workspace}
 */
function makeUserWorkspace(): array
{
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    return ['user' => $user, 'workspace' => $workspace];
}

test('the service instantiates a board from a template', function () {
    $template = makeTemplateBoard();
    ['user' => $user, 'workspace' => $workspace] = makeUserWorkspace();

    $board = app(BoardTemplateService::class)->instantiate($template, $workspace, $user, 'Mon sprint');

    expect($board->name)->toBe('Mon sprint')
        ->and($board->is_template)->toBeFalse()
        ->and($board->workspace_id)->toBe($workspace->id)
        ->and($board->memberRole($user))->toBe(Role::Owner)
        ->and($board->lists()->count())->toBe(1)
        ->and($board->labels()->count())->toBe(1);

    $newCard = $board->cards()->firstOrFail();

    expect($newCard->title)->toBe('Tâche modèle')
        ->and($newCard->labels()->count())->toBe(1)
        ->and($newCard->checklists()->count())->toBe(1)
        ->and($newCard->checklists()->first()->items()->count())->toBe(1);
});

test('an admin can flag a board as a global template but a plain member cannot', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $workspace = Workspace::factory()->create(['owner_id' => $admin->id]);
    $workspace->members()->attach($admin, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($admin, ['role' => Role::Owner->value]);

    Livewire::actingAs($admin)->test(Show::class, ['board' => $board])->call('toggleTemplate');
    expect($board->fresh()->is_template)->toBeTrue();

    $member = User::factory()->create();
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire::actingAs($member)->test(Show::class, ['board' => $board])->call('toggleTemplate')->assertForbidden();
});

test('the dashboard creates a board from a template', function () {
    $template = makeTemplateBoard();
    ['user' => $user, 'workspace' => $workspace] = makeUserWorkspace();

    Livewire::actingAs($user)->test(Dashboard::class)
        ->call('openTemplateModal', $template->id)
        ->assertSet('templateToUse', $template->id)
        ->set('templateWorkspaceId', $workspace->id)
        ->set('templateBoardName', 'Depuis modèle')
        ->call('createFromTemplate')
        ->assertRedirect();

    expect(Board::where('name', 'Depuis modèle')->where('is_template', false)->exists())->toBeTrue();
});
