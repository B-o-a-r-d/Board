<?php

namespace App\Automations\Actions;

use App\Automations\Contracts\AutomationAction;
use App\Models\Card;
use Board\PluginSdk\Support\SafeUrl;
use Illuminate\Support\Facades\Http;

class SendWebhookAction implements AutomationAction
{
    public static function key(): string
    {
        return 'send_webhook';
    }

    public function label(): string
    {
        return 'Envoyer un webhook';
    }

    public function configFields(): array
    {
        return [
            ['key' => 'url', 'label' => 'URL (https)', 'type' => 'url'],
            ['key' => 'secret', 'label' => 'Secret HMAC (optionnel)', 'type' => 'password'],
        ];
    }

    public function run(Card $card, array $config): void
    {
        $url = trim((string) ($config['url'] ?? ''));

        // SSRF guard: http(s) only, no internal/reserved hosts. safeConnection()
        // returns the exact IP it vetted so we can pin the socket to it below —
        // closing the DNS-rebinding window between this check and the connect.
        $connection = SafeUrl::safeConnection($url);

        if ($url === '' || $connection === null) {
            throw new \RuntimeException('Webhook URL refused (unsafe or internal host).');
        }

        $payload = [
            'event' => 'automation.webhook',
            'card' => [
                'id' => $card->public_id,
                'title' => $card->title,
                'list' => $card->list?->name,
                'board' => $card->board->name,
                'due_at' => $card->due_at?->toIso8601String(),
                'completed_at' => $card->completed_at?->toIso8601String(),
            ],
        ];

        $body = (string) json_encode($payload);
        $secret = (string) ($config['secret'] ?? '');

        Http::timeout(5)
            ->connectTimeout(3)
            ->withOptions([
                // No redirects (a 3xx could bounce us inside) and the connection
                // pinned to the vetted IP (defeats DNS rebinding).
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$connection['host']}:{$connection['port']}:{$connection['ip']}"]],
            ])
            ->withHeaders(array_filter([
                'Content-Type' => 'application/json',
                'X-Board-Signature' => $secret !== '' ? 'sha256='.hash_hmac('sha256', $body, $secret) : null,
            ]))
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }
}
