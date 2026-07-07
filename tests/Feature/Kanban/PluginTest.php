<?php

use App\Enums\Role;
use App\Livewire\Boards\PluginList;
use App\Livewire\Boards\Show;
use App\Livewire\Cards\CardDetail;
use App\Mcp\Servers\BoardServer;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\BoardPlugin;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Board\PluginGithub\Mcp\GithubCommitsTool;
use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\EnrichesCards;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
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

test('classic list cards are lazy-loaded behind an animated skeleton', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'Ma carte lazy']);

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);

    // Force the pre-load state the browser sees before wire:init fires.
    $component->set('cardsReady', false)
        ->assertSee('animate-pulse', false)
        ->assertDontSee('Ma carte lazy');

    $component->call('loadCards')
        ->assertSet('cardsReady', true)
        ->assertSee('Ma carte lazy');
});

test('the github plugin auto-registers into the registry via its package', function () {
    $registry = app(PluginRegistry::class);
    $plugin = $registry->get('github');

    expect($plugin)->not->toBeNull()
        ->and($plugin->label())->toBe('GitHub')
        ->and($plugin->requiresOAuth())->toBeTrue()
        ->and($plugin)->toBeInstanceOf(ProvidesListSource::class)
        ->and($plugin)->toBeInstanceOf(DefinesActivities::class)
        ->and($plugin)->toBeInstanceOf(EnrichesCards::class)
        ->and($plugin)->toBeInstanceOf(ProvidesMcpTools::class);
});

test('the github plugin ships its own file translations', function () {
    $plugin = app(PluginRegistry::class)->get('github');

    app()->setLocale('en');
    expect($plugin->description())->toBe('Read-only lists of a GitHub repository\'s commits, pull requests and issues.')
        ->and(trans('github::messages.mode.commits'))->toBe('Recent commits');

    app()->setLocale('fr');
    expect(trans('github::messages.mode.commits'))->toBe('Derniers commits');
});

test('activity describe() delegates to the plugin for its own types', function () {
    app()->setLocale('en');

    $activity = new Activity([
        'type' => 'github.ref_linked',
        'source' => 'plugin:github',
        'properties' => ['ref_type' => 'commit', 'title' => 'Fix the bug'],
    ]);

    expect($activity->describe())->toBe('linked the commit "Fix the bug"')
        ->and($activity->pluginKey())->toBe('github');
});

