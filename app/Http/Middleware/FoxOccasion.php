<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Merkt sich nach einer abgeschickten Aktion, ob sie ernst war – damit Rua
 * danach passend lobt (docs/RUA.md): nach Löschen, Passwort,
 * Zwei-Faktor, Token, Sicherung, Abbrechen oder Abmelden nur schlicht
 * („Erledigt.“), sonst mit Bild („Gespeichert, fest wie ein Runenstein.“).
 * Nur wenn der Controller eine Erfolgsmeldung (session 'ok') gesetzt hat;
 * der Anlass gilt wie die Meldung genau für die nächste Seite.
 *
 * Remembers after a submitted action whether it was serious – so that Rua
 * praises fittingly afterwards (docs/RUA.md): after deleting, password,
 * two-factor, token, backup, cancelling or signing out only plainly
 * ("Done."), otherwise with an image ("Saved, solid as a rune stone.").
 * Only if the controller set a success message (session 'ok'); like the
 * message, the occasion applies to exactly the next page.
 */
class FoxOccasion
{
    /** Woran eine ernste Aktion zu erkennen ist / What marks a serious action */
    private const SERIOUS = '/(destroy|delete|loesch|lösch|entfern|remove|passwor|password|2fa|mfa|totp|token|codes|logout|abmeld|sommer|summer|backup|sicherung|restore|wiederherst|leeren|clear|reset|beenden|abbrech|cancel)/u';

    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        if (! $request->isMethod('GET') && $request->hasSession() && $request->session()->has('ok')) {
            $request->session()->flash('fox_occasion', self::serious($request) ? 'serious' : 'normal');
        }

        return $response;
    }

    /** War die Anfrage eine ernste Aktion? / Was the request a serious action? */
    public static function serious(Request $request): bool
    {
        if ($request->isMethod('DELETE')) {
            return true;
        }
        $trace = strtolower(($request->route()?->getName() ?? '') . ' ' . $request->path());

        return (bool) preg_match(self::SERIOUS, $trace);
    }
}
