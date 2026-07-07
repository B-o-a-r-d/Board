<?php

use App\Enums\Role;
use App\Livewire\Boards\Show;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\BoardPlugin;
use App\Models\User;
use App\Models\Workspace;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\PluginListItem;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

/**
 * @return array{board: Board, owner: User, member: User}
 */
function makePluginBoard(): array
{
    $owner = User::factory()->create();
    $member = User::factory()->create();

    $workspace = Workspace::factory()->create(['owner_id' => $owner->id]);
    $workspace->members()->attach($owner, ['role' => Role::Owner->value]);
    $workspace->members()->attach($member, ['role' => Role::Member->value]);

    $board = Board::factory()->create(['workspace_id' => $workspace->id]);
    $board->members()->attach($owner, ['role' => Role::Owner->value]);
    $board->members()->attach($member, ['role' => Role::Member->value]);

    return compact('board', 'owner', 'member');
}

test('the github plugin auto-registers into the registry via its package', function () {
    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('github');

    expect($plugin)->not->toBeNull()
        ->and($plugin->label())->toBe('GitHub')
        ->and($plugin->requiresOAuth())->toBeTrue()
        ->and($plugin)->toBeInstanceOf(ProvidesListSource::class);
});

test('an installed plugin config is encrypted at rest', function () {
    ['board' => $board] = makePluginBoard();

    $plugin = BoardPlugin::create([
        'board_id' => $board->id,
        'plugin_key' => 'github',
        'name' => 'GitHub',
        'config' => ['token' => 'gho_secret_value'],
        'is_active' => true,
    ]);

    $raw = DB::table('board_plugins')->where('id', $plugin->id)->value('config');

    expect($raw)->not->toContain('gho_secret_value')
        ->and($plugin->fresh()->config['token'])->toBe('gho_secret_value')
        ->and($plugin->isConnected())->toBeTrue();
});

test('a board admin can install a plugin but a plain member cannot', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makePluginBoard();

    Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'github')
        ->assertForbidden();

    expect($board->plugins()->count())->toBe(0);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'github')
        ->assertHasNoErrors();

    expect($board->plugins()->where('plugin_key', 'github')->count())->toBe(1);
});

test('installing the same plugin twice is a no-op', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);
    $component->call('installPlugin', 'github');
    $component->call('installPlugin', 'github');

    expect($board->plugins()->where('plugin_key', 'github')->count())->toBe(1);
});

test('creating a plugin list stores the source binding', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginList', $instance->id)
        ->set('newPluginListName', 'Commits')
        ->set('newPluginListMode', 'commits')
        ->set('newPluginListConfig.repository', 'laravel/framework')
        ->call('createPluginList')
        ->assertHasNoErrors();

    $list = $board->lists()->whereNotNull('source_plugin_id')->firstOrFail();

    expect($list->name)->toBe('Commits')
        ->and($list->source_plugin_id)->toBe($instance->id)
        ->and($list->source_mode)->toBe('commits')
        ->and($list->source_config)->toBe(['repository' => 'laravel/framework'])
        ->and($list->isPluginList())->toBeTrue();
});

test('an invalid source mode is rejected', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginList', $instance->id)
        ->set('newPluginListName', 'Bad')
        ->set('newPluginListMode', 'nonsense')
        ->call('createPluginList')
        ->assertHasErrors('newPluginListMode');

    expect($board->lists()->whereNotNull('source_plugin_id')->count())->toBe(0);
});

