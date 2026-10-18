<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Daten für die Fehlerseiten (Standard 03): Fehlernummer, Grund, Wartezeit,
 * Upload-Grenze und Anmeldestatus. Nichts hier darf selbst scheitern — eine
 * Fehlerseite läuft auch, wenn Datenbank oder Sitzung fehlen.
 *
 * Einbau in bootstrap/app.php, damit die Nummer auch im Protokoll steht:
 *     ->withExceptions(fn (Exceptions $e) => $e->context(fn () => ['reference' => ErrorPage::reference()]))
 *
 * Data for the error pages (Standard 03): error reference, reason, waiting
 * time, upload limit and sign-in state. Nothing here may fail itself — an
 * error page also works when the database or the session is missing.
 *
 * Setup in bootstrap/app.php, so that the reference also appears in the log:
 *     ->withExceptions(fn (Exceptions $e) => $e->context(fn () => ['reference' => ErrorPage::reference()]))
 */
class ErrorPage
{
    /** Ohne 0/O, 1/I/L, 2/Z, 5/S, 8/B — am Telefon eindeutig vorzulesen / Unambiguous when read out on the phone */
    private const CHARACTERS = 'ACDEFGHJKMNPQRTUVWXY34679';

    private static ?string $reference = null;

    /**
     * Fehlernummer dieser Anfrage: dieselbe im Protokoll (Hilfe → Letzte Fehler)
     * und auf der Fehlerseite, z. B. „K7QD3M“.
     *
     * Error reference of this request: the same in the log (help → recent
     * errors) and on the error page, e.g. “K7QD3M”.
     */
    public static function reference(): string
    {
        if (self::$reference === null) {
            $r = '';
            for ($i = 0; $i < 6; $i++) {
                $r .= self::CHARACTERS[random_int(0, strlen(self::CHARACTERS) - 1)];
            }
            self::$reference = $r;
        }

        return self::$reference;
    }

    /**
     * Grund aus abort(403, "…") — nur eigene Meldungen, die in lang/en.json
     * stehen. Fremde Texte (Framework, Pfade, Technik) zeigen wir nie.
     *
     * Reason from abort(403, "…") — only our own messages that exist in
     * lang/en.json. Foreign texts (framework, paths, internals) are never shown.
     */
    public static function reason(?Throwable $e): ?string
    {
        try {
            if (! $e instanceof HttpExceptionInterface || $e->getStatusCode() >= 500) {
                return null;
            }
            $text = trim($e->getMessage());
            if ($text === '' || ! app('translator')->hasForLocale($text, 'en')) {
                return null;
            }

            return __($text);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Steht der Fehler im Protokoll? Echte Ausnahmen packt Laravel für die
     * Anzeige in eine HttpException (mit getPrevious()); abort(500) und die
     * Wartung (503) werden nicht protokolliert — dann keine Fehlernummer zeigen.
     *
     * Is the error in the log? Laravel wraps real exceptions in an
     * HttpException for display (with getPrevious()); abort(500) and
     * maintenance (503) are not logged — then show no error reference.
     */
    public static function isLogged(?Throwable $e): bool
    {
        return $e !== null && ($e->getPrevious() !== null || ! $e instanceof HttpExceptionInterface);
    }

    /** Sekunden aus „Retry-After“ (429/503) oder null / Seconds from “Retry-After” (429/503) or null */
    public static function retryAfter(?Throwable $e): ?int
    {
        if (! $e instanceof HttpExceptionInterface) {
            return null;
        }
        $value = $e->getHeaders()['Retry-After'] ?? null;

        return is_numeric($value) && $value > 0 ? (int) $value : null;
    }

    /** Größte erlaubte Anfrage aus php.ini, z. B. „64 MB“ / Largest allowed request from php.ini, e.g. “64 MB” */
    public static function uploadLimit(): string
    {
        $bytes = array_filter(array_map(
            fn ($k) => self::bytes((string) ini_get($k)),
            ['upload_max_filesize', 'post_max_size']
        ));
        $min = $bytes ? min($bytes) : 0;

        return $min >= 1048576 ? round($min / 1048576) . ' MB' : max(1, round($min / 1024)) . ' KB';
    }

    /**
     * Ist jemand angemeldet? Bei unbekannten Adressen (404) läuft die Sitzung
     * nicht — dann gilt man hier als Gast. crewgate merkt sich die Anmeldung
     * in der Sitzung unter „ldap_user“.
     *
     * Is someone signed in? For unknown addresses (404) the session does not
     * run — then one counts as a guest here. crewgate keeps the sign-in in
     * the session under “ldap_user”.
     */
    public static function signedIn(): bool
    {
        try {
            return auth()->check() || (session()->isStarted() && session()->has('ldap_user'));
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Auffangroute für unbekannte Adressen (routes/web.php: Route::fallback).
     * Sie läuft durch die web-Middleware, damit die 404-Seite in der gewählten
     * Sprache erscheint. Gibt es die Adresse mit einer anderen Methode
     * (z. B. nur POST), bleibt es wie ohne Auffangroute bei 405.
     *
     * Fallback route for unknown addresses (routes/web.php: Route::fallback).
     * It runs through the web middleware so that the 404 page appears in the
     * chosen language. If the address exists with another method (e.g. POST
     * only), the answer stays 405 as without the fallback.
     */
    public static function unknownAddress(\Illuminate\Http\Request $request): never
    {
        $allowed = [];
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                app('router')->getRoutes()->match(\Illuminate\Http\Request::create($request->getPathInfo(), $method));
                $allowed[] = $method;
            } catch (Throwable) {
                // keine Route mit dieser Methode / no route with this method
            }
        }

        abort($allowed ? 405 : 404, '', $allowed ? ['Allow' => implode(', ', $allowed)] : []);
    }

    /** „64M“ → Bytes / “64M” → bytes */
    private static function bytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtoupper(substr($value, -1))) {
            'G' => $number * 1073741824,
            'M' => $number * 1048576,
            'K' => $number * 1024,
            default => $number,
        };
    }
}
