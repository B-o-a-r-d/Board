<?php

use App\Models\User;
use Board\Marketplace\Livewire\Marketplace;
use Board\Marketplace\MarketplaceClient;
use Board\Marketplace\Models\PluginPackage;
use Board\Marketplace\Models\PluginRepository;
use Board\Marketplace\PluginInstaller;
use Board\Marketplace\PluginInstallException;
use Board\Marketplace\Support\ComposerRunner;
use Board\Marketplace\Support\PackagistStats;
use Board\Marketplace\Support\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

/**
 * Records the composer commands the installer issues and simulates their
 * filesystem effects inside the plugins project — no real composer binary.
 */
class FakeComposerRunner extends ComposerRunner
{
    /** @var array<int, array<int, string>> */
    public array $commands = [];

    /** @var array<string, mixed>|null the composer.json `require` writes for the package */
    public ?array $installedManifest = null;

    public string $installedVersion = 'v1.2.3';

    public string $outdatedLatest = '';

    public function run(array $arguments, string $workingDirectory): string
    {
        $this->commands[] = $arguments;

        $verb = $arguments[0] ?? '';

        if ($verb === 'require') {
            $name = explode(':', (string) $arguments[1])[0];
            File::ensureDirectoryExists($workingDirectory."/vendor/{$name}");
            File::put($workingDirectory."/vendor/{$name}/composer.json", json_encode($this->installedManifest ?? []));
            File::ensureDirectoryExists($workingDirectory.'/vendor/composer');
            File::put($workingDirectory.'/vendor/composer/installed.json', json_encode([
                'packages' => [['name' => $name, 'version' => $this->installedVersion]],
            ]));
        }

        if ($verb === 'remove') {
            File::deleteDirectory($workingDirectory.'/vendor/'.(string) $arguments[1]);
        }

        if ($verb === 'outdated') {
            return json_encode(['installed' => $this->outdatedLatest === '' ? [] : [
                ['name' => 'board/plugin-demo', 'latest' => $this->outdatedLatest],
            ]]);
        }

        return '';
    }
}

function fakeComposer(): FakeComposerRunner
{
    $fake = new FakeComposerRunner;
    $fake->installedManifest = [
        'name' => 'board/plugin-demo',
        'require' => ['board/plugin-sdk' => '^0.2'],
        'extra' => ['board' => ['sdk_contract' => 1], 'laravel' => ['providers' => ['Board\\PluginDemo\\DemoServiceProvider']]],
    ];
    app()->instance(ComposerRunner::class, $fake);

    return $fake;
}

/**
 * @return array{key: string, name: string, repo: string, package: string}
 */
function composerEntry(): array
{
    return ['key' => 'demo', 'name' => 'Demo', 'repo' => 'acme/demo', 'package' => 'board/plugin-demo'];
}

afterEach(function () {
    // The composer flow is new: nothing real lives in the plugins project yet,
    // so the whole managed manifest + vendor are test artifacts.
    File::delete(storage_path('app/plugins/composer.json'));
    File::delete(storage_path('app/plugins/composer.lock'));
    File::deleteDirectory(storage_path('app/plugins/vendor'));
    File::deleteDirectory(storage_path('app/plugins/.composer'));
});

function fakeCatalogWithPackage(string $packageName): void
{
    $markdown = <<<MD
    ---
    key: demo
    name: Demo
    repo: acme/demo
    package: {$packageName}
    ---
    Body.
    MD;

    Http::fake([
        'api.github.com/repos/B-o-a-r-d/Marketplace/contents/plugins' => Http::response([
            ['type' => 'file', 'name' => 'demo.md', 'download_url' => 'https://raw.githubusercontent.com/B-o-a-r-d/Marketplace/main/plugins/demo.md'],
        ]),
        'raw.githubusercontent.com/B-o-a-r-d/Marketplace/*' => Http::response($markdown),
    ]);
}

test('the catalog parses an optional composer package name', function () {
    fakeCatalogWithPackage('board/plugin-demo');

    expect(app(MarketplaceClient::class)->catalog()->first()['package'])->toBe('board/plugin-demo');
});

