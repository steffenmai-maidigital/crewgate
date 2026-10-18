<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Hinter einem Reverse Proxy (nginx, Traefik, Synology): die
        // X-Forwarded-*-Header auswerten, damit Laravel https/Host/Port der
        // öffentlichen URL kennt und Assets korrekt verlinkt werden.
        // Geglaubt wird nur Proxys aus internen Netzen — sonst könnte jeder
        // eine falsche Absender-IP vortäuschen. "*" = allen vertrauen.
        // Behind a reverse proxy (nginx, Traefik, Synology): evaluate the
        // X-Forwarded-* headers so that Laravel knows https/host/port of the
        // public URL and links assets correctly.
        // Only proxies from internal networks are trusted — otherwise anyone
        // could fake a sender IP. "*" = trust all.
        $proxys = env('TRUSTED_PROXIES', '127.0.0.1,10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,::1,fc00::/7');
        $middleware->trustProxies(
            at: $proxys === '*' ? '*' : array_map('trim', explode(',', $proxys)),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_AWS_ELB,
        );

        // Sicherheits-Header und sichere Cookies für jede Antwort (Standard 06)
        // Security headers and secure cookies for every response (Standard 06)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(append: [
            \App\Http\Middleware\SetLocale::class,
            // Merkt den Anlass einer Aktion fürs passende Lob von Rua / remembers the occasion of an action for Rua's fitting praise
            \App\Http\Middleware\FoxOccasion::class,
        ]);

        // Sprache vor dem Laden der Modelle aus der URL setzen — sonst erscheint
        // die 404-Seite für nicht gefundene Einträge in der falschen Sprache
        // Set the locale before route model binding — otherwise the 404 page
        // for missing records appears in the wrong language
        $middleware->prependToPriorityList(
            before: \Illuminate\Routing\Middleware\SubstituteBindings::class,
            prepend: \App\Http\Middleware\SetLocale::class,
        );

        $middleware->alias([
            'api.token' => \App\Http\Middleware\EnsureApiToken::class,
            'ldap.auth'  => \App\Http\Middleware\EnsureLdapAuthenticated::class,
            'role.hr'    => \App\Http\Middleware\EnsureHr::class,
            'role.admin' => \App\Http\Middleware\EnsureAdmin::class,
            'role.create' => \App\Http\Middleware\EnsureCanCreate::class,
            'role.hr-or-admin' => \App\Http\Middleware\EnsureHrOrAdmin::class,
            'role.manage' => \App\Http\Middleware\EnsureCanManage::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Fehlernummer in jeden Protokolleintrag; dieselbe zeigt die Fehlerseite (Standard 03)
        // Error reference in every log entry; the error page shows the same one (Standard 03)
        $exceptions->context(fn () => ['reference' => \App\Support\ErrorPage::reference()]);
    })->create();
