<?php

use App\Enums\BoardVisibility;
use App\Enums\Role;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Comment;
use App\Models\Label;
use App\Models\User;
use App\Models\Workspace;

test('a workspace exposes its owner, members and boards', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    Board::factory()->count(2)->create(['workspace_id' => $workspace->id]);

    expect($workspace->owner->is($owner))->toBeTrue()
        ->and($workspace->members)->toHaveCount(2)
        ->and($workspace->boards)->toHaveCount(2)
        ->and($workspace->memberRole($owner))->toBe(Role::Owner)
        ->and($workspace->memberRole($member))->toBe(Role::Member)
        ->and($workspace->hasMember($member))->toBeTrue();
});

test('board casts visibility and returns lists ordered by position', function () {
    $board = Board::factory()->create(['visibility' => BoardVisibility::Workspace]);

    BoardList::factory()->create(['board_id' => $board->id, 'name' => 'Second', 'position' => 2]);
    BoardList::factory()->create(['board_id' => $board->id, 'name' => 'First', 'position' => 1]);

    expect($board->visibility)->toBe(BoardVisibility::Workspace)
        ->and($board->lists->pluck('name')->all())->toBe(['First', 'Second']);
});

test('a card aggregates labels, members, checklists and comments', function () {
    $card = Card::factory()->create();

    $card->labels()->attach(Label::factory()->create(['board_id' => $card->board_id]));
    $card->members()->attach(User::factory()->create());

    $checklist = Checklist::factory()->create(['card_id' => $card->id]);
    ChecklistItem::factory()->count(3)->create(['checklist_id' => $checklist->id]);
    Comment::factory()->count(2)->create(['card_id' => $card->id]);

    $card->refresh();

    expect($card->labels)->toHaveCount(1)
        ->and($card->members)->toHaveCount(1)
        ->and($card->checklists)->toHaveCount(1)
        ->and($card->checklists->first()->items)->toHaveCount(3)
        ->and($card->comments)->toHaveCount(2)
        ->and($card->board->is($card->list->board))->toBeTrue();
});

test('a user exposes owned workspaces, memberships, boards and assigned cards', function () {
    $user = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $user->id]);
    $workspace->members()->attach($user, ['role' => Role::Owner->value]);

    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($user, ['role' => Role::Owner->value]);

    $card = Card::factory()->create(['board_id' => $board->id]);
    $card->members()->attach($user);

    expect($user->ownedWorkspaces)->toHaveCount(1)
        ->and($user->workspaces)->toHaveCount(1)
        ->and($user->boards)->toHaveCount(1)
        ->and($user->assignedCards)->toHaveCount(1);
});
