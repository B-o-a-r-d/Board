<?php

use App\Models\User;
use Board\Marketplace\Models\PluginPackage;
use Board\Marketplace\PluginInstaller;
use Board\Marketplace\PluginInstallException;
use Board\Marketplace\PluginLoader;
use Board\PluginSdk\PluginRegistry;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

afterEach(function () {
    File::deleteDirectory(storage_path('app/plugins'));
});

/**
 * @param  array<string, mixed>  $composerOverride
 */
function fakeGithubRelease(string $tag = 'v1.0.0', array $composerOverride = []): void
{
    $composer = array_replace_recursive([
        'name' => 'acme/demo-plugin',
        'require' => ['board/plugin-sdk' => '^0.2'],
        'autoload' => ['psr-4' => ['Acme\\DemoPlugin\\' => 'src/']],
        'extra' => ['board' => ['sdk_contract' => 1], 'laravel' => ['providers' => ['Acme\\DemoPlugin\\DemoServiceProvider']]],
    ], $composerOverride);

    Http::fake([
        'api.github.com/repos/acme/demo/releases/latest' => Http::response(['tag_name' => $tag]),
        'raw.githubusercontent.com/acme/demo/*/composer.json' => Http::response($composer),
        'api.github.com/repos/acme/demo/zipball/*' => Http::response(demoZipball()),
    ]);
}

function demoEntry(): array
{
    return ['key' => 'demo', 'name' => 'Demo', 'repo' => 'acme/demo'];
}

/**
 * Fake the release + composer.json for acme/demo and serve the given zipball.
 */
function fakeGithubReleaseWithZip(string $zipball, string $tag = 'v1.0.0'): void
{
    Http::fake([
        'api.github.com/repos/acme/demo/releases/latest' => Http::response(['tag_name' => $tag]),
        'raw.githubusercontent.com/acme/demo/*/composer.json' => Http::response([
            'name' => 'acme/demo', 'require' => ['board/plugin-sdk' => '^0.2'],
            'extra' => ['board' => ['sdk_contract' => 1]],
        ]),
        'api.github.com/repos/acme/demo/zipball/*' => Http::response($zipball),
    ]);
}

test('it refuses a zip-slip archive (path traversal) and writes nothing', function () {
    fakeGithubReleaseWithZip(maliciousZipball('acme-demo-abc1234/../../../../evil.php'));

    expect(fn () => app(PluginInstaller::class)->install(demoEntry()))
        ->toThrow(PluginInstallException::class);

    expect(PluginPackage::count())->toBe(0)
        ->and(is_dir(storage_path('app/plugins/demo')))->toBeFalse()
        ->and(is_file(storage_path('evil.php')))->toBeFalse();
});

test('it refuses an archive with an absolute-path entry', function () {
    fakeGithubReleaseWithZip(maliciousZipball('/tmp/board-evil.php'));

    expect(fn () => app(PluginInstaller::class)->install(demoEntry()))
        ->toThrow(PluginInstallException::class);

    expect(is_file('/tmp/board-evil.php'))->toBeFalse();
});

test('it rejects an unsafe catalog repo before any download', function () {
    expect(fn () => app(PluginInstaller::class)->install(['key' => 'demo', 'name' => 'X', 'repo' => '../evil']))
        ->toThrow(PluginInstallException::class);
});

test('it rejects an unsafe catalog key before any download', function () {
    expect(fn () => app(PluginInstaller::class)->install(['key' => '../x', 'name' => 'X', 'repo' => 'acme/demo']))
        ->toThrow(PluginInstallException::class);
});

test('it rejects a release tag that carries path traversal', function () {
    Http::fake([
        'api.github.com/repos/acme/demo/releases/latest' => Http::response(['tag_name' => '../../evil']),
    ]);

    expect(fn () => app(PluginInstaller::class)->install(demoEntry()))
        ->toThrow(PluginInstallException::class);
});

test('it refuses to install a plugin that declares no / an unsupported SDK contract', function () {
    Http::fake([
        'api.github.com/repos/acme/demo/releases/latest' => Http::response(['tag_name' => 'v1.0.0']),
        'raw.githubusercontent.com/acme/demo/*/composer.json' => Http::response([
            'name' => 'acme/demo', 'require' => ['board/plugin-sdk' => '^0.2'], // no extra.board.sdk_contract
        ]),
    ]);

    expect(fn () => app(PluginInstaller::class)->install(demoEntry()))
        ->toThrow(PluginInstallException::class);

    expect(PluginPackage::count())->toBe(0);
});

