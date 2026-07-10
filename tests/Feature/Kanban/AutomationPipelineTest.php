<?php

use App\Models\Automation;
use Illuminate\Support\Facades\DB;

/**
 * Phase 0 of the Butler plan: schema + model groundwork for multi-action
 * pipelines, conditions and the "by me" actor scope. The engine still runs
 * the legacy single action until Phase 1 switches it over.
 */
function makeAutomationBoard(): array
{
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeCardContext();

    return compact('board', 'owner', 'member');
}

test('a legacy single-action rule is exposed as a one-action pipeline', function () {
    ['board' => $board, 'owner' => $owner] = makeAutomationBoard();

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Règle héritée',
        'trigger_type' => 'card.completed',
        'action_type' => 'move_to_list',
        'action_config' => ['list_id' => 42],
    ]);

    expect($automation->actionList())->toBe([
        ['type' => 'move_to_list', 'config' => ['list_id' => 42]],
    ]);
});

test('a rule stores an ordered multi-action pipeline and AND conditions', function () {
    ['board' => $board, 'owner' => $owner] = makeAutomationBoard();

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Pipeline',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => [
            ['type' => 'assign_label', 'config' => ['label_id' => 1]],
            ['type' => 'move_to_list', 'config' => ['list_id' => 2]],
            ['type' => 'archive_card', 'config' => []],
        ],
        'conditions' => [
            ['type' => 'has_label', 'config' => ['label_id' => 1]],
            ['type' => 'in_list', 'config' => ['list_id' => 3]],
        ],
    ]);

    $fresh = $automation->fresh();

    expect($fresh->actionList())->toHaveCount(3)
        ->and(array_column($fresh->actionList(), 'type'))->toBe(['assign_label', 'move_to_list', 'archive_card'])
        ->and($fresh->conditionList())->toHaveCount(2)
        ->and($fresh->conditionList()[0])->toBe(['type' => 'has_label', 'config' => ['label_id' => 1]]);
});

test('malformed pipeline entries are ignored, order is preserved', function () {
    ['board' => $board, 'owner' => $owner] = makeAutomationBoard();

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Sale',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => [
            ['type' => 'first', 'config' => []],
            ['config' => ['orphan' => true]],       // no type → dropped
            'not-an-array',                          // dropped
            ['type' => 'second'],                    // config defaults to []
        ],
    ]);

    expect($automation->fresh()->actionList())->toBe([
        ['type' => 'first', 'config' => []],
        ['type' => 'second', 'config' => []],
    ]);
});

test('the actor scope "me" only allows the rule creator', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makeAutomationBoard();

    $anyone = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Tous',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
    ]);

    $mine = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Par moi',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actor_scope' => Automation::ACTOR_ME,
    ]);

    expect($anyone->fresh()->actor_scope)->toBe(Automation::ACTOR_ANYONE)
        ->and($anyone->fresh()->actorAllowed($member->id))->toBeTrue()
        ->and($mine->actorAllowed($owner->id))->toBeTrue()
        ->and($mine->actorAllowed($member->id))->toBeFalse()
        ->and($mine->actorAllowed(null))->toBeFalse();
});

test('the migration backfills legacy rows into one-action pipelines', function () {
    ['board' => $board, 'owner' => $owner] = makeAutomationBoard();

    // Simulate a pre-migration row: legacy columns set, pipeline column empty.
    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Ancienne',
        'trigger_type' => 'card.completed',
        'action_type' => 'assign_member',
        'action_config' => ['user_id' => 7],
    ]);
    DB::table('automations')->where('id', $automation->id)->update(['actions' => null]);

    // Re-run the backfill exactly as the migration does.
    DB::table('automations')->whereNull('actions')->orderBy('id')->each(function ($row) {
        DB::table('automations')->where('id', $row->id)->update([
            'actions' => json_encode([[
                'type' => $row->action_type,
                'config' => json_decode($row->action_config ?? 'null', true) ?? [],
            ]]),
        ]);
    });

    expect($automation->fresh()->actions)->toBe([
        ['type' => 'assign_member', 'config' => ['user_id' => 7]],
    ]);
});
