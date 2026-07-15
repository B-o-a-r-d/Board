<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rate-limits the sensitive auth POST endpoints Fortify does not throttle on its
 * own (login and two-factor already carry their own limiters). Without this, the
 * password-reset request, password-reset submit, registration and password
 * confirmation endpoints are open to brute force, account spam, mail-bombing and
 * user enumeration.
 *
 * Applied on the web group (cheap path guard), so it is independent of the order
 * in which Fortify registers its routes.
 */
class ThrottleSensitiveAuth
{
    /** POST paths to protect, matched against {@see Request::path()} (no leading slash). */
    private const PATHS = ['register', 'forgot-password', 'reset-password', 'user/confirm-password'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('post') || ! in_array($request->path(), self::PATHS, true)) {
            return $next($request);
        }

        // Tight per-identity limit, plus a looser per-IP ceiling that blunts
        // distributed enumeration across many emails from one source.
        $email = Str::transliterate(Str::lower((string) $request->input('email')));

        $limits = [
            'auth-attempts:'.$email.'|'.$request->ip() => 5,
            'auth-attempts-ip:'.$request->ip() => 20,
        ];

        foreach ($limits as $key => $maxAttempts) {
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                abort(429, __('Trop de tentatives. Réessayez dans un instant.'));
            }
        }

        foreach (array_keys($limits) as $key) {
            RateLimiter::hit($key);
        }

        return $next($request);
    }
}
