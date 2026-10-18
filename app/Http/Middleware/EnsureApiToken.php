<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;

/**
 * API v2: Token aus den Einstellungen prüfen (Bearer oder X-Api-Token)
 *
 * API v2: check the token from the settings (Bearer or X-Api-Token)
 */
class EnsureApiToken
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) Setting::get('api_token');

        if ($expected === '') {
            return response()->json(['error' => 'API ist deaktiviert – kein Token in den Einstellungen hinterlegt. / API disabled – no token configured.'], 403);
        }

        $given = $request->bearerToken() ?: (string) $request->header('X-Api-Token');

        if ($given === '' || ! hash_equals($expected, $given)) {
            return response()->json(['error' => 'Ungültiges API-Token. / Invalid API token.'], 401);
        }

        return $next($request);
    }
}
