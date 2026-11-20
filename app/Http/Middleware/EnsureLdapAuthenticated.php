<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Mfa;
use Closure;
use Illuminate\Http\Request;

/**
 * Angemeldet? (Sitzung aus AuthController — eigenes Konto oder Verzeichnis.)
 * Das Konto wird bei jeder Anfrage aus der Datenbank gelesen: Sperren und
 * Löschen wirken sofort, Rechte aus der Benutzerverwaltung ab der nächsten
 * Seite. Ein neues Passwort beendet alle anderen Sitzungen des Kontos. Ist
 * der zweite Faktor für die Gruppe Pflicht und noch nicht eingerichtet oder
 * das Startpasswort noch nicht geändert, geht es nur auf die Konto-Seite und
 * zum Abmelden.
 *
 * Signed in? (Session from AuthController — own account or directory.)
 * The account is read from the database on every request: locking and
 * deleting take effect at once, rights from user management from the next
 * page. A new password ends all other sessions of the account. If the second
 * factor is mandatory for the group and not yet set up, or the initial
 * password has not been changed yet, only the account page and signing out
 * are allowed.
 */
class EnsureLdapAuthenticated
{
    public function handle(Request $request, Closure $next)
    {
        $u = $request->session()->get('ldap_user');
        if (! $u) {
            return redirect()->route('login');
        }

        // Sitzung aus der Zeit vor den englischen Schlüsseln (Standard 10, ohne
        // „mfa_pending“) oder vor dem Passwort-Fingerabdruck: einmal neu
        // anmelden, damit keine Pflicht und kein Passwortwechsel verloren geht
        // Session from before the English keys (standard 10, without
        // "mfa_pending") or before the password fingerprint: sign in once more
        // so that no requirement and no password change gets lost
        if (! array_key_exists('mfa_pending', $u) || ! array_key_exists('password_fingerprint', $u)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        // Konto bei jeder Anfrage nachladen: gesperrt oder gelöscht → sofort
        // abmelden; geänderte Rechte und Rollen gelten ab der nächsten Seite
        // Reload the account on every request: locked or deleted → sign out
        // at once; changed rights and roles apply from the next page on
        $account = User::find($u['id'] ?? 0);
        if (! $account || ! $account->active) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => __('Ihr Konto ist gesperrt oder wurde gelöscht. Bitte wenden Sie sich an den Administrator.')]);
        }
        // Passwort seit der Anmeldung geändert (in einer anderen Sitzung oder
        // durch einen Admin): diese Sitzung endet. Beim eigenen Wechsel zieht
        // AccountController den Fingerabdruck der laufenden Sitzung nach.
        // Password changed since sign-in (in another session or by an admin):
        // this session ends. For an own change AccountController updates the
        // fingerprint of the running session.
        if (! hash_equals((string) $u['password_fingerprint'], $account->passwordFingerprint())) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['username' => __('Das Passwort dieses Kontos wurde geändert. Bitte melden Sie sich neu an.')]);
        }
        $fresh = array_merge($u, $account->sessionData($u['groups'] ?? []));
        // Zwei-Faktor-Stufe je Anfrage neu: eine neue Pflicht (Richtlinie, Beförderung,
        // zurückgesetzte Einrichtung) gilt sofort, nicht erst bei der nächsten Anmeldung
        // Two-factor level anew on every request: a new requirement (policy, promotion,
        // reset set-up) applies at once, not only at the next sign-in
        $fresh['mfa'] = Mfa::stricter((string) ($u['mfa'] ?? Mfa::OPTIONAL), Mfa::policy($account, $u['groups'] ?? []));
        $fresh['mfa_pending'] = $fresh['mfa'] === Mfa::REQUIRED && ! $account->hasTwoFactor();
        if ($fresh !== $u) {
            $request->session()->put('ldap_user', $fresh);
            $u = $fresh;
        }

        // Zwei-Faktor-Pflicht offen oder Startpasswort noch nicht geändert: nur
        // Konto-Seite und Abmelden, auch keine Hilfe (Standard 09)
        // Two-factor requirement open or initial password not changed yet:
        // only the account page and signing out, no help page either (standard 09)
        if (($u['mfa_pending'] || $account->mustChangePassword()) && ! $request->routeIs('account', 'account.*', 'logout')) {
            return redirect()->route('account');   // die Konto-Seite erklärt, warum / the account page explains why
        }

        return $next($request);
    }
}
