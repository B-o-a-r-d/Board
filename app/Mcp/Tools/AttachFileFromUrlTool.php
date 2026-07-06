<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach a file to a card by fetching it from a public http(s) URL (images or videos, up to 25 MB). MCP cannot upload raw binary, so provide a URL.')]
class AttachFileFromUrlTool extends Tool
{
    use InteractsWithMcpBoard;

    private const MAX_BYTES = 25 * 1024 * 1024;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|string',
            'url' => 'required|url|max:2048',
            'name' => 'nullable|string|max:255',
        ]);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessBoardAccess($request, $card?->board)) {
            return $error;
        }

        $url = $request->get('url');

        if (! $this->isSafeUrl($url)) {
            return Response::error('URL non autorisée (schéma ou hôte interne).');
        }

        try {
            $response = Http::timeout(10)->withOptions(['stream' => false])->get($url);
        } catch (\Throwable) {
            return Response::error('Impossible de récupérer le fichier.');
        }

        if (! $response->ok()) {
            return Response::error('Le téléchargement a échoué (HTTP '.$response->status().').');
        }

        $mime = strtolower((string) $response->header('Content-Type'));
        $mime = trim(explode(';', $mime)[0]);

        if (! Str::startsWith($mime, ['image/', 'video/'])) {
            return Response::error('Type de fichier non supporté ('.$mime.'). Seuls les images et vidéos sont acceptés.');
        }

        $body = $response->body();

        if (strlen($body) > self::MAX_BYTES) {
            return Response::error('Fichier trop volumineux (max 25 Mo).');
        }

        $name = $request->get('name') ?: (basename(parse_url($url, PHP_URL_PATH) ?: '') ?: 'fichier');
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: Str::afterLast($mime, '/');
        $path = 'attachments/'.$card->board_id.'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($path, $body);

        $attachment = $card->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'name' => $name,
            'mime_type' => $mime,
            'size' => strlen($body),
        ]);

        $this->recordMcpActivity($card, $request->user(), 'attachment.added', $this->mcpSource($request), ['name' => $name]);

        return Response::json([
            'id' => $attachment->public_id,
            'name' => $attachment->name,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    private function isSafeUrl(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)) {
            return false;
        }

        $host = $parts['host'] ?? '';
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);

        if ($ips === []) {
            return false;
        }

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID) to attach the file to.')->required(),
            'url' => $schema->string()->description('Public http(s) URL of an image or video.')->required(),
            'name' => $schema->string()->description('Optional file name.'),
        ];
    }
}
