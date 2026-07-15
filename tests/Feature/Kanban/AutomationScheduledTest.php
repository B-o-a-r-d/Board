<?php

use App\Automations\ScheduleMatcher;
use App\Models\Automation;
use App\Models\Card;
use App\Models\Label;
use Illuminate\Support\Carbon;

/** Phase 4: scheduled rules ("every day…") and due-date (±N days) rules. */
function scheduledRule(array $attributes): Automation
{
    return Automation::create(array_merge([
        'name' => 'Programmée',
        'action_type' => 'noop',
        'is_active' => true,
    ], $attributes));
}

// --- ScheduleMatcher ---------------------------------------------------------------

test('daily schedules fire once per occurrence', function () {
    $matcher = new ScheduleMatcher;
    $config = ['freq' => 'daily', 'at' => '09:00'];

    expect($matcher->isDue($config, Carbon::parse('2026-07-13 08:59'), null))->toBeFalse()
        ->and($matcher->isDue($config, Carbon::parse('2026-07-13 09:01'), null))->toBeTrue()
        // Already ran after today's occurrence → not due again…
        ->and($matcher->isDue($config, Carbon::parse('2026-07-13 10:00'), Carbon::parse('2026-07-13 09:01')))->toBeFalse()
        // …until the next day's occurrence.
        ->and($matcher->isDue($config, Carbon::parse('2026-07-14 09:05'), Carbon::parse('2026-07-13 09:01')))->toBeTrue();
});

test('weekday, biweekly, monthly and yearly schedules match their days', function () {
    $matcher = new ScheduleMatcher;

    // Selected weekdays: mondays only.
    $mondays = ['freq' => 'days', 'days' => ['monday'], 'at' => '09:00'];
    expect($matcher->occurrenceFor($mondays, Carbon::parse('2026-07-13 12:00')))->not->toBeNull() // a Monday
        ->and($matcher->occurrenceFor($mondays, Carbon::parse('2026-07-14 12:00')))->toBeNull();  // a Tuesday

    // Every 2 weeks anchored on 2026-07-06: that week matches, the next doesn't.
    $biweekly = ['freq' => 'every_n_weeks', 'n' => 2, 'days' => ['monday'], 'anchor' => '2026-07-06'];
    expect($matcher->occurrenceFor($biweekly, Carbon::parse('2026-07-06 12:00')))->not->toBeNull()
        ->and($matcher->occurrenceFor($biweekly, Carbon::parse('2026-07-13 12:00')))->toBeNull()
        ->and($matcher->occurrenceFor($biweekly, Carbon::parse('2026-07-20 12:00')))->not->toBeNull();

    // First Friday of the month: July 3rd 2026 yes, July 10th no.
    $firstFriday = ['freq' => 'monthly_first_dow', 'dow' => 'friday'];
    expect($matcher->occurrenceFor($firstFriday, Carbon::parse('2026-07-03 12:00')))->not->toBeNull()
        ->and($matcher->occurrenceFor($firstFriday, Carbon::parse('2026-07-10 12:00')))->toBeNull();

    // Monthly on the 31st clamps to the month length (June 30th).
    $monthly = ['freq' => 'monthly_day', 'day' => 31];
    expect($matcher->occurrenceFor($monthly, Carbon::parse('2026-06-30 12:00')))->not->toBeNull()
        ->and($matcher->occurrenceFor($monthly, Carbon::parse('2026-06-29 12:00')))->toBeNull();

    // Yearly on July 14th.
    $yearly = ['freq' => 'yearly', 'day' => 14, 'month' => 7];
    expect($matcher->occurrenceFor($yearly, Carbon::parse('2026-07-14 12:00')))->not->toBeNull()
        ->and($matcher->occurrenceFor($yearly, Carbon::parse('2026-07-15 12:00')))->toBeNull();
});

// --- automations:run-scheduled — scheduled rules -------------------------------------

