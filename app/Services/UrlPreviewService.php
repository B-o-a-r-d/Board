<?php

namespace App\Services;

use App\Models\LinkPreview;
use App\Support\SafeHttp;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Str;

class UrlPreviewService
{
    /** Refetch cached previews (including failures) after this many days. */
    private const TTL_DAYS = 7;

    /** Max URLs previewed per text block. */
    public const MAX_PER_TEXT = 3;

    /**
     * Extract the first few http(s) URLs from a text block.
     *
     * @return array<int, string>
     */
    public function extractUrls(string $text): array
    {
        preg_match_all('#https?://[^\s<>"\'\)\]]+#i', $text, $matches);

        return array_slice(array_values(array_unique($matches[0])), 0, self::MAX_PER_TEXT);
    }

    /**
     * Resolve a preview for a single URL, using the cache when fresh.
     */
    public function preview(string $url): ?LinkPreview
    {
        $normalized = $this->normalize($url);

        if ($normalized === null) {
            return null;
        }

        $hash = sha1($normalized);
        $cached = LinkPreview::where('url_hash', $hash)->first();

        if ($cached && $cached->fetched_at && $cached->fetched_at->gt(now()->subDays(self::TTL_DAYS))) {
            return $cached->ok ? $cached : null;
        }

        $data = $this->fetch($normalized);

        $preview = LinkPreview::updateOrCreate(
            ['url_hash' => $hash],
            array_merge([
                'url' => $normalized,
                'ok' => $data !== null,
                'fetched_at' => now(),
                'title' => null,
                'description' => null,
                'image' => null,
                'site_name' => null,
            ], $data ?? []),
        );

        return $data !== null ? $preview : null;
    }

    private function normalize(string $url): ?string
    {
        $url = trim($url);
        $parts = parse_url($url);

        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null
     */
    private function fetch(string $url): ?array
    {
        try {
            // SSRF-safe: validates every redirect hop and pins the connection to
            // the checked IP (no redirect pivot, no DNS rebinding).
            $response = SafeHttp::get($url, fn (PendingRequest $r): PendingRequest => $r
                ->withHeaders([
                    'User-Agent' => 'BoardBot/1.0 (+link-preview)',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->connectTimeout(3)
                ->timeout(5));

            if (! $response->ok()) {
                return null;
            }

            $contentType = (string) $response->header('Content-Type');

            if ($contentType !== '' && ! Str::contains($contentType, 'html')) {
                return null;
            }

            return $this->parse(mb_substr($response->body(), 0, 500_000), $url);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{title: ?string, description: ?string, image: ?string, site_name: ?string}|null
     */
    private function parse(string $html, string $url): ?array
    {
        $meta = [];

        if (preg_match_all('/<meta\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/(?:property|name)\s*=\s*["\']([^"\']+)["\']/i', $tag, $key)
                    && preg_match('/content\s*=\s*["\']([^"\']*)["\']/i', $tag, $content)) {
                    $meta[strtolower($key[1])] = html_entity_decode($content[1], ENT_QUOTES | ENT_HTML5);
                }
            }
        }

        $title = $meta['og:title'] ?? null;

        if ($title === null && preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $t)) {
            $title = html_entity_decode(trim($t[1]), ENT_QUOTES | ENT_HTML5);
        }

        $description = $meta['og:description'] ?? $meta['description'] ?? null;
        $image = isset($meta['og:image']) ? $this->absolutize($meta['og:image'], $url) : null;
        $siteName = $meta['og:site_name'] ?? parse_url($url, PHP_URL_HOST);

        if (($title ?? '') === '' && ($description ?? '') === '' && ($image ?? '') === '') {
            return null;
        }

        return [
            'title' => $title !== null ? mb_substr($title, 0, 250) : null,
            'description' => $description !== null ? mb_substr($description, 0, 500) : null,
            'image' => $image !== null ? mb_substr($image, 0, 1000) : null,
            'site_name' => $siteName !== null ? mb_substr($siteName, 0, 250) : null,
        ];
    }

    private function absolutize(string $image, string $base): string
    {
        if (Str::startsWith($image, ['http://', 'https://'])) {
            return $image;
        }

        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return $origin.'/'.ltrim($image, '/');
    }
}
