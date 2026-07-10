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

        // SSRF guard: http(s) only, no internal/reserved hosts, and redirects are
        // disabled below so a public URL can't bounce the request inside.
        if ($url === '' || ! SafeUrl::isSafe($url)) {
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
            ->withOptions(['allow_redirects' => false])
            ->withHeaders(array_filter([
                'Content-Type' => 'application/json',
                'X-Board-Signature' => $secret !== '' ? 'sha256='.hash_hmac('sha256', $body, $secret) : null,
            ]))
            ->withBody($body, 'application/json')
            ->post($url)
            ->throw();
    }
}