test('a daily scheduled rule creates its card once per day, attributed to the rule creator', function () {
    Carbon::setTestNow('2026-07-13 08:00');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    scheduledRule([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'trigger_type' => 'scheduled',
        'trigger_config' => ['freq' => 'daily', 'at' => '09:00'],
        'actions' => [['type' => 'create_card', 'config' => ['title' => 'Standup', 'list_id' => $card->board_list_id]]],
    ]);

    // Before the occurrence: nothing.
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect(Card::where('title', 'Standup')->count())->toBe(0);

    // Past 09:00 → fires once, attributed to the creator.
    Carbon::setTestNow('2026-07-13 09:05');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect(Card::where('title', 'Standup')->count())->toBe(1)
        ->and(Card::where('title', 'Standup')->first()->created_by)->toBe($owner->id);

    // Same day, later tick → idempotent.
    Carbon::setTestNow('2026-07-13 09:20');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect(Card::where('title', 'Standup')->count())->toBe(1);

    // Next day → fires again.
    Carbon::setTestNow('2026-07-14 09:05');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect(Card::where('title', 'Standup')->count())->toBe(2);
});

test('a creatorless scheduled rule runs userless and never inherits the previous rule identity', function () {
    Carbon::setTestNow('2026-07-13 09:05');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();

    // Rule A runs first (lower id) under its creator, authenticating the process.
    scheduledRule([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'trigger_type' => 'scheduled',
        'trigger_config' => ['freq' => 'daily', 'at' => '09:00'],
        'actions' => [['type' => 'create_card', 'config' => ['title' => 'From A', 'list_id' => $card->board_list_id]]],
    ]);

    // Rule B has no creator: it must run userless, not carry over rule A's owner.
    scheduledRule([
        'board_id' => $board->id,
        'created_by' => null,
        'trigger_type' => 'scheduled',
        'trigger_config' => ['freq' => 'daily', 'at' => '09:00'],
        'actions' => [['type' => 'create_card', 'config' => ['title' => 'From B', 'list_id' => $card->board_list_id]]],
    ]);

    $this->artisan('automations:run-scheduled')->assertSuccessful();

    expect(Card::where('title', 'From A')->first()->created_by)->toBe($owner->id)
        ->and(Card::where('title', 'From B')->first()->created_by)->toBeNull();
});

// --- automations:run-scheduled — due-date (±N days) rules -----------------------------

test('a "3 days before due" rule fires once when the instant crosses', function () {
    Carbon::setTestNow('2026-07-13 08:00');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $label = Label::factory()->create(['board_id' => $board->id]);
    $card->update(['due_at' => Carbon::parse('2026-07-16 09:00')]);

    $rule = scheduledRule([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'trigger_type' => 'card.due_relative',
        'trigger_config' => ['days' => 3, 'direction' => 'before'],
        'actions' => [
            ['type' => 'assign_label', 'config' => ['label_id' => $label->id]],
            ['type' => 'post_comment', 'config' => ['body' => 'Échéance proche de {card}']],
        ],
    ]);

    // 08:30 — the relative instant (07-13 09:00) hasn't crossed yet.
    Carbon::setTestNow('2026-07-13 08:30');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect($card->labels()->count())->toBe(0);

    // 09:30 — crossed: label + comment (authored by the rule creator).
    Carbon::setTestNow('2026-07-13 09:30');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect($card->labels()->whereKey($label->id)->exists())->toBeTrue()
        ->and($card->comments()->count())->toBe(1)
        ->and($card->comments()->first()->user_id)->toBe($owner->id)
        ->and($rule->fresh()->runs_count)->toBe(1);

    // Later tick — the window moved past the instant: no refire.
    Carbon::setTestNow('2026-07-13 09:45');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect($rule->fresh()->runs_count)->toBe(1);
});

test('an "after due" rule fires once the delay has elapsed', function () {
    Carbon::setTestNow('2026-07-10 08:00');
    ['board' => $board, 'owner' => $owner, 'card' => $card] = makeCardContext();
    $card->update(['due_at' => Carbon::parse('2026-07-10 12:00')]);

    $rule = scheduledRule([
        'board_id' => $board->id,
        'created_by' => $owner->id,
        'trigger_type' => 'card.due_relative',
        'trigger_config' => ['days' => 1, 'direction' => 'after'],
        'actions' => [['type' => 'archive_card', 'config' => []]],
    ]);

    // Still before due + 1 day.
    Carbon::setTestNow('2026-07-11 11:00');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect($card->fresh()->archived_at)->toBeNull();

    // Crossed (07-11 12:00) → the overdue card is archived.
    Carbon::setTestNow('2026-07-11 13:00');
    $this->artisan('automations:run-scheduled')->assertSuccessful();
    expect($card->fresh()->archived_at)->not->toBeNull()
        ->and($rule->fresh()->runs_count)->toBe(1);
});
