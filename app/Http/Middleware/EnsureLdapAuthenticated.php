<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Angemeldet? (Sitzung aus AuthController — eigenes Konto oder Verzeichnis.)
 * Ist der zweite Faktor für die Gruppe Pflicht und noch nicht eingerichtet,
 * geht es nur auf die Konto-Seite und zum Abmelden.
 *
 * Signed in? (Session from AuthController — own account or directory.)
 * If the second factor is mandatory for the group and not yet set up,
 * only the account page and signing out are allowed.
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
        // „mfa_pending“): einmal neu anmelden, damit keine Pflicht verloren geht
        // Session from before the English keys (standard 10, without
        // "mfa_pending"): sign in once more so that no requirement gets lost
        if (! array_key_exists('mfa_pending', $u)) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if ($u['mfa_pending'] && ! $request->routeIs('account', 'account.*', 'logout')) {
            return redirect()->route('account');   // die Konto-Seite erklärt, warum / the account page explains why
        }

        return $next($request);
    }
}