test('the loader quarantines an incompatible package instead of crashing the boot', function () {
    // A legacy/incompatible install (no recorded contract — exactly the prod
    // incident). The gate must skip it WITHOUT loading its class, so no files are
    // needed and boot() must not throw.
    PluginPackage::create([
        'key' => 'stale', 'name' => 'Stale', 'repo' => 'acme/stale',
        'version' => '1.0.0', 'sdk_constraint' => '^0.2', 'contract_version' => null,
        'path' => 'plugins/stale', 'enabled' => true, 'available_version' => '1.0.0',
    ]);

    (new PluginLoader($this->app))->boot(); // must not crash

    $package = PluginPackage::where('key', 'stale')->firstOrFail();
    expect($package->isCompatible())->toBeFalse()
        ->and($package->load_error)->not->toBeNull();
});

test('it installs a plugin package from a github release and the loader boots it', function () {
    fakeGithubRelease();

    $package = app(PluginInstaller::class)->install(demoEntry());

    expect($package->version)->toBe('1.0.0')
        ->and($package->sdk_constraint)->toBe('^0.2')
        ->and(is_file(storage_path('app/plugins/demo/composer.json')))->toBeTrue()
        ->and(is_file(storage_path('app/plugins/demo/src/DemoPlugin.php')))->toBeTrue();

    // Simulate the next request: the loader registers the runtime package.
    (new PluginLoader($this->app))->boot();

    $plugin = app(PluginRegistry::class)->get('demo');

    expect($plugin)->not->toBeNull()
        ->and($plugin->label())->toBe('Demo');
});

test('it blocks a release that is incompatible with the host SDK', function () {
    fakeGithubRelease(composerOverride: ['require' => ['board/plugin-sdk' => '^9.0']]);

    expect(fn () => app(PluginInstaller::class)->install(demoEntry()))
        ->toThrow(PluginInstallException::class);

    expect(PluginPackage::count())->toBe(0)
        ->and(is_dir(storage_path('app/plugins/demo')))->toBeFalse();
});

test('it fails when the repo has no published release', function () {
    Http::fake(['api.github.com/repos/acme/demo/releases/latest' => Http::response([], 404)]);

    expect(fn () => app(PluginInstaller::class)->install(demoEntry()))
        ->toThrow(PluginInstallException::class);
});

test('uninstall removes the files and the row', function () {
    fakeGithubRelease();
    app(PluginInstaller::class)->install(demoEntry());

    app(PluginInstaller::class)->uninstall('demo');

    expect(PluginPackage::count())->toBe(0)
        ->and(is_dir(storage_path('app/plugins/demo')))->toBeFalse();
});

test('a breaking (major) update is blocked without confirmation, allowed with it', function () {
    $this->actingAs(User::factory()->create());

    // A single stub set whose "latest" tag we flip by reference (calling
    // Http::fake twice would merge stubs and the first would keep winning).
    $tag = 'v1.0.0';
    $composer = [
        'name' => 'acme/demo-plugin',
        'require' => ['board/plugin-sdk' => '^0.2'],
        'autoload' => ['psr-4' => ['Acme\\DemoPlugin\\' => 'src/']],
        'extra' => ['board' => ['sdk_contract' => 1], 'laravel' => ['providers' => ['Acme\\DemoPlugin\\DemoServiceProvider']]],
    ];
    Http::fake([
        'api.github.com/repos/acme/demo/releases/latest' => function () use (&$tag) {
            return Http::response(['tag_name' => $tag]);
        },
        'raw.githubusercontent.com/acme/demo/*/composer.json' => fn () => Http::response($composer),
        'api.github.com/repos/acme/demo/zipball/*' => fn () => Http::response(demoZipball()),
    ]);

    app(PluginInstaller::class)->install(demoEntry());

    $tag = 'v2.0.0'; // a new major release appears

    expect(fn () => app(PluginInstaller::class)->update('demo'))
        ->toThrow(PluginInstallException::class);

    expect(PluginPackage::where('key', 'demo')->value('version'))->toBe('1.0.0');

    app(PluginInstaller::class)->update('demo', confirmBreaking: true);

    expect(PluginPackage::where('key', 'demo')->value('version'))->toBe('2.0.0');
});

test('checkUpdates records the latest available version and breaking flag', function () {
    PluginPackage::create([
        'key' => 'demo', 'name' => 'Demo', 'repo' => 'acme/demo',
        'version' => '1.0.0', 'sdk_constraint' => '^0.2', 'path' => 'plugins/demo',
        'enabled' => true, 'available_version' => '1.0.0',
    ]);

    Http::fake(['api.github.com/repos/acme/demo/releases/latest' => Http::response(['tag_name' => 'v2.0.0'])]);

    app(PluginInstaller::class)->checkUpdates();

    $package = PluginPackage::where('key', 'demo')->firstOrFail();

    expect($package->version)->toBe('1.0.0')
        ->and($package->available_version)->toBe('2.0.0')
        ->and($package->breaking_update)->toBeTrue()
        ->and($package->hasUpdate())->toBeTrue();
});
