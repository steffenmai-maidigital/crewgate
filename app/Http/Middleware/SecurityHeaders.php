<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Sicherheits-Header für jede Antwort (Standard 06).
 *
 * - Content-Security-Policy: Skripte, Bilder, Schriften und Verbindungen nur
 *   vom eigenen Server. Selbst wenn jemand Code einschleusen könnte, ließe
 *   sich nichts von fremden Servern nachladen oder dorthin schicken.
 *   ('unsafe-inline' ist nötig, weil die Seiten kleine Skripte und Stile
 *   direkt enthalten; Benutzereingaben werden nie als HTML ausgegeben.)
 * - kein Einbetten in fremde Seiten (Clickjacking)
 * - keine Kamera, kein Mikrofon, kein Standort
 * - hinter HTTPS: Cookies nur verschlüsselt senden, HSTS
 *
 * Security headers for every response (Standard 06).
 *
 * - Content-Security-Policy: scripts, images, fonts and connections only
 *   from our own server. Even if someone could inject code, nothing
 *   could be loaded from foreign servers or sent there.
 *   ('unsafe-inline' is needed because the pages contain small scripts and
 *   styles directly; user input is never output as HTML.)
 * - no embedding in foreign pages (clickjacking)
 * - no camera, no microphone, no location
 * - behind HTTPS: send cookies encrypted only, HSTS
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $https = $request->isSecure();
        if ($https) {
            // Sitzungs- und CSRF-Cookie nie unverschlüsselt übertragen
            // Never transmit the session and CSRF cookies unencrypted
            config(['session.secure' => true]);
        }

        $response = $next($request);
        $h = $response->headers;

        if (! $h->has('Content-Security-Policy')) {
            $h->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'unsafe-inline'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' data: blob:",
                "connect-src 'self'",
                "font-src 'self'",
                "object-src 'none'",
                "base-uri 'self'",
                "form-action 'self'",
                "frame-ancestors 'self'",
            ]));
        }
        $h->set('X-Frame-Options', 'SAMEORIGIN');
        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'same-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($https) {
            $h->set('Strict-Transport-Security', 'max-age=15552000');
        }

        return $response;
    }
}
