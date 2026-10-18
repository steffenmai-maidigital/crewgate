<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureHrOrAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->session()->get('ldap_user');

        $allowed = ($user['is_hr'] ?? false)
            || ($user['is_admin'] ?? false)
            || ($user['is_auditor'] ?? false);

        if (! $user || ! $allowed) {
            abort(403, 'Nur für HR, Administratoren und Auditoren.');
        }

        return $next($request);
    }
}
