<?php

use App\Models\User;
use Board\Marketplace\Livewire\Marketplace;
use Board\Marketplace\MarketplaceClient;
use Board\Marketplace\Models\PluginPackage;
use Board\Marketplace\PluginInstaller;
use Board\Marketplace\Support\Settings;
use Board\PluginSdk\Support\PluginSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

/**
 * @param  array<string, mixed>  $overrides
 */
function installedAcmePackage(array $overrides = []): PluginPackage
{
    return PluginPackage::create(array_merge([
        'key' => 'acme', 'name' => 'Acme', 'repo' => 'acme/demo', 'version' => '1.0.0',
        'sdk_constraint' => '^0.2', 'path' => 'plugins/acme', 'enabled' => true, 'available_version' => '1.0.0',
    ], $overrides));
}

afterEach(function () {
    // Only the fixtures this suite creates — never the whole plugins dir, which
    // on a dev machine can hold real locally-installed Power-Ups.
    File::deleteDirectory(storage_path('app/plugins/acme'));
    File::deleteDirectory(storage_path('app/plugins/demo'));
});

function catalogMarkdown(): string
{
    return <<<'MD'
    ---
    key: demo
    name: Demo
    repo: acme/demo
    description: A demo plugin.
    icon: puzzle-piece
    capabilities: [list-source, activities]
    category: connectors
    ---
    A longer description.
    MD;
}

/**
 * Fake the catalog listing + (optionally) the release/zipball for an install.
 */
function fakeMarketplace(bool $withRelease = false): void
{
    $stubs = [
        'api.github.com/repos/B-o-a-r-d/Marketplace/contents/plugins' => Http::response([
            ['type' => 'file', 'name' => 'demo.md', 'download_url' => 'https://raw.githubusercontent.com/B-o-a-r-d/Marketplace/main/plugins/demo.md'],
        ]),
        'raw.githubusercontent.com/B-o-a-r-d/Marketplace/*' => Http::response(catalogMarkdown()),
    ];

    if ($withRelease) {
        $stubs['api.github.com/repos/acme/demo/releases/latest'] = Http::response(['tag_name' => 'v1.0.0']);
        $stubs['raw.githubusercontent.com/acme/demo/*/composer.json'] = Http::response([
            'name' => 'acme/demo-plugin',
            'require' => ['board/plugin-sdk' => '^0.2'],
            'autoload' => ['psr-4' => ['Acme\\DemoPlugin\\' => 'src/']],
            'extra' => ['board' => ['sdk_contract' => 1], 'laravel' => ['providers' => ['Acme\\DemoPlugin\\DemoServiceProvider']]],
        ]);
        $stubs['api.github.com/repos/acme/demo/zipball/*'] = Http::response(demoZipball());
    }

    Http::fake($stubs);
}

function admin(): User
{
    return User::factory()->create(['is_admin' => true]);
}

test('the marketplace client parses catalog front-matter', function () {
    fakeMarketplace();

    $catalog = app(MarketplaceClient::class)->catalog();

    expect($catalog)->toHaveCount(1)
        ->and($catalog->first()['key'])->toBe('demo')
        ->and($catalog->first()['repo'])->toBe('acme/demo')
        ->and($catalog->first()['capabilities'])->toBe(['list-source', 'activities']);
});

test('the marketplace client drops a catalog entry with an unsafe repo or key', function () {
    $malicious = <<<'MD'
    ---
    key: demo
    name: Demo
    repo: ../evil
    ---
    A tampered entry.
    MD;

    Http::fake([
        'api.github.com/repos/B-o-a-r-d/Marketplace/contents/plugins' => Http::response([
            ['type' => 'file', 'name' => 'demo.md', 'download_url' => 'https://raw.githubusercontent.com/B-o-a-r-d/Marketplace/main/plugins/demo.md'],
        ]),
        'raw.githubusercontent.com/B-o-a-r-d/Marketplace/*' => Http::response($malicious),
    ]);

    expect(app(MarketplaceClient::class)->catalog())->toHaveCount(0);
});

test('plugin instance settings round-trip through the encrypted settings store', function () {
    PluginSettings::for('acme')->put(['allowed_hosts' => '10.0.0.5', 'api_token' => 's3cret']);

    expect(PluginSettings::for('acme')->get('allowed_hosts'))->toBe('10.0.0.5')
        ->and(PluginSettings::for('acme')->get('api_token'))->toBe('s3cret');

    // Persisted encrypted, never in plaintext.
    $raw = (string) DB::table('settings')->where('key', 'plugin.acme')->value('value');
    expect($raw)->not->toContain('10.0.0.5')->not->toContain('s3cret');
});

