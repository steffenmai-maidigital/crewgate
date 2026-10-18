<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureCanCreate
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->session()->get('ldap_user');

        if (! $user || ! ($user['can_create'] ?? false)) {
            abort(403, 'Keine Berechtigung, Vorgänge anzulegen.');
        }

        return $next($request);
    }
}
