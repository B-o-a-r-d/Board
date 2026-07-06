<?php

namespace App\Services;

use App\Models\LinkPreview;
use Illuminate\Support\Facades\Http;
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
        if (! $this->isSafeHost((string) parse_url($url, PHP_URL_HOST))) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'User-Agent' => 'B0ardBot/1.0 (+link-preview)',
                'Accept' => 'text/html,application/xhtml+xml',
            ])
                ->connectTimeout(3)
                ->timeout(5)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 3,
                        'protocols' => ['http', 'https'],
                        'on_redirect' => function ($request, $response, $uri) {
                            if (! $this->isSafeHost($uri->getHost())) {
                                throw new \RuntimeException('Unsafe redirect target.');
                            }
                        },
                    ],
                ])
                ->get($url);

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
     * Reject hosts that resolve to private, loopback or reserved IP ranges (SSRF guard).
     */
    private function isSafeHost(string $host): bool
    {
        if ($host === '') {
            return false;
        }

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