test('an admin configures a plugin instance settings from the marketplace (no .env)', function () {
    Settings::setEnabled(true);
    fakeMarketplace();
    installedAcmePackage();

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startSettings', 'acme')
        ->assertSet('configuringKey', 'acme')
        ->set('settingsDraft.default_instance_url', 'https://acme.example.com')
        ->set('settingsDraft.allowed_hosts', 'gitlab.internal, 10.0.0.5')
        ->set('settingsDraft.api_token', 'tok-123')
        ->call('saveSettings')
        ->assertHasNoErrors()
        ->assertSet('configuringKey', null);

    expect(PluginSettings::for('acme')->get('default_instance_url'))->toBe('https://acme.example.com')
        ->and(PluginSettings::for('acme')->get('allowed_hosts'))->toBe('gitlab.internal, 10.0.0.5')
        ->and(PluginSettings::for('acme')->get('api_token'))->toBe('tok-123');
});

test('a blank secret keeps the stored plugin setting on re-save', function () {
    Settings::setEnabled(true);
    fakeMarketplace();
    installedAcmePackage();
    PluginSettings::for('acme')->put(['api_token' => 'keep-me']);

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startSettings', 'acme')
        ->set('settingsDraft.allowed_hosts', 'x.internal')
        ->call('saveSettings') // api_token left blank
        ->assertHasNoErrors();

    expect(PluginSettings::for('acme')->get('api_token'))->toBe('keep-me')
        ->and(PluginSettings::for('acme')->get('allowed_hosts'))->toBe('x.internal');
});

test('marketplace settings reject a malformed url', function () {
    Settings::setEnabled(true);
    fakeMarketplace();
    installedAcmePackage();

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startSettings', 'acme')
        ->set('settingsDraft.default_instance_url', 'not-a-url')
        ->call('saveSettings')
        ->assertHasErrors('settingsDraft.default_instance_url');
});

test('configuring plugin settings requires the marketplace master switch and admin', function () {
    fakeMarketplace();
    installedAcmePackage();

    // Master switch off → guarded.
    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startSettings', 'acme')
        ->assertForbidden();

    // Non-admin cannot even reach the page.
    Settings::setEnabled(true);
    Livewire::actingAs(User::factory()->create())->test(Marketplace::class)->assertForbidden();
});

test('the marketplace page is admin-only', function () {
    fakeMarketplace();

    Livewire::actingAs(User::factory()->create())->test(Marketplace::class)->assertForbidden();

    Livewire::actingAs(admin())->test(Marketplace::class)->assertOk()->assertSee('Demo');
});

test('installing is blocked while the master switch is off', function () {
    fakeMarketplace(withRelease: true);

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('install', 'demo')
        ->assertForbidden();

    expect(PluginPackage::count())->toBe(0);
});

test('an admin installs a plugin from the catalog when the switch is on', function () {
    Settings::setEnabled(true);
    fakeMarketplace(withRelease: true);

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('install', 'demo')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $package = PluginPackage::where('key', 'demo')->firstOrFail();

    expect($package->version)->toBe('1.0.0')
        ->and($package->repo)->toBe('acme/demo')
        ->and(is_file(storage_path('app/plugins/demo/composer.json')))->toBeTrue();
});

test('the uninstall modal exposes whether the plugin has data to purge', function () {
    Settings::setEnabled(true);
    fakeMarketplace(withRelease: true);
    app(PluginInstaller::class)->install(['key' => 'demo', 'name' => 'Demo', 'repo' => 'acme/demo']);

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startUninstall', 'demo')
        ->assertSet('uninstallingKey', 'demo')
        ->assertSet('purgeData', false)
        ->assertViewHas('uninstallHasData', true) // the demo fixture ships a migration
        ->call('cancelUninstall')
        ->assertSet('uninstallingKey', null);
})->afterEach(fn () => Schema::dropIfExists('demo_plugin_probe'));

test('a plain uninstall from the modal keeps the plugin data', function () {
    Settings::setEnabled(true);
    fakeMarketplace(withRelease: true);
    app(PluginInstaller::class)->install(['key' => 'demo', 'name' => 'Demo', 'repo' => 'acme/demo']);
    expect(Schema::hasTable('demo_plugin_probe'))->toBeTrue();

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startUninstall', 'demo')
        ->call('confirmUninstall')
        ->assertSet('uninstallingKey', null)
        ->assertDispatched('toast');

    expect(PluginPackage::where('key', 'demo')->exists())->toBeFalse()
        ->and(Schema::hasTable('demo_plugin_probe'))->toBeTrue();
})->afterEach(fn () => Schema::dropIfExists('demo_plugin_probe'));

test('a purge uninstall from the modal drops the plugin tables', function () {
    Settings::setEnabled(true);
    fakeMarketplace(withRelease: true);
    app(PluginInstaller::class)->install(['key' => 'demo', 'name' => 'Demo', 'repo' => 'acme/demo']);
    expect(Schema::hasTable('demo_plugin_probe'))->toBeTrue();

    Livewire::actingAs(admin())->test(Marketplace::class)
        ->call('startUninstall', 'demo')
        ->set('purgeData', true)
        ->call('confirmUninstall')
        ->assertSet('uninstallingKey', null)
        ->assertDispatched('toast');

    expect(PluginPackage::where('key', 'demo')->exists())->toBeFalse()
        ->and(Schema::hasTable('demo_plugin_probe'))->toBeFalse();
});
