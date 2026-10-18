<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Zugriff auf Felder- und Produktpflege: Admins, HR (für die Anlage-Felder)
 * und alle Benutzer, die über AD-Gruppen mindestens einer Rolle angehören.
 *
 * Access to field and product maintenance: admins, HR (for the intake fields)
 * and all users who belong to at least one role via AD groups.
 */
class EnsureCanManage
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->session()->get('ldap_user');

        $allowed = $user && (
            ($user['is_admin'] ?? false)
            || ($user['is_hr'] ?? false)
            || ! empty($user['role_ids'])
        );

        if (! $allowed) {
            abort(403, 'Keine Berechtigung zur Feld- oder Produktpflege.');
        }

        return $next($request);
    }
}
