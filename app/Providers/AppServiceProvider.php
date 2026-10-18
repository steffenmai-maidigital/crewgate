<?php

namespace App\Providers;

use App\Services\MailerService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Läuft die Anwendung öffentlich unter https (Reverse Proxy), müssen
        // auch Asset- und Routen-URLs mit https erzeugt werden.
        // If the application runs publicly under https (reverse proxy), asset
        // and route URLs must be generated with https as well.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // SMTP-/SMTPS-Einstellungen aus dem Backend anwenden
        // Apply SMTP/SMTPS settings from the back end
        MailerService::applyDatabaseConfig();
    }
}
