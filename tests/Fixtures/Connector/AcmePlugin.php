<?php

namespace Tests\Fixtures\Connector;

use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\PluginListItem;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Neutral, provider-agnostic connector used as a test fixture for the host's
 * plugin *system* (registry, list sources, card enrichment, activities, MCP
 * tools and the generic OAuth broker). It stands in for a real Power-Up like
 * GitHub without coupling the core test suite to any concrete plugin package.
 *
 * All I/O targets the fake `acme.test` host so tests can `Http::fake()` it.
 */
class AcmePlugin implements DefinesActivities, Plugin, ProvidesListSource, ProvidesMcpTools, ProvidesOAuth
{
    public const AUTHORIZE_URL = 'https://acme.test/oauth/authorize';

    public const TOKEN_URL = 'https://acme.test/oauth/token';

    public const API = 'https://api.acme.test';

    public static function key(): string
    {
        return 'acme';
    }

    public function label(): string
    {
        return 'Acme';
    }

    public function description(): string
    {
        return 'Read-only lists from an Acme workspace.';
    }

    public function icon(): string
    {
        return 'plugs';
    }

    public function requiresOAuth(): bool
    {
        return true;
    }

    public function oauthProvider(): ?string
    {
        return 'acme';
    }

    public function configFields(array $config = []): array
    {
        return [
            ['key' => 'instance_url', 'label' => 'Instance URL', 'type' => 'url', 'placeholder' => 'https://acme.example.com'],
            ['key' => 'client_id', 'label' => 'Client ID', 'type' => 'text', 'placeholder' => 'acme-xxxx'],
            ['key' => 'client_secret', 'label' => 'Client secret', 'type' => 'password'],
        ];
    }

    // --- ProvidesOAuth --------------------------------------------------------

    public function authorizeUrl(array $config = []): string
    {
        // Honour a per-board instance URL when present (self-host pattern),
        // otherwise the default host.
        return isset($config['instance_url'])
            ? $config['instance_url'].'/oauth/authorize'
            : self::AUTHORIZE_URL;
    }

    public function tokenUrl(array $config = []): string
    {
        return self::TOKEN_URL;
    }

    public function scopes(): array
    {
        return ['read'];
    }

    public function authorizeParameters(): array
    {
        return ['prompt' => 'consent'];
    }

    public function resolveAccount(string $accessToken, array $config = []): ?string
    {
        return Http::withToken($accessToken)->acceptJson()
            ->get(self::API.'/user')
            ->json('login');
    }

    // --- ProvidesListSource ---------------------------------------------------

    public function sourceModes(): array
    {
        return [['key' => 'items', 'label' => 'Items']];
    }

    public function listConfigFields(array $config = []): array
    {
        return [['key' => 'resource', 'label' => 'Resource', 'type' => 'text', 'placeholder' => 'team/project']];
    }

    public function items(array $config, string $mode, array $sourceConfig): Collection
    {
        $resource = trim((string) ($sourceConfig['resource'] ?? ''));

        if ($resource === '') {
            return collect();
        }

        $limit = max(1, (int) ($sourceConfig['limit'] ?? 15));

        $items = $this->client($config)
            ->get(self::API.'/items', ['resource' => $resource, 'per_page' => $limit])
            ->json();

        return collect(is_array($items) ? $items : [])->map(fn (array $item): PluginListItem => new PluginListItem(
            externalRef: (string) ($item['id'] ?? ''),
            title: (string) ($item['title'] ?? ''),
            subtitle: (string) ($item['author'] ?? '—'),
            url: (string) ($item['url'] ?? ''),
            icon: 'file',
            timestamp: (string) ($item['created_at'] ?? ''),
        ));
    }

    // --- DefinesActivities ----------------------------------------------------

    public function activityTab(): array
    {
        return ['key' => 'acme', 'label' => 'Acme'];
    }

    public function activityTypes(): array
    {
        return ['acme.ref_linked'];
    }

    public function describeActivity(string $type, array $properties): ?string
    {
        if ($type !== 'acme.ref_linked') {
            return null;
        }

        return sprintf(
            'linked the %s "%s"',
            $properties['ref_type'] ?? 'item',
            $properties['title'] ?? ($properties['ref_id'] ?? ''),
        );
    }

    // --- ProvidesMcpTools -----------------------------------------------------

    public function mcpTools(): array
    {
        return [AcmeItemsTool::class];
    }

    // --- internals ------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $config
     */
    private function client(array $config): PendingRequest
    {
        return Http::withToken((string) ($config['token'] ?? ''))->acceptJson();
    }
}
