<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->session()->get('ldap_user');

        if (! $user || ! ($user['is_admin'] ?? false)) {
            abort(403, 'Nur für Administratoren.');
        }

        return $next($request);
    }
}
