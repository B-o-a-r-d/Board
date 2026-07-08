<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\InteractsWithMcpBoard;
use App\Models\Card;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Attach a local file to a card by sending its base64-encoded content. Use this for files on your machine (read the file and base64-encode it). Images or videos, up to 15 MB decoded.')]
class AttachFileTool extends Tool
{
    use InteractsWithMcpBoard;

    private const MAX_BYTES = 15 * 1024 * 1024;

    public function handle(Request $request): Response
    {
        $request->validate([
            'card_id' => 'required|string',
            'name' => 'required|string|max:255',
            'content' => 'required|string',
            'mime' => 'nullable|string|max:100',
        ]);

        $card = $this->resolvePublicId(Card::class, $request->get('card_id'));

        if ($error = $this->denyUnlessCanContribute($request, $card?->board)) {
            return $error;
        }

        // Accept both raw base64 and data URLs (data:image/png;base64,....).
        $raw = $request->get('content');
        $raw = Str::contains($raw, ',') ? Str::afterLast($raw, ',') : $raw;

        $binary = base64_decode($raw, true);

        if ($binary === false || $binary === '') {
            return Response::error('Contenu base64 invalide.');
        }

        if (strlen($binary) > self::MAX_BYTES) {
            return Response::error('Fichier trop volumineux (max 15 Mo décodés).');
        }

        $mime = $this->detectMime($binary) ?? strtolower((string) $request->get('mime'));

        if (! Str::startsWith($mime, ['image/', 'video/'])) {
            return Response::error('Type non supporté ('.($mime ?: 'inconnu').'). Seuls les images et vidéos sont acceptées.');
        }

        $name = $request->get('name');
        $extension = pathinfo($name, PATHINFO_EXTENSION) ?: Str::afterLast($mime, '/');

        if (! $card->board->workspace->attachmentExtensionAllowed($extension)) {
            return Response::error('Type de fichier non autorisé pour ce workspace ('.$extension.').');
        }

        $path = 'attachments/'.$card->board_id.'/'.Str::random(40).'.'.$extension;

        Storage::disk('public')->put($path, $binary);

        $attachment = $card->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'public',
            'path' => $path,
            'name' => $name,
            'mime_type' => $mime,
            'size' => strlen($binary),
        ]);

        $this->recordMcpActivity($card, $request->user(), 'attachment.added', $this->mcpSource($request), ['name' => $name]);

        return Response::json([
            'id' => $attachment->public_id,
            'name' => $attachment->name,
            'url' => Storage::disk('public')->url($path),
        ]);
    }

    private function detectMime(string $binary): ?string
    {
        if (! function_exists('finfo_open')) {
            return null;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_buffer($finfo, $binary) ?: null;
        finfo_close($finfo);

        return $mime ? strtolower($mime) : null;
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'card_id' => $schema->string()->description('The card public id (ULID) to attach the file to.')->required(),
            'name' => $schema->string()->description('File name, e.g. "capture.png".')->required(),
            'content' => $schema->string()->description('Base64-encoded file content (a data: URL is also accepted).')->required(),
            'mime' => $schema->string()->description('Optional MIME type hint (auto-detected when possible).'),
        ];
    }
}