test('the catalog drops a malformed composer package name but keeps the entry', function () {
    fakeCatalogWithPackage('../Evil/../pkg');

    $entry = app(MarketplaceClient::class)->catalog()->first();

    expect($entry['key'])->toBe('demo')
        ->and($entry['package'])->toBe('');
});

test('a catalog entry with a package name installs through the plugins composer project', function () {
    $fake = fakeComposer();

    $package = app(PluginInstaller::class)->install(composerEntry());

    expect($package->source)->toBe('composer')
        ->and($package->package_name)->toBe('board/plugin-demo')
        ->and($package->version)->toBe('1.2.3')
        ->and($package->contract_version)->toBe(1)
        ->and($package->path)->toBe('plugins/vendor/board/plugin-demo')
        ->and($package->enabled)->toBeTrue()
        ->and($fake->commands[0][0] ?? null)->toBe('require')
        ->and($fake->commands[0][1] ?? null)->toBe('board/plugin-demo');

    // The managed manifest pins the host's packages (SDK included) via `replace`
    // and forbids composer plugins — the install step stays inert.
    $manifest = json_decode((string) file_get_contents(storage_path('app/plugins/composer.json')), true);

    expect($manifest['replace'])->toHaveKey('board/plugin-sdk')
        ->and($manifest['config']['allow-plugins'])->toBeFalse();
});

test('a composer plugin with an unsupported SDK contract is rolled back', function () {
    $fake = fakeComposer();
    $fake->installedManifest['extra']['board']['sdk_contract'] = 99;

    expect(fn () => app(PluginInstaller::class)->install(composerEntry()))
        ->toThrow(PluginInstallException::class);

    // The failed install removed the package again and recorded nothing.
    expect(PluginPackage::where('key', 'demo')->exists())->toBeFalse()
        ->and(collect($fake->commands)->pluck(0)->all())->toBe(['require', 'remove']);
});

test('uninstalling a composer plugin removes it from the plugins project', function () {
    $fake = fakeComposer();
    app(PluginInstaller::class)->install(composerEntry());

    app(PluginInstaller::class)->uninstall('demo');

    expect(PluginPackage::where('key', 'demo')->exists())->toBeFalse()
        ->and(collect($fake->commands)->last()[0])->toBe('remove')
        ->and(is_dir(storage_path('app/plugins/vendor/board/plugin-demo')))->toBeFalse();
});

test('checkUpdates resolves composer plugins through composer outdated', function () {
    $fake = fakeComposer();
    app(PluginInstaller::class)->install(composerEntry());

    $fake->outdatedLatest = 'v2.0.0';
    app(PluginInstaller::class)->checkUpdates();

    $package = PluginPackage::where('key', 'demo')->firstOrFail();

    expect($package->available_version)->toBe('2.0.0')
        ->and($package->breaking_update)->toBeTrue();
});

// --- Phase 2 : sources custom + install depuis une source ---------------------

function composerAdmin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

test('an admin manages custom composer repositories from the source modal', function () {
    Settings::setEnabled(true);

    $component = Livewire\Livewire::actingAs(composerAdmin())
        ->test(Marketplace::class)
        ->call('openSource')
        ->set('newRepoType', 'vcs')
        ->set('newRepoUrl', 'http://insecure.test/repo.git')
        ->call('addRepository')
        ->assertHasErrors('newRepoUrl');

    $component->set('newRepoUrl', 'https://git.example.com/acme/plugin.git')
        ->call('addRepository')
        ->assertHasNoErrors();

    expect(PluginRepository::count())->toBe(1);

    $component->call('removeRepository', PluginRepository::first()->id);
    expect(PluginRepository::count())->toBe(0);
});

test('custom repositories are written into the plugins composer manifest on install', function () {
    fakeComposer();
    PluginRepository::create(['type' => 'vcs', 'url' => 'https://git.example.com/acme/plugin.git']);

    app(PluginInstaller::class)->install(composerEntry());

    $manifest = json_decode((string) file_get_contents(storage_path('app/plugins/composer.json')), true);

    expect($manifest['repositories'])->toBe([
        ['type' => 'vcs', 'url' => 'https://git.example.com/acme/plugin.git'],
    ]);
});

