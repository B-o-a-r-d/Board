<?php

use App\Automations\AutomationEngine;
use App\Automations\AutomationRegistry;
use App\Automations\Contracts\AutomationAction;
use App\Livewire\Boards\Automations;
use App\Models\Activity;
use App\Models\Automation;
use App\Models\AutomationRun;
use App\Models\Card;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/** Phase 8: execution journal + auto-quarantine after repeated failures. */
class AlwaysBoomAction implements AutomationAction
{
    public static function key(): string
    {
        return 'always_boom';
    }

    public function label(): string
    {
        return 'Boom';
    }

    public function configFields(): array
    {
        return [];
    }

    public function run(Card $card, array $config): void
    {
        throw new RuntimeException('kaboom');
    }
}

function boomRule(int $boardId, int $userId): Automation
{
    app(AutomationRegistry::class)->registerAction(new AlwaysBoomAction);

    return Automation::create([
        'board_id' => $boardId,
        'created_by' => $userId,
        'name' => 'Casse tout',
        'trigger_type' => 'card.completed',
        'action_type' => 'noop',
        'is_active' => true,
        'actions' => [['type' => 'always_boom', 'config' => []]],
    ]);
}

test('each pipeline execution records a run with its status', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    Automation::create([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'name' => 'OK',
        'trigger_type' => 'card.completed',
        'action_type' => 'archive_card',
        'is_active' => true,
        'actions' => [['type' => 'archive_card', 'config' => []]],
    ]);

    app(AutomationEngine::class)->fire('card.completed', $card);

    $run = AutomationRun::where('board_id', $board->id)->first();
    expect($run)->not->toBeNull()
        ->and($run->status)->toBe('success')
        ->and($run->actions_run)->toBe(1)
        ->and($run->actions_failed)->toBe(0)
        ->and($run->card_id)->toBe($card->id);
});

test('a failing pipeline records a failed run with the error and counts consecutive failures', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $rule = boomRule($board->id, $owner->id);

    app(AutomationEngine::class)->fire('card.completed', $card);

    $run = AutomationRun::where('automation_id', $rule->id)->first();
    expect($run->status)->toBe('failed')
        ->and($run->actions_failed)->toBe(1)
        ->and($run->error)->toContain('kaboom')
        ->and($rule->fresh()->consecutive_failures)->toBe(1);
});

test('a rule is auto-disabled after MAX_CONSECUTIVE_FAILURES and logs a board activity', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $rule = boomRule($board->id, $owner->id);
    $engine = app(AutomationEngine::class);

    for ($i = 0; $i < AutomationEngine::MAX_CONSECUTIVE_FAILURES; $i++) {
        $engine->fire('card.completed', $card);
    }

    expect($rule->fresh()->is_active)->toBeFalse()
        ->and(Activity::where('board_id', $board->id)->where('type', 'automation.disabled')->exists())->toBeTrue();

    // Disabled → no longer fires.
    $before = AutomationRun::where('automation_id', $rule->id)->count();
    $engine->fire('card.completed', $card);
    expect(AutomationRun::where('automation_id', $rule->id)->count())->toBe($before);
});

test('a successful action resets the consecutive-failure counter', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $rule = boomRule($board->id, $owner->id);
    $engine = app(AutomationEngine::class);

    $engine->fire('card.completed', $card);
    expect($rule->fresh()->consecutive_failures)->toBe(1);

    // Swap to a working action → the next run resets the counter.
    $rule->update(['actions' => [['type' => 'archive_card', 'config' => []]]]);
    $engine->fire('card.completed', $card);
    expect($rule->fresh()->consecutive_failures)->toBe(0);
});

test('the builder Activity section lists recent runs', function () {
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    $rule = Automation::create([
        'board_id' => $board->id, 'created_by' => $owner->id, 'name' => 'Ma règle suivie',
        'trigger_type' => 'card.completed', 'action_type' => 'archive_card', 'is_active' => true,
        'actions' => [['type' => 'archive_card', 'config' => []]],
    ]);
    AutomationRun::create([
        'automation_id' => $rule->id, 'board_id' => $board->id, 'card_id' => $card->id,
        'status' => 'success', 'actions_run' => 1, 'actions_failed' => 0,
    ]);

    Livewire::actingAs($owner)->test(Automations::class, ['board' => $board])
        ->call('open')
        ->call('setSection', 'activity')
        ->assertSee('Ma règle suivie')
        ->assertSee(__('Succès'));
});

test('activities:prune deletes automation runs older than 30 days', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    $rule = Automation::create([
        'board_id' => $board->id, 'created_by' => $owner->id, 'name' => 'R',
        'trigger_type' => 'card.completed', 'action_type' => 'archive_card', 'is_active' => true,
    ]);

    $old = AutomationRun::create(['automation_id' => $rule->id, 'board_id' => $board->id, 'status' => 'success', 'actions_run' => 1]);
    $recent = AutomationRun::create(['automation_id' => $rule->id, 'board_id' => $board->id, 'status' => 'success', 'actions_run' => 1]);
    DB::table('automation_runs')->where('id', $old->id)->update(['created_at' => now()->subDays(40)]);

    $this->artisan('activities:prune')->assertSuccessful();

    expect(AutomationRun::whereKey($old->id)->exists())->toBeFalse()
        ->and(AutomationRun::whereKey($recent->id)->exists())->toBeTrue();
});
