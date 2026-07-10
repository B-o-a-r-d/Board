<?php

use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Models\Activity;
use App\Models\Board;
use App\Models\User;
use Illuminate\Support\Facades\DB;

test('activities:prune deletes entries older than the board retention', function () {
    $board = Board::factory()->create(['activity_retention_days' => 30]);

    $old = Activity::factory()->create(['board_id' => $board->id]);
    $recent = Activity::factory()->create(['board_id' => $board->id]);
    DB::table('activities')->where('id', $old->id)->update(['created_at' => now()->subDays(40)]);
    DB::table('activities')->where('id', $recent->id)->update(['created_at' => now()->subDays(10)]);

    $this->artisan('activities:prune')->assertSuccessful();

    expect(Activity::whereKey($old->id)->exists())->toBeFalse()
        ->and(Activity::whereKey($recent->id)->exists())->toBeTrue();
});

test('activities:prune keeps everything for a board without retention', function () {
    $board = Board::factory()->create(['activity_retention_days' => null]);
    $old = Activity::factory()->create(['board_id' => $board->id]);
    DB::table('activities')->where('id', $old->id)->update(['created_at' => now()->subYears(2)]);

    $this->artisan('activities:prune')->assertSuccessful();

    expect(Activity::whereKey($old->id)->exists())->toBeTrue();
});

test('the activity panel shows the retention footer to board admins only', function () {
    $board = Board::factory()->create();
    $owner = User::factory()->create();
    $board->workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);

    $member = User::factory()->create();
    $board->workspace->members()->attach($member, ['role' => Role::Member->value]);
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire\Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('toggleActivity')
        ->assertSee(__('Purge automatique des activités anciennes'));

    Livewire\Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->call('toggleActivity')
        ->assertDontSee(__('Purge automatique des activités anciennes'));
});

test('only a board admin can change the activity retention', function () {
    $board = Board::factory()->create();
    $member = User::factory()->create();
    $board->workspace->members()->attach($member, ['role' => Role::Member->value]);
    $board->members()->attach($member, ['role' => Role::Member->value]);

    Livewire\Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->call('saveActivityRetention', '30')
        ->assertForbidden();

    expect($board->fresh()->activity_retention_days)->toBeNull();
});