test('a plugin list renders read-only items fetched from github', function () {
    Http::fake([
        'api.github.com/repos/*/commits*' => Http::response([
            [
                'sha' => 'abc1234567890',
                'html_url' => 'https://github.com/o/r/commit/abc1234',
                'commit' => ['message' => "Fix the widget\n\nlong body", 'author' => ['name' => 'Octo Cat', 'date' => '2026-07-07T10:00:00Z']],
            ],
        ]),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    BoardList::factory()->create([
        'board_id' => $board->id,
        'name' => 'Commits',
        'source_plugin_id' => $instance->id,
        'source_mode' => 'commits',
        'source_config' => ['repository' => 'o/r'],
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->assertSee('Fix the widget')
        ->assertSee('Octo Cat · abc1234');
});

test('refreshing a plugin list busts the cache and broadcasts', function () {
    Http::fake(['api.github.com/repos/*/commits*' => Http::response([])]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create([
        'board_id' => $board->id,
        'source_plugin_id' => $instance->id,
        'source_mode' => 'commits',
        'source_config' => ['repository' => 'o/r'],
    ]);

    Cache::put(
        "plugin-list:{$list->id}",
        [(new PluginListItem(externalRef: 'x', title: 'STALE'))->toArray()],
        now()->addMinutes(5),
    );

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->assertSee('STALE')
        ->call('refreshPluginList', $list->id)
        ->assertHasNoErrors();

    // The stale sentinel is gone (cache was busted and re-fetched as empty).
    expect(Cache::get("plugin-list:{$list->id}"))->toBe([]);
});

test('the github oauth callback stores an encrypted token', function () {
    Config::set('services.github.client_id', 'cid');
    Config::set('services.github.client_secret', 'csecret');

    Http::fake([
        'github.com/login/oauth/access_token' => Http::response(['access_token' => 'gho_from_oauth']),
        'api.github.com/user' => Http::response(['login' => 'octocat']),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => [], 'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->withSession(['plugin_oauth' => ['plugin_id' => $instance->id, 'state' => 'st4te']])
        ->get(route('plugins.oauth.github.callback', ['state' => 'st4te', 'code' => 'the-code']))
        ->assertRedirect(route('boards.show', $board));

    $fresh = $instance->fresh();
    $raw = DB::table('board_plugins')->where('id', $instance->id)->value('config');

    expect($fresh->config['token'])->toBe('gho_from_oauth')
        ->and($fresh->config['account'])->toBe('octocat')
        ->and($raw)->not->toContain('gho_from_oauth');
});

test('oauth credentials are configured from the modal and stored encrypted', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => [], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginConfig', $instance->id)
        ->set('pluginConfigDraft.client_id', 'Iv1.abcdef')
        ->set('pluginConfigDraft.client_secret', 'super-secret-value')
        ->call('savePluginConfig')
        ->assertHasNoErrors();

    $fresh = $instance->fresh();
    $raw = DB::table('board_plugins')->where('id', $instance->id)->value('config');

    expect($fresh->config['client_id'])->toBe('Iv1.abcdef')
        ->and($fresh->config['client_secret'])->toBe('super-secret-value')
        ->and($raw)->not->toContain('super-secret-value');
});

test('a blank secret keeps the previously stored one on re-save', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub',
        'config' => ['client_id' => 'old-id', 'client_secret' => 'keep-me'],
        'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginConfig', $instance->id)
        ->set('pluginConfigDraft.client_id', 'new-id')
        ->set('pluginConfigDraft.client_secret', '')
        ->call('savePluginConfig');

    $fresh = $instance->fresh();

    expect($fresh->config['client_id'])->toBe('new-id')
        ->and($fresh->config['client_secret'])->toBe('keep-me');
});

test('connecting is blocked until oauth credentials are configured', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => [], 'is_active' => true,
    ]);

    // No client_id configured and no env fallback → redirected back with a notice.
    config()->set('services.github.client_id', null);

    $this->actingAs($owner)
        ->get(route('plugins.oauth.github.redirect', $instance))
        ->assertRedirect(route('boards.show', $board));

    expect(session('plugin_oauth'))->toBeNull();
});

test('the oauth callback rejects a mismatched state', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => [], 'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->withSession(['plugin_oauth' => ['plugin_id' => $instance->id, 'state' => 'expected']])
        ->get(route('plugins.oauth.github.callback', ['state' => 'forged', 'code' => 'x']))
        ->assertForbidden();
});
