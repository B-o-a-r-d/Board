<?php

use App\Enums\Role;
use App\Livewire\Settings\Profile;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, workspace: Workspace}
 */
function apiBoard(): array
{
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    return ['board' => $board, 'owner' => $owner, 'workspace' => $workspace];
}

test('the api requires authentication', function () {
    $this->getJson('/api/v1/boards')->assertUnauthorized();
});

test('a personal access token authenticates api requests', function () {
    ['board' => $board, 'owner' => $owner] = apiBoard();

    $token = $owner->createToken('cli')->plainTextToken;

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/boards')
        ->assertOk()
        ->assertJsonFragment(['id' => $board->public_id]);
});

test('a user lists and creates boards', function () {
    ['board' => $board, 'owner' => $owner, 'workspace' => $workspace] = apiBoard();
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/boards')->assertOk()->assertJsonFragment(['id' => $board->public_id]);

    $this->postJson('/api/v1/boards', ['workspace_id' => $workspace->public_id, 'name' => 'API board'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'API board');

    expect(Board::where('name', 'API board')->exists())->toBeTrue();
});

test('board show returns nested lists and cards', function () {
    ['board' => $board, 'owner' => $owner] = apiBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_list_id' => $list->id, 'board_id' => $board->id, 'title' => 'Carte API']);
    Sanctum::actingAs($owner);

    $this->getJson("/api/v1/boards/{$board->public_id}")
        ->assertOk()
        ->assertJsonPath('data.lists.0.cards.0.title', 'Carte API');
});

test('outsiders cannot access a board', function () {
    ['board' => $board] = apiBoard();
    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/v1/boards/{$board->public_id}")->assertForbidden();
});

test('admins update and delete a board, plain members cannot', function () {
    ['board' => $board, 'owner' => $owner] = apiBoard();
    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/boards/{$board->public_id}", ['name' => 'Renommé'])
        ->assertOk()->assertJsonPath('data.name', 'Renommé');

    $member = User::factory()->create();
    $board->members()->attach($member, ['role' => Role::Member->value]);
    Sanctum::actingAs($member);

    $this->patchJson("/api/v1/boards/{$board->public_id}", ['name' => 'X'])->assertForbidden();
    $this->deleteJson("/api/v1/boards/{$board->public_id}")->assertForbidden();

    Sanctum::actingAs($owner);
    $this->deleteJson("/api/v1/boards/{$board->public_id}")->assertNoContent();
    expect(Board::whereKey($board->id)->exists())->toBeFalse();
});

test('lists and cards can be fully managed', function () {
    ['board' => $board, 'owner' => $owner] = apiBoard();
    Sanctum::actingAs($owner);

    $listId = $this->postJson("/api/v1/boards/{$board->public_id}/lists", ['name' => 'À faire'])->assertCreated()->json('data.id');
    $cardId = $this->postJson("/api/v1/lists/{$listId}/cards", ['title' => 'Tâche'])->assertCreated()->json('data.id');

    $this->patchJson("/api/v1/cards/{$cardId}", ['completed' => true])->assertOk();
    expect(Card::firstWhere('public_id', $cardId)->completed_at)->not->toBeNull();

    $list2 = $this->postJson("/api/v1/boards/{$board->public_id}/lists", ['name' => 'Fait'])->json('data.id');
    $this->postJson("/api/v1/cards/{$cardId}/move", ['list_id' => $list2])
        ->assertOk()->assertJsonPath('data.list_id', $list2);

    $this->deleteJson("/api/v1/cards/{$cardId}")->assertNoContent();
    $this->deleteJson("/api/v1/lists/{$listId}")->assertNoContent();
});

test('labels can be managed', function () {
    ['board' => $board, 'owner' => $owner] = apiBoard();
    Sanctum::actingAs($owner);

    $labelId = $this->postJson("/api/v1/boards/{$board->public_id}/labels", ['name' => 'Bug', 'color' => '#ef4444'])
        ->assertCreated()->json('data.id');

    $this->patchJson("/api/v1/labels/{$labelId}", ['name' => 'Urgent'])->assertOk()->assertJsonPath('data.name', 'Urgent');
    $this->deleteJson("/api/v1/labels/{$labelId}")->assertNoContent();
});

test('the profile creates and revokes api tokens', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Profile::class)
        ->set('tokenName', 'Script de synchro')
        ->call('createToken');

    expect($user->tokens()->count())->toBe(1);

    $id = $user->tokens()->first()->id;

    Livewire::actingAs($user)->test(Profile::class)->call('revokeToken', $id);

    expect($user->fresh()->tokens()->count())->toBe(0);
});
