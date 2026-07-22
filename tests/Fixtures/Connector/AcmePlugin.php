<?php

namespace Tests\Fixtures\Connector;

use Board\PluginSdk\Contracts\DefinesActivities;
use Board\PluginSdk\Contracts\Plugin;
use Board\PluginSdk\Contracts\ProvidesAssets;
use Board\PluginSdk\Contracts\ProvidesAutomationActions;
use Board\PluginSdk\Contracts\ProvidesBoardType;
use Board\PluginSdk\Contracts\ProvidesCardFields;
use Board\PluginSdk\Contracts\ProvidesListSource;
use Board\PluginSdk\Contracts\ProvidesMcpTools;
use Board\PluginSdk\Contracts\ProvidesOAuth;
use Board\PluginSdk\Contracts\ProvidesSettings;
use Board\PluginSdk\PluginListItem;
use Board\PluginSdk\PluginToast;
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
class AcmePlugin implements DefinesActivities, Plugin, ProvidesAssets, ProvidesAutomationActions, ProvidesBoardType, ProvidesCardFields, ProvidesListSource, ProvidesMcpTools, ProvidesOAuth, ProvidesSettings
{
    public const AUTHORIZE_URL = 'https://acme.test/oauth/authorize';

    public const TOKEN_URL = 'https://acme.test/oauth/token';

    public const API = 'https://api.acme.test';

    public static function key(): string
    {
        return 'acme';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function settings(): array
    {
        return [
            ['key' => 'default_instance_url', 'label' => 'Default instance', 'type' => 'url', 'required' => false, 'placeholder' => 'https://acme.test'],
            ['key' => 'allowed_hosts', 'label' => 'Allowed internal hosts', 'type' => 'text', 'required' => false],
            ['key' => 'api_token', 'label' => 'API token', 'type' => 'password', 'required' => false],
        ];
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

    // --- ProvidesAssets (plugin front-end assets tests) ------------------------

    /**
     * @return array<int, string>
     */
    public function assetStyles(): array
    {
        return ['acme.css'];
    }

    /**
     * @return array<int, string>
     */
    public function assetScripts(): array
    {
        return ['acme.js'];
    }

    // --- ProvidesBoardType (board-type system tests) ---------------------------

    public function boardTypeKey(): string
    {
        return 'acmeboard';
    }

    public function boardTypeLabel(): string
    {
        return 'Acme Board';
    }

    public function boardTypeIcon(): string
    {
        return 'books';
    }

    public function boardTypeRoute(): string
    {
        return 'acme-board.show';
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

    // --- ProvidesCardFields -----------------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function cardFields(array $config = []): array
    {
        return [
            ['key' => 'acme_status', 'name' => 'Acme status', 'type' => 'select', 'options' => ['Open', 'In progress', 'Done'], 'placement' => 'content'],
            ['key' => 'acme_ref', 'name' => 'Acme reference', 'type' => 'url'],
        ];
    }

    // --- ProvidesAutomationActions ---------------------------------------------

    /**
     * @return array<int, array<string, mixed>>
     */
    public function automationActions(): array
    {
        return [[
            'key' => 'create_item',
            'label' => 'Créer un item Acme',
            'configFields' => [
                ['key' => 'resource', 'label' => 'Resource', 'type' => 'text'],
            ],
        ]];
    }

    public function runAutomationAction(array $config, string $key, array $card, array $actionConfig): ?PluginToast
    {
        if ($key !== 'create_item') {
            return null;
        }

        $item = $this->client($config)
            ->post(self::API.'/items', [
                'resource' => (string) ($actionConfig['resource'] ?? ''),
                'title' => (string) ($card['title'] ?? ''),
                'card_id' => $card['id'] ?? null,
            ])
            ->throw()
            ->json();

        $url = (string) (is_array($item) ? ($item['url'] ?? '') : '');

        return new PluginToast(
            message: 'Item Acme créé',
            description: (string) ($actionConfig['resource'] ?? ''),
            duration: 6000,
            actions: $url === '' ? [] : [
                ['label' => 'Ouvrir', 'url' => $url],
                // Dropped by the host: only http(s) links reach the browser.
                ['label' => 'Evil', 'url' => 'javascript:alert(1)'],
            ],
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
