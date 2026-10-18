<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Sprache setzen: gewählte Sprache aus der Session, sonst anhand der
 * Browsersprache (Deutsch, wenn erkannt), Fallback Englisch.
 *
 * Set the language: chosen language from the session, otherwise based on the
 * browser language (German if detected), fallback English.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale');

        if (! in_array($locale, ['de', 'en'], true)) {
            $accept = strtolower((string) $request->header('Accept-Language'));
            $locale = str_contains($accept, 'de') ? 'de' : 'en';
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
