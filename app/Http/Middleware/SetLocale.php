<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /** Locales the application ships translations for. */
    public const SUPPORTED = ['fr', 'en', 'es'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && in_array($user->locale, self::SUPPORTED, true)) {
            app()->setLocale($user->locale);
        }

        return $next($request);
    }
}
