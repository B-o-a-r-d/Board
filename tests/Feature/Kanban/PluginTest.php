<?php

use App\Enums\Role;
use App\Livewire\Boards\ActivityLog;
use App\Livewire\Boards\ListColumn;
use App\Livewire\Boards\PluginList;
use App\Livewire\Boards\Show;
use App\Mcp\Servers\BoardServer;
use App\Models\Activity;
use App\Models\Board;
use App\Models\BoardList;
use App\Models\BoardPlugin;
use App\Models\Card;
use App\Models\User;
use App\Models\Workspace;
use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\PluginListItem;
use Board\PluginSdk\PluginRegistry;
use Board\PluginSdk\Support\PluginSettings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Fixtures\Connector\AcmeItemsTool;

/**
 * The plugin *system* is exercised through the neutral "Acme" connector fixture
 * (see tests/Fixtures/Connector) — a capability-complete Power-Up that stands in
 * for any real plugin package. The core app no longer bundles a concrete plugin.
 *
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

test('classic list cards render in their own list-column component', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $list = BoardList::factory()->create(['board_id' => $board->id]);
    Card::factory()->create(['board_id' => $board->id, 'board_list_id' => $list->id, 'title' => 'Ma carte']);

    // Cards live in a per-list ListColumn (extracted from Show so a card action
    // re-renders only its column). A plugin-sourced list keeps its own child.
    Livewire::actingAs($owner)->test(ListColumn::class, ['board' => $board, 'list' => $list])
        ->assertSee('Ma carte');
});

test('the connector plugin auto-registers into the registry via its provider', function () {
    $plugin = app(PluginRegistry::class)->get('acme');

    expect($plugin)->not->toBeNull()
        ->and($plugin->label())->toBe('Acme')
        ->and($plugin->requiresOAuth())->toBeTrue()
        ->and($plugin)->toBeInstanceOf(ProvidesListSource::class)
        ->and($plugin)->toBeInstanceOf(DefinesActivities::class)
        ->and($plugin)->toBeInstanceOf(ProvidesMcpTools::class)
        ->and($plugin)->toBeInstanceOf(ProvidesOAuth::class);
});

test('activity describe() delegates to the plugin for its own types', function () {
    $activity = new Activity([
        'type' => 'acme.ref_linked',
        'source' => 'plugin:acme',
        'properties' => ['ref_type' => 'item', 'title' => 'Fix the bug'],
    ]);

    expect($activity->describe())->toBe('linked the item "Fix the bug"')
        ->and($activity->pluginKey())->toBe('acme');
});

test('the slide-over shows a plugin tab only when that plugin has activity', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    // No acme activity yet → no acme tab.
    Livewire::actingAs($owner)->test(ActivityLog::class, ['board' => $board])
        ->set('open', true)
        ->assertViewHas('activityTabs', fn ($tabs) => collect($tabs)->doesntContain(fn ($t) => $t['plugin_key'] === 'acme'));

    Activity::create([
        'board_id' => $board->id, 'type' => 'acme.ref_linked', 'source' => 'plugin:acme',
        'properties' => ['ref_type' => 'item', 'title' => 'x'],
    ]);

    Livewire::actingAs($owner)->test(ActivityLog::class, ['board' => $board])
        ->set('open', true)
        ->assertViewHas('activityTabs', fn ($tabs) => collect($tabs)->contains(fn ($t) => $t['plugin_key'] === 'acme'));
});

test('the plugin mcp tool lists items through the board server', function () {
    Http::fake([
        'api.acme.test/items*' => Http::response([
            ['id' => 'A1', 'title' => 'Hello from MCP', 'author' => 'Dev', 'url' => 'https://acme.test/items/A1'],
        ]),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    BoardServer::actingAs($owner)->tool(AcmeItemsTool::class, [
        'board_id' => $board->public_id,
        'resource' => 'team/project',
    ])->assertOk()->assertSee('Hello from MCP');
});

test('an installed plugin config is encrypted at rest', function () {
    ['board' => $board] = makePluginBoard();

    $plugin = BoardPlugin::create([
        'board_id' => $board->id,
        'plugin_key' => 'acme',
        'name' => 'Acme',
        'config' => ['token' => 'secret_token_value'],
        'is_active' => true,
    ]);

    $raw = DB::table('board_plugins')->where('id', $plugin->id)->value('config');

    expect($raw)->not->toContain('secret_token_value')
        ->and($plugin->fresh()->config['token'])->toBe('secret_token_value')
        ->and($plugin->isConnected())->toBeTrue();
});

test('a board admin can install a plugin but a plain member cannot', function () {
    ['board' => $board, 'owner' => $owner, 'member' => $member] = makePluginBoard();

    Livewire::actingAs($member)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'acme')
        ->assertForbidden();

    expect($board->plugins()->count())->toBe(0);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('installPlugin', 'acme')
        ->assertHasNoErrors();

    expect($board->plugins()->where('plugin_key', 'acme')->count())->toBe(1);
});

test('installing the same plugin twice is a no-op', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();

    $component = Livewire::actingAs($owner)->test(Show::class, ['board' => $board]);
    $component->call('installPlugin', 'acme');
    $component->call('installPlugin', 'acme');

    expect($board->plugins()->where('plugin_key', 'acme')->count())->toBe(1);
});

test('creating a plugin list stores the source binding', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginList', $instance->id)
        ->set('newPluginListName', 'Items')
        ->set('newPluginListMode', 'items')
        ->set('newPluginListConfig.resource', 'team/project')
        ->call('createPluginList')
        ->assertHasNoErrors();

    $list = $board->lists()->whereNotNull('source_plugin_id')->firstOrFail();

    expect($list->name)->toBe('Items')
        ->and($list->source_plugin_id)->toBe($instance->id)
        ->and($list->source_mode)->toBe('items')
        ->and($list->source_config)->toBe(['resource' => 'team/project'])
        ->and($list->isPluginList())->toBeTrue();
});

test('an invalid source mode is rejected', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginList', $instance->id)
        ->set('newPluginListName', 'Bad')
        ->set('newPluginListMode', 'nonsense')
        ->call('createPluginList')
        ->assertHasErrors('newPluginListMode');

    expect($board->lists()->whereNotNull('source_plugin_id')->count())->toBe(0);
});

test('the lazy plugin list renders read-only items with the item time', function () {
    config(['app.timezone' => 'Europe/Paris']); // timestamps are shown in the app timezone

    Http::fake([
        'api.acme.test/items*' => Http::response([
            [
                'id' => 'A1',
                'title' => 'Fix the widget',
                'author' => 'Octo Cat',
                'url' => 'https://acme.test/items/A1',
                'created_at' => '2026-07-07T10:00:00Z',
            ],
        ]),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create([
        'board_id' => $board->id,
        'name' => 'Items',
        'source_plugin_id' => $instance->id,
        'source_mode' => 'items',
        'source_config' => ['resource' => 'team/project'],
    ]);

    Livewire::withoutLazyLoading()
        ->actingAs($owner)
        ->test(PluginList::class, ['list' => $list])
        ->assertSee('Fix the widget')
        ->assertSee('Octo Cat')
        ->assertSee('12:00'); // 10:00 UTC shown in Europe/Paris
});

test('the lazy plugin list shows a skeleton placeholder before loading', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create([
        'board_id' => $board->id,
        'source_plugin_id' => $instance->id,
        'source_mode' => 'items',
        'source_config' => ['resource' => 'team/project'],
    ]);

    // With lazy loading enabled, the component renders its placeholder first.
    Livewire::actingAs($owner)->test(PluginList::class, ['list' => $list])
        ->assertSee('animate-pulse', false);
});

test('refreshing a plugin list busts the cache and broadcasts', function () {
    Http::fake(['api.acme.test/items*' => Http::response([])]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create([
        'board_id' => $board->id,
        'source_plugin_id' => $instance->id,
        'source_mode' => 'items',
        'source_config' => ['resource' => 'team/project'],
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

test('a plugin list lazy-loads more items on demand', function () {
    $all = collect(range(1, 40))->map(fn (int $n): array => [
        'id' => 'A'.str_pad((string) $n, 4, '0', STR_PAD_LEFT),
        'title' => "Item number {$n}",
        'author' => 'Dev',
        'url' => "https://acme.test/items/{$n}",
        'created_at' => '2026-07-07T10:00:00Z',
    ])->all();

    Http::fake([
        'api.acme.test/items*' => function ($request) use ($all) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $q);
            $perPage = (int) ($q['per_page'] ?? 15);

            return Http::response(array_slice($all, 0, $perPage));
        },
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => ['token' => 't'], 'is_active' => true,
    ]);
    $list = BoardList::factory()->create([
        'board_id' => $board->id,
        'source_plugin_id' => $instance->id,
        'source_mode' => 'items',
        'source_config' => ['resource' => 'team/project'],
    ]);

    Livewire::withoutLazyLoading()
        ->actingAs($owner)
        ->test(PluginList::class, ['list' => $list])
        ->assertSee('Item number 15')
        ->assertDontSee('Item number 16')
        ->call('loadMore')
        ->assertSet('limit', 30)
        ->assertSee('Item number 16')
        ->assertSee('Item number 30');
});

test('the oauth callback stores an encrypted token', function () {
    Http::fake([
        'acme.test/oauth/token' => Http::response(['access_token' => 'acme_from_oauth']),
        'api.acme.test/user' => Http::response(['login' => 'octocat']),
    ]);

    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->withSession(['plugin_oauth' => ['plugin_id' => $instance->id, 'state' => 'st4te']])
        ->get(route('plugins.oauth.callback', ['state' => 'st4te', 'code' => 'the-code']))
        ->assertRedirect(route('boards.show', $board));

    $fresh = $instance->fresh();
    $raw = DB::table('board_plugins')->where('id', $instance->id)->value('config');

    expect($fresh->config['token'])->toBe('acme_from_oauth')
        ->and($fresh->config['account'])->toBe('octocat')
        ->and($raw)->not->toContain('acme_from_oauth');
});

test('oauth credentials are configured from the modal and stored encrypted', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginConfig', $instance->id)
        ->set('pluginConfigDraft.client_id', 'acme-abcdef')
        ->set('pluginConfigDraft.client_secret', 'super-secret-value')
        ->call('savePluginConfig')
        ->assertHasNoErrors();

    $fresh = $instance->fresh();
    $raw = DB::table('board_plugins')->where('id', $instance->id)->value('config');

    expect($fresh->config['client_id'])->toBe('acme-abcdef')
        ->and($fresh->config['client_secret'])->toBe('super-secret-value')
        ->and($raw)->not->toContain('super-secret-value');
});

test('a blank secret keeps the previously stored one on re-save', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme',
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
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    // No client_id configured and no provider config fallback → redirected back.
    $this->actingAs($owner)
        ->get(route('plugins.oauth.redirect', $instance))
        ->assertRedirect(route('boards.show', $board));

    expect(session('plugin_oauth'))->toBeNull();
});

test('savePluginConfig rejects an SSRF-unsafe instance url (metadata/localhost)', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginConfig', $instance->id)
        ->set('pluginConfigDraft.instance_url', 'http://169.254.169.254')
        ->call('savePluginConfig')
        ->assertHasErrors('pluginConfigDraft.instance_url');

    expect($instance->fresh()->config['instance_url'] ?? null)->toBeNull();
});

test('savePluginConfig accepts a public instance url', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginConfig', $instance->id)
        ->set('pluginConfigDraft.instance_url', 'https://93.184.216.34')
        ->call('savePluginConfig')
        ->assertHasNoErrors();

    expect($instance->fresh()->config['instance_url'])->toBe('https://93.184.216.34');
});

test('savePluginConfig permits an internal instance url on the plugin allow-list', function () {
    PluginSettings::for('acme')->put(['allowed_hosts' => '10.0.0.5']);
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    Livewire::actingAs($owner)->test(Show::class, ['board' => $board])
        ->call('startPluginConfig', $instance->id)
        ->set('pluginConfigDraft.instance_url', 'http://10.0.0.5')
        ->call('savePluginConfig')
        ->assertHasNoErrors();

    expect($instance->fresh()->config['instance_url'])->toBe('http://10.0.0.5');
});

test('the oauth redirect targets the per-board instance url from config', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    // An admin has configured a custom (self-hosted) instance URL on the instance.
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme',
        'config' => ['client_id' => 'cid', 'instance_url' => 'https://acme.custom.test'],
        'is_active' => true,
    ]);

    $response = $this->actingAs($owner)->get(route('plugins.oauth.redirect', $instance));

    expect($response->headers->get('Location'))->toStartWith('https://acme.custom.test/oauth/authorize');
});

test('the oauth redirect degrades gracefully when the plugin does not drive oauth', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    // A plugin_key with no ProvidesOAuth plugin in the registry (e.g. an outdated
    // build) must redirect back with a notice — never a bare 404.
    $instance = $board->plugins()->create([
        'plugin_key' => 'ghost', 'name' => 'Ghost', 'config' => [], 'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->get(route('plugins.oauth.redirect', $instance))
        ->assertRedirect(route('boards.show', $board));

    expect(session('plugin_oauth'))->toBeNull();
});

test('the oauth callback rejects a mismatched state', function () {
    ['board' => $board, 'owner' => $owner] = makePluginBoard();
    $instance = $board->plugins()->create([
        'plugin_key' => 'acme', 'name' => 'Acme', 'config' => [], 'is_active' => true,
    ]);

    $this->actingAs($owner)
        ->withSession(['plugin_oauth' => ['plugin_id' => $instance->id, 'state' => 'expected']])
        ->get(route('plugins.oauth.callback', ['state' => 'forged', 'code' => 'x']))
        ->assertForbidden();
});
