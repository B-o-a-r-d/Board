<?php

use Board\Marketplace\MarketplaceClient;
use Board\Marketplace\Models\PluginPackage;
use Board\Marketplace\PluginInstaller;
use Board\Marketplace\PluginInstallException;
use Board\Marketplace\Support\ComposerRunner;
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
