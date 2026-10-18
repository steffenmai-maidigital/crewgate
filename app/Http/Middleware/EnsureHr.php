<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHr
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->session()->get('ldap_user');

        if (! $user || ! ($user['is_hr'] ?? false)) {
            abort(403, 'Nur für die HR-Abteilung.');
        }

        return $next($request);
    }
}
