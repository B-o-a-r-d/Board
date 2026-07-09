<?php

use App\Models\Board;
use App\Models\Card;
use App\Models\Workspace;
use Database\Seeders\DatabaseSeeder;

/**
 * Regression: the seeder must NOT disable model events (WithoutModelEvents), or
 * HasPublicId (creating) and Workspace role seeding (created) never fire, leaving
 * null public_ids — which breaks every {…:public_id} route.
 */
test('the seeder produces records with public ids and workspace roles', function () {
    $this->seed(DatabaseSeeder::class);

    $workspace = Workspace::first();

    expect($workspace->public_id)->not->toBeNull()
        ->and(Workspace::whereNull('public_id')->count())->toBe(0)
        ->and(Board::whereNull('public_id')->count())->toBe(0)
        ->and(Card::whereNull('public_id')->count())->toBe(0)
        ->and($workspace->roles()->pluck('key')->sort()->values()->all())->toBe(['admin', 'member', 'observer', 'owner']);

    // The public_id must actually resolve a route.
    expect(route('workspaces.calendar', $workspace))->toContain($workspace->public_id);
});