test('linking a github commit to a card stores a ref and logs a plugin activity', function () {
    Http::fake([
        'api.github.com/repos/*/commits/*' => Http::response([
            'sha' => 'abc1234567890',
            'html_url' => 'https://github.com/o/r/commit/abc1234',
            'commit' => ['message' => "Fix the bug\n\nbody", 'author' => ['name' => 'Octo', 'date' => '2026-07-07T10:00:00Z']],
        ]),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('addPluginRef', $instance->id, 'commit', 'https://github.com/o/r/commit/abc1234567890')
        ->assertHasNoErrors();

    $ref = $card->pluginRefs()->firstOrFail();
    expect($ref->plugin_key)->toBe('github')
        ->and($ref->ref_type)->toBe('commit')
        ->and($ref->ref_id)->toBe('abc1234567890')
        ->and($ref->payload['title'])->toBe('Fix the bug');

    $activity = Activity::where('type', 'github.ref_linked')->latest('id')->firstOrFail();
    expect($activity->source)->toBe('plugin:github')
        ->and($activity->card_id)->toBe($card->id);
});

test('an unresolvable ref shows an error and stores nothing', function () {
    Http::fake(['api.github.com/*' => Http::response([], 404)]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    $card = Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id]);

    Livewire::actingAs($owner)->test(CardDetail::class, ['board' => $board])
        ->call('openCard', $card->id)
        ->call('addPluginRef', $instance->id, 'commit', 'garbage-input');

    expect($card->pluginRefs()->count())->toBe(0);
});

test('the slide-over shows a plugin tab only when that plugin has activity', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    // No github activity yet → no github tab.
    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('showActivity', true)
        ->assertViewHas('activityTabs', fn ($tabs) => collect($tabs)->doesntContain(fn ($t) => $t['plugin_key'] === 'github'));

    Activity::create([
        'board_id' => $board->id, 'type' => 'github.ref_linked', 'source' => 'plugin:github',
        'properties' => ['ref_type' => 'commit', 'title' => 'x'],
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->set('showActivity', true)
        ->assertViewHas('activityTabs', fn ($tabs) => collect($tabs)->contains(fn ($t) => $t['plugin_key'] === 'github'));
});

test('the plugin mcp tool lists commits through the board server', function () {
    Http::fake([
        'api.github.com/repos/*/commits*' => Http::response([
            ['sha' => 'deadbeef', 'html_url' => 'https://github.com/o/r/commit/deadbeef',
                'commit' => ['message' => 'Hello from MCP', 'author' => ['name' => 'Dev', 'date' => '2026-07-07T10:00:00Z']]],
        ]),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $board->plugins()->create([
        'plugin_key' => 'github', 'name' => 'GitHub', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    BoardServer::actingAs($owner)->tool(GithubCommitsTool::class, [
        'board_id' => $board->public_id,
        'repository' => 'o/r',
    ])->assertOk()->assertSee('Hello from MCP');
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

test('the lazy plugin list renders read-only items with the commit time', function () {
    config(['app.timezone' => 'Europe/Paris']); // commit time is shown in the app timezone

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
    $list = BoardList::factory()->create([
        'board_id' => $board->id,
        'name' => 'Commits',
        'source_plugin_id' => $instance->id,
        'source_mode' => 'commits',
        'source_config' => ['repository' => 'o/r'],
    ]);

    Livewire::withoutLazyLoading()
        ->actingAs($owner)
        ->test(PluginList::class, ['list' => $list])
        ->assertSee('Fix the widget')
        ->assertSee('Octo Cat · abc1234')
        ->assertSee('12:00'); // 10:00 UTC shown in Europe/Paris
});

test('the lazy plugin list shows a skeleton placeholder before loading', function () {
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

    // With lazy loading enabled, the component renders its placeholder first.
    Livewire::actingAs($owner)->test(PluginList::class, ['list' => $list])
        ->assertSee('animate-pulse', false);
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
        "plugin-list:{$list->id}:15",
        [(new PluginListItem(externalRef: 'x', title: 'STALE'))->toArray()],
        now()->addMinutes(5),
    );

    Livewire::withoutLazyLoading()
        ->actingAs($owner)
        ->test(PluginList::class, ['list' => $list])
        ->call('refresh')
        ->assertHasNoErrors();

    // The stale sentinel is gone (cache was busted and re-fetched as empty).
    expect(Cache::get("plugin-list:{$list->id}:15"))->toBe([]);
});

test('a plugin list lazy-loads more commits on demand', function () {
    $all = collect(range(1, 40))->map(fn (int $n): array => [
        'sha' => str_pad((string) $n, 7, '0', STR_PAD_LEFT).'abcdef',
        'html_url' => "https://github.com/o/r/commit/{$n}",
        'commit' => ['message' => "Commit number {$n}", 'author' => ['name' => 'Dev', 'date' => '2026-07-07T10:00:00Z']],
    ])->all();

    Http::fake([
        'api.github.com/repos/*/commits*' => function ($request) use ($all) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $perPage = (int) ($q['per_page'] ?? 30);
            $page = (int) ($q['page'] ?? 1);

            return Http::response(array_slice($all, ($page - 1) * $perPage, $perPage));
        },
    ]);

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

    Livewire::withoutLazyLoading()
        ->actingAs($owner)
        ->test(PluginList::class, ['list' => $list])
        ->assertSee('Commit number 15')
        ->assertDontSee('Commit number 16')
        ->call('loadMore')
        ->assertSet('limit', 30)
        ->assertSee('Commit number 16')
        ->assertSee('Commit number 30');
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