test('installing from a raw package name derives the key and shows up off-catalog', function () {
    Settings::setEnabled(true);
    fakeComposer();
    Http::fake(); // empty catalog + no Packagist calls

    Livewire\Livewire::actingAs(composerAdmin())
        ->test(Marketplace::class)
        ->call('openSource')
        ->set('sourcePackage', 'board/plugin-demo')
        ->call('installFromSource')
        ->assertSet('showSource', false)
        ->assertSee(__('Installés hors catalogue'))
        ->assertSee('board/plugin-demo');

    $package = PluginPackage::where('key', 'board-plugin-demo')->firstOrFail();

    expect($package->source)->toBe('composer')
        ->and($package->package_name)->toBe('board/plugin-demo')
        ->and($package->name)->toBe('Plugin Demo');
});

// --- Phase 3 : bannières / captures + stats Packagist --------------------------

test('the catalog keeps https banners and screenshots and drops the rest', function () {
    $markdown = <<<'MD'
    ---
    key: demo
    name: Demo
    repo: acme/demo
    banner: https://cdn.example.com/banner.png
    screenshots:
      - https://cdn.example.com/shot1.png
      - http://cdn.example.com/insecure.png
      - javascript:alert(1)
    ---
    Body.
    MD;

    Http::fake([
        'api.github.com/repos/B-o-a-r-d/Marketplace/contents/plugins' => Http::response([
            ['type' => 'file', 'name' => 'demo.md', 'download_url' => 'https://raw.githubusercontent.com/B-o-a-r-d/Marketplace/main/plugins/demo.md'],
        ]),
        'raw.githubusercontent.com/B-o-a-r-d/Marketplace/*' => Http::response($markdown),
    ]);

    $entry = app(MarketplaceClient::class)->catalog()->first();

    expect($entry['banner'])->toBe('https://cdn.example.com/banner.png')
        ->and($entry['screenshots'])->toBe(['https://cdn.example.com/shot1.png']);
});

test('packagist download totals are fetched and failures cached as unknown', function () {
    Http::fake([
        'packagist.org/packages/board/plugin-demo.json' => Http::response(['package' => ['downloads' => ['total' => 1234]]]),
        'packagist.org/packages/board/plugin-down.json' => Http::response(null, 500),
    ]);

    $stats = app(PackagistStats::class);

    expect($stats->downloads('board/plugin-demo'))->toBe(1234)
        ->and($stats->downloads('board/plugin-down'))->toBeNull();

    // The failure is cached (sentinel), so a second read makes no new HTTP call.
    $stats->downloads('board/plugin-down');
    Http::assertSentCount(2);
});

test('a catalog cached by an older release renders with defaults instead of crashing', function () {
    Settings::setEnabled(true);

    // Legacy entry shape (pre-v0.1.5: no package/banner/screenshots keys),
    // planted straight into the current cache key.
    cache()->put('marketplace:catalog:v2', [[
        'key' => 'legacy',
        'name' => 'Legacy',
        'repo' => 'acme/legacy',
        'description' => 'Cached by an old release.',
        'author' => '',
        'homepage' => '',
        'icon' => 'puzzle-piece',
        'capabilities' => [],
        'category' => 'other',
        'readme' => 'Body.',
    ]], now()->addHour());

    $entry = app(MarketplaceClient::class)->catalog()->firstWhere('key', 'legacy');

    expect($entry['package'])->toBe('')
        ->and($entry['banner'])->toBe('')
        ->and($entry['screenshots'])->toBe([]);

    Livewire\Livewire::actingAs(composerAdmin())
        ->test(Marketplace::class)
        ->assertOk()
        ->assertSee('Legacy');
});

test('checkUpdates runs no composer process while nothing is composer-installed', function () {
    $fake = fakeComposer();

    // A legacy archive package exists, but no composer-sourced one.
    PluginPackage::create([
        'key' => 'legacy', 'name' => 'Legacy', 'repo' => 'acme/legacy', 'version' => '1.0.0',
        'path' => 'plugins/legacy', 'enabled' => true, 'available_version' => '1.0.0',
    ]);
    Http::fake(['api.github.com/repos/acme/legacy/releases/latest' => Http::response(['tag_name' => 'v1.0.0'])]);

    app(PluginInstaller::class)->checkUpdates();

    expect($fake->commands)->toBe([]);
});
