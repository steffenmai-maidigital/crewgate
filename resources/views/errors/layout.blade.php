<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    @hasSection('reload')<meta http-equiv="refresh" content="@yield('reload')">@endif
    <title>@yield('title') · {{ config('product.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('version.build') }}">
    {{-- Einheitliche Symbole (Twemoji) wie in der übrigen App, Standard 03

         Uniform symbols (Twemoji) as in the rest of the app, Standard 03 --}}
    <meta name="symbol-base" content="{{ rtrim(asset(''), '/') }}">
    <script src="{{ asset('js/symbols.js') }}?v={{ config('version.build') }}" defer></script>
    @php
        // Fehlerseiten dürfen selbst nie scheitern (z. B. wenn die Datenbank fehlt)
        // Error pages must never fail themselves (e.g. when the database is missing)
        try {
            $company = \App\Models\Setting::get('brand_company_name');
            $logoPath = \App\Models\Setting::get('brand_logo_path');
            $accent = \App\Models\Setting::get('brand_accent_color');
        } catch (\Throwable $e) {
            $company = null; $logoPath = null; $accent = null;
        }
        $logo = $logoPath ? asset($logoPath) : asset(config('product.symbol'));
        $code = (int) trim($__env->yieldContent('code'));
        $error = $exception ?? null;
        $reason = \App\Support\ErrorPage::reason($error);
        $signedIn = \App\Support\ErrorPage::signedIn();
        // Anmelden statt Startseite, wo die Anmeldung fehlt oder abgelaufen ist
        // Sign in instead of home page where the sign-in is missing or has expired
        $toLogin = ! $signedIn && in_array($code, [401, 403, 419], true);
    @endphp
    @if($accent && preg_match('/^#[0-9a-fA-F]{6}$/', $accent))<style>:root, html[data-theme="dark"] { --accent: {{ $accent }}; }</style>@endif
    <script>
        (function () {
            var t = null; try { t = localStorage.getItem('theme'); } catch (e) {}
            document.documentElement.dataset.theme = t || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>
</head>
<body>
<main>
    <div class="panel error-page">
        <img class="error-logo" src="{{ $logo }}" alt="" width="64" height="64">
        <div class="error-symbol" aria-hidden="true">@yield('symbol')</div>
        <p class="error-code">{{ __('Fehler') }} {{ $code }}</p>
        <h1>@yield('title')</h1>
        <p class="error-text">@yield('text')</p>

        @if($reason)
            <p class="error-reason">{{ $reason }}</p>
        @endif

        @hasSection('tips')
            <div class="error-tips">
                <h2>{{ __('Das können Sie tun') }}</h2>
                <ul>@yield('tips')</ul>
            </div>
        @endif

        <div class="actions">
            @if($toLogin && Route::has('login'))
                <a class="btn primary" href="{{ route('login') }}">🔑 {{ __('Anmelden') }}</a>
            @else
                <a class="btn primary" href="{{ url('/') }}">🏠 {{ __('Zur Startseite') }}</a>
            @endif
            <a class="btn secondary" href="javascript:history.back()">‹ {{ __('Zurück') }}</a>
            @if($signedIn && Route::has('help') && $code < 500)
                <a class="btn secondary" href="{{ route('help') }}">📘 {{ __('Hilfe') }}</a>
            @endif
        </div>

        @if($code >= 500 && \App\Support\ErrorPage::isLogged($error))
            {{-- Fehlernummer: steht gleich im Protokoll (Hilfe → Letzte Fehler)

                 Error reference: the same one is in the log (help → recent errors) --}}
            <p class="error-reference">
                {{ __('Fehlernummer') }} <code>{{ \App\Support\ErrorPage::reference() }}</code>
                · {{ now()->format('d.m.Y H:i') }}
            </p>
        @endif

        <p class="hint">{{ $company ? $company . ' · ' : '' }}{{ config('product.name') }}</p>
    </div>
</main>
</body>
</html>
