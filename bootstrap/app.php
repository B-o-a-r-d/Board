<?php

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Only trust reverse proxies we actually sit behind — trusting '*' lets
        // any client spoof X-Forwarded-For and defeat the IP-based login throttle.
        // Default to private ranges (covers a Docker/Traefik network and local
        // direct access); override with a CIDR list via TRUSTED_PROXIES.
        $trustedProxies = env('TRUSTED_PROXIES');

        $middleware->trustProxies(
            at: match (true) {
                $trustedProxies === '*' => '*',
                is_string($trustedProxies) && trim($trustedProxies) !== '' => array_map(trim(...), explode(',', $trustedProxies)),
                default => ['10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16', '127.0.0.1', '::1'],
            },
            headers: Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
            | Request::HEADER_X_FORWARDED_AWS_ELB,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
