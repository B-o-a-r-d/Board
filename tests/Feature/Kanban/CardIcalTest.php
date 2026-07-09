<?php

use App\Enums\Role;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

test('a board iCal feed serves the dated cards as a valid calendar', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'Ship the release', 'due_at' => Carbon::parse('2026-08-01 14:00:00')]);
    $board->enableIcalFeed();

    $response = $this->get($board->icalUrl());

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');
    $body = $response->getContent();
    $expectedStart = $card->fresh()->due_at->utc()->format('Ymd\THis\Z');
    expect($body)->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        ->toContain('SUMMARY:Ship the release')
        ->toContain('DTSTART:'.$expectedStart);
});

test('cards without a date are excluded from the feed', function () {
    ['board' => $board, 'card' => $card] = makeCardContext();
    $card->update(['title' => 'No date card', 'due_at' => null, 'start_at' => null]);
    $board->enableIcalFeed();

    $body = $this->get($board->icalUrl())->assertOk()->getContent();

    expect($body)->not->toContain('No date card')
        ->and(substr_count($body, 'BEGIN:VEVENT'))->toBe(0);
});

test('an unknown board token returns 404', function () {
    $this->get(route('boards.ical', ['token' => str_repeat('x', 40)]))->assertNotFound();
});

test('disabling a board feed invalidates the existing link', function () {
    ['board' => $board] = makeCardContext();
    $board->enableIcalFeed();
    $url = $board->icalUrl();

    $this->get($url)->assertOk();

    $board->disableIcalFeed();

    $this->get($url)->assertNotFound();
});

test('regenerating a board feed changes the token', function () {
    ['board' => $board] = makeCardContext();
    $board->enableIcalFeed();
    $oldUrl = $board->icalUrl();

    $board->regenerateIcalFeed();

    expect($board->icalUrl())->not->toBe($oldUrl);
    $this->get($oldUrl)->assertNotFound();
    $this->get($board->icalUrl())->assertOk();
});

test('the feature can be disabled instance-wide', function () {
    ['board' => $board] = makeCardContext();
    $board->enableIcalFeed();
    $url = $board->icalUrl();

    config(['board.ical_feeds' => false]);

    $this->get($url)->assertNotFound();
});

test('a user feed aggregates dated cards across accessible boards and respects RBAC', function () {
    ['board' => $board, 'owner' => $owner] = makeCardContext();
    Card::factory()->create([
        'board_list_id' => $board->lists()->first()->id,
        'board_id' => $board->id,
        'title' => 'Mine and dated',
        'due_at' => Carbon::parse('2026-09-10 09:00:00'),
    ]);

    // A board in another workspace the user is not part of — must never leak.
    $stranger = User::factory()->create();
    $otherWorkspace = Workspace::factory()->create(['owner_id' => $stranger->id]);
    $otherWorkspace->members()->attach($stranger, ['role' => Role::Owner->value]);
    $otherBoard = Board::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $otherBoard->members()->attach($stranger, ['role' => Role::Owner->value]);
    $otherList = BoardList::factory()->create(['board_id' => $otherBoard->id]);
    Card::factory()->create([
        'board_list_id' => $otherList->id,
        'board_id' => $otherBoard->id,
        'title' => 'Not for you',
        'due_at' => Carbon::parse('2026-09-11 09:00:00'),
    ]);

    $owner->enableIcalFeed();
    $body = $this->get($owner->icalUrl())->assertOk()->getContent();

    expect($body)->toContain('SUMMARY:Mine and dated')
        ->not->toContain('Not for you');
});

test('an unknown user token returns 404', function () {
    $this->get(route('calendar.ical', ['token' => str_repeat('y', 40)]))->assertNotFound();
});
