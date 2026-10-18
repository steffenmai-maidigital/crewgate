<?php

use App\Http\Controllers\Api\V2\OnboardingApiController;
use Illuminate\Support\Facades\Route;

// CrewGate API v2 – Zugriff nur mit gültigem Token (Einstellungen → API).
// Authentifizierung: Header "Authorization: Bearer <token>" oder "X-Api-Token".
// CrewGate API v2 – access only with a valid token (Settings → API).
// Authentication: header "Authorization: Bearer <token>" or "X-Api-Token".
Route::prefix('v2')->middleware('api.token')->group(function () {
    // „cases“ = Adresse seit Standard 10; „vorgaenge“ = alte Adresse, bleibt als
    // gleichwertiger Alias (keine Weiterleitung, POST muss weiter gehen)
    // "cases" = address since standard 10; "vorgaenge" = old address, stays as
    // an equivalent alias (no redirect, POST must keep working)
    foreach (['cases', 'vorgaenge'] as $path) {
        // Vorgang anlegen (startet die Vorbereitung, Vorgesetzten-Mail geht raus)
        // Create a case (starts the preparation, the supervisor mail is sent)
        Route::post('/' . $path, [OnboardingApiController::class, 'store']);

        // Status aller Vorgänge (Filter: ?status=…&process=…)
        // Status of all cases (filter: ?status=…&process=…)
        Route::get('/' . $path, [OnboardingApiController::class, 'index']);

        // Einzelner Vorgang: Status, Quittierungen und alle gepflegten Werte
        // Single case: status, acknowledgements and all maintained values
        Route::get('/' . $path . '/{onboarding}', [OnboardingApiController::class, 'show']);
    }
});
