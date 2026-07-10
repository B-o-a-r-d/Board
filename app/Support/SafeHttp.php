<?php

namespace App\Support;

use Board\PluginSdk\Support\SafeUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * SSRF-safe outbound HTTP fetcher for user-supplied URLs.
 *
 * It closes the two gaps a plain `Http::get()` after a one-shot check still has:
 *  - Redirects: automatic following is DISABLED and each hop is re-validated with
 *    {@see SafeUrl}, so a public URL cannot 302 the server onto an internal host.
 *  - DNS rebinding: the connection is pinned (curl RESOLVE) to the exact IP that
 *    was validated, so a host that re-resolves to a private address between the
 *    check and the connect cannot be reached.
 *
 * Throws {@see SsrfException} as soon as any hop is unsafe.
 */
class SafeHttp
{
    private const MAX_REDIRECTS = 3;

    /**
     * @param  (callable(PendingRequest): PendingRequest)|null  $configure  tweak timeouts/headers/options
     * @param  array<int, string>  $allowedHosts  internal hosts to permit (e.g. a plugin allow-list)
     *
     * @throws SsrfException
     */
    public static function get(string $url, ?callable $configure = null, array $allowedHosts = []): Response
    {
        for ($hop = 0; ; $hop++) {
            $target = SafeUrl::safeConnection($url, $allowedHosts);

            if ($target === null) {
                throw new SsrfException('URL non autorisée (schéma ou hôte interne).');
            }

            $request = Http::withOptions([
                'allow_redirects' => false,
                'curl' => [CURLOPT_RESOLVE => ["{$target['host']}:{$target['port']}:{$target['ip']}"]],
            ]);

            if ($configure !== null) {
                $request = $configure($request);
            }

            $response = $request->get($url);

            if ($hop >= self::MAX_REDIRECTS || ! $response->redirect()) {
                return $response;
            }

            $location = trim((string) $response->header('Location'));

            if ($location === '') {
                return $response;
            }

            $url = self::resolveLocation($location, $url);
        }
    }

    /**
     * Resolve a (possibly relative) Location header against the current URL.
     */
    private static function resolveLocation(string $location, string $base): string
    {
        if (Str::startsWith($location, ['http://', 'https://'])) {
            return $location;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';

        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        $origin = $scheme.'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        $path = $parts['path'] ?? '/';
        $dir = substr($path, 0, (int) strrpos($path, '/') + 1);

        return $origin.$dir.$location;
    }
}
