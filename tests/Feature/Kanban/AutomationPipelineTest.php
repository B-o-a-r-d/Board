<?php

use App\Automations\AutomationEngine;
use App\Automations\AutomationRegistry;
use App\Automations\Contracts\AutomationAction;
use App\Models\Automation;
use App\Models\Card;
use App\Models\Label;
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

// --- Phase 1: engine pipeline -------------------------------------------------------

abstract class FakePipelineAction implements AutomationAction
{
    /** @var array<int, string> shared execution trace across all fake actions */
    public static array $log = [];

    public function label(): string
    {
        return static::key();
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        self::$log[] = static::key();
    }
}

class FakeActionA extends FakePipelineAction
{
    public static function key(): string
    {
        return 'fake_a';
    }
}

class FakeActionB extends FakePipelineAction
{
    public static function key(): string
    {
        return 'fake_b';
    }
}

class FakeBoomAction extends FakePipelineAction
{
    public static function key(): string
    {
        return 'fake_boom';
    }

    public function run(Card $card, array $config): void
    {
        throw new RuntimeException('boom');
    }
}

function registerFakeActions(): void
{
    FakePipelineAction::$log = [];
    $registry = app(AutomationRegistry::class);
    $registry->registerAction(new FakeActionA);
    $registry->registerAction(new FakeActionB);
    $registry->registerAction(new FakeBoomAction);
}

test('the engine runs a multi-action pipeline in order and updates the counters', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    registerFakeActions();

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Pipeline ordonné',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => [
            ['type' => 'fake_a', 'config' => []],
            ['type' => 'fake_b', 'config' => []],
        ],
    ]);

    $ran = app(AutomationEngine::class)->fire('card.completed', $card);

    expect($ran)->toBe(2)
        ->and(FakePipelineAction::$log)->toBe(['fake_a', 'fake_b']);

    $automation->refresh();
    expect($automation->runs_count)->toBe(1)
        ->and($automation->failures_count)->toBe(0)
        ->and($automation->last_run_at)->not->toBeNull();
});

test('a failing or unknown action is isolated and counted, the pipeline continues', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    registerFakeActions();

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Échec isolé',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => [
            ['type' => 'fake_a', 'config' => []],
            ['type' => 'fake_boom', 'config' => []],
            ['type' => 'unknown_action', 'config' => []],
            ['type' => 'fake_b', 'config' => []],
        ],
    ]);

    $ran = app(AutomationEngine::class)->fire('card.completed', $card);

    expect($ran)->toBe(2)
        ->and(FakePipelineAction::$log)->toBe(['fake_a', 'fake_b'])
        ->and($automation->fresh()->failures_count)->toBe(2);
});

test('AND conditions gate the pipeline and an unknown condition fails closed', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    registerFakeActions();
    $label = Label::factory()->create(['board_id' => $board->id]);

    $automation = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Conditionnée',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => [['type' => 'fake_a', 'config' => []]],
        'conditions' => [
            ['type' => 'in_list', 'config' => ['list_id' => $card->board_list_id]],
            ['type' => 'has_label', 'config' => ['label_id' => $label->id]],
        ],
    ]);

    $engine = app(AutomationEngine::class);

    // Label missing → the AND fails, nothing runs.
    expect($engine->fire('card.completed', $card))->toBe(0)
        ->and($automation->fresh()->runs_count)->toBe(0);

    // Both conditions satisfied → the pipeline runs.
    $card->labels()->attach($label);
    expect($engine->fire('card.completed', $card))->toBe(1);

    // A rule referencing a removed/unknown condition never fires.
    $automation->update(['conditions' => [['type' => 'ghost_condition', 'config' => []]]]);
    FakePipelineAction::$log = [];
    expect($engine->fire('card.completed', $card))->toBe(0)
        ->and(FakePipelineAction::$log)->toBe([]);
});

test('the "by me" actor scope only fires for the rule creator', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member, 'card' => $card] = makeCardContext();
    registerFakeActions();

    Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Par moi',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => [['type' => 'fake_a', 'config' => []]],
        'actor_scope' => Automation::ACTOR_ME,
    ]);

    $engine = app(AutomationEngine::class);

    $this->actingAs($member);
    expect($engine->fire('card.completed', $card))->toBe(0);

    $this->actingAs($owner);
    expect($engine->fire('card.completed', $card))->toBe(1);
});

test('a pipeline is capped at MAX_ACTIONS steps', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    registerFakeActions();

    Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Débordement',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'actions' => array_fill(0, 12, ['type' => 'fake_a', 'config' => []]),
    ]);

    $ran = app(AutomationEngine::class)->fire('card.completed', $card);

    expect($ran)->toBe(AutomationEngine::MAX_ACTIONS)
        ->and(FakePipelineAction::$log)->toHaveCount(10);
});

test('a card button (manual trigger) runs its whole pipeline', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    registerFakeActions();

    $button = Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'Bouton',
        'trigger_type' => 'manual',
        'action_type' => 'noop',
        'is_active' => true,
        'actions' => [
            ['type' => 'fake_a', 'config' => []],
            ['type' => 'fake_b', 'config' => []],
        ],
    ]);

    expect(app(AutomationEngine::class)->runManual($button, $card))->toBeTrue()
        ->and(FakePipelineAction::$log)->toBe(['fake_a', 'fake_b']);
});
