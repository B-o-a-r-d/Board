<?php

use App\Models\User;
use Board\Marketplace\Livewire\Marketplace;
use Board\Marketplace\MarketplaceClient;
use Board\Marketplace\Models\PluginPackage;
use Board\Marketplace\Support\Settings;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

afterEach(function () {
    File::deleteDirectory(storage_path('app/plugins'));
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
            'extra' => ['laravel' => ['providers' => ['Acme\\DemoPlugin\\DemoServiceProvider']]],
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
