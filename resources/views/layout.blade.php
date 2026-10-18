<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CrewGate') · CrewGate</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('img/crewgate.svg') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ config('version.build') }}">
    {{-- Einheitliche Symbole (Twemoji) statt Geräte-Emojis, Standard 03

         Uniform symbols (Twemoji) instead of device emojis, Standard 03 --}}
    <meta name="symbol-base" content="{{ rtrim(asset(''), '/') }}">
    <script src="{{ asset('js/symbols.js') }}?v={{ config('version.build') }}" defer></script>
    @php
        $brand = [
            'name'   => \App\Models\Setting::get('brand_company_name'),
            'logo'   => \App\Models\Setting::get('brand_logo_path'),
            'header' => \App\Models\Setting::get('brand_header_color'),
            'accent' => \App\Models\Setting::get('brand_accent_color'),
            'footerBg' => \App\Models\Setting::get('brand_footer_color'),
            'footerText' => \App\Models\Setting::get('brand_footer_text'),
        ];
    @endphp
    @if($brand['header'] || $brand['accent'] || $brand['footerBg'])
        <style>
            :root, html[data-theme="dark"] {
                @if($brand['accent']) --accent: {{ $brand['accent'] }}; @endif
                @if($brand['header']) --header-bg: {{ $brand['header'] }}; @endif
                @if($brand['footerBg']) --footer-bg: {{ $brand['footerBg'] }}; @endif
            }
        </style>
    @endif
    <script>
        // Hell/Dunkel (Standard 03): gespeicherte Wahl, sonst Systemeinstellung
        // Light/dark (Standard 03): saved choice, otherwise system setting
        (function () {
            var t = null; try { t = localStorage.getItem('theme'); } catch (e) {}
            document.documentElement.dataset.theme = t || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        })();
    </script>
</head>
<body>
@if(config('test.enabled'))
    <div class="test-banner">TESTSYSTEM</div>
@endif
@php($u = session('ldap_user'))
<header class="top">
    <a class="brand" href="{{ $u ? route('dashboard') : route('login') }}">
        @if($brand['logo'])<img src="{{ asset($brand['logo']) }}" alt="">@else<img src="{{ asset('img/crewgate.svg') }}" alt="">@endif
        {{ $brand['name'] ?: 'CrewGate' }}
    </a>
    @if($u)
        <nav aria-label="{{ __('Hauptmenü') }}">
            <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard', 'onboardings.show') ? 'active' : '' }}">{{ __('Übersicht') }}</a>
            @if($u['can_create'] ?? false)
                <a href="{{ route('onboardings.create') }}" class="{{ request()->routeIs('onboardings.create', 'onboardings.import*') ? 'active' : '' }}">{{ __('Neuer Vorgang') }}</a>
            @endif
            @if(($u['is_hr'] ?? false) || ($u['is_admin'] ?? false) || ($u['is_auditor'] ?? false))
                @if(($u['is_hr'] ?? false) || ($u['is_admin'] ?? false))
                    <a href="{{ route('processes.index') }}" class="{{ request()->routeIs('processes.*') ? 'active' : '' }}">{{ __('Prozesse') }}</a>
                @endif
                <a href="{{ route('logs') }}" class="{{ request()->routeIs('logs*') ? 'active' : '' }}">{{ __('Protokoll') }}</a>
            @endif
            @if(($u['is_admin'] ?? false) || ($u['is_hr'] ?? false) || !empty($u['role_ids']))
                <a href="{{ route('admin.fields') }}" class="{{ request()->routeIs('admin.fields*') ? 'active' : '' }}">{{ __('Felder') }}</a>
                <a href="{{ route('admin.products') }}" class="{{ request()->routeIs('admin.products*', 'admin.categories*') ? 'active' : '' }}">{{ __('Produkte') }}</a>
            @endif
            @if($u['is_admin'] ?? false)
                <a href="{{ route('admin.roles') }}" class="{{ request()->routeIs('admin.roles*') ? 'active' : '' }}">{{ __('Rollen') }}</a>
                <a href="{{ route('admin.users') }}" class="{{ request()->routeIs('admin.users*') ? 'active' : '' }}">{{ __('Benutzer') }}</a>
                <a href="{{ route('admin.maillog') }}" class="{{ request()->routeIs('admin.maillog') ? 'active' : '' }}">{{ __('Mail-Log') }}</a>
                <a href="{{ route('admin.settings') }}" class="{{ request()->routeIs('admin.settings*') ? 'active' : '' }}">{{ __('Einstellungen') }}</a>
            @endif
        </nav>
    @endif
    <span class="who">
        <details class="lang-switch">
            <summary>
                <img src="{{ asset(app()->getLocale() === 'de' ? 'img/flag-de.svg' : 'img/flag-gb.svg') }}" alt="">
                {{ app()->getLocale() === 'de' ? 'Deutsch' : 'English' }} ▾
            </summary>
            <div class="lang-menu">
                <a href="{{ route('locale.switch', 'de') }}"><img src="{{ asset('img/flag-de.svg') }}" alt=""> Deutsch</a>
                <a href="{{ route('locale.switch', 'en') }}"><img src="{{ asset('img/flag-gb.svg') }}" alt=""> English</a>
            </div>
        </details>
        <button type="button" class="theme" id="theme-toggle" title="{{ __('Hell/Dunkel umschalten') }}" aria-label="{{ __('Hell/Dunkel umschalten') }}">◐</button>
        @if($u)
            <a class="name" href="{{ route('account') }}" title="{{ __('Mein Konto') }}">{{ $u['name'] ?? '' }}@if(!empty($u['department'])) · {{ $u['department'] }}@endif</a>
            <form method="post" action="{{ route('logout') }}" class="inline-form">@csrf<button>{{ __('Abmelden') }}</button></form>
        @endif
    </span>
</header>
<main>
    @if(session('ok'))<div class="flash ok">{{ session('ok') }}</div>@endif
    @if($errors->any())
        <div class="flash err">
            @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
        </div>
    @endif
    @yield('content')
</main>
@include('partials.footer', ['signedIn' => (bool) $u])
@if(\App\Support\Fox::visible())
    @include('partials.fox', ['foxPrefix' => 'cg-fox'])
@endif
<script>
    document.getElementById('theme-toggle').addEventListener('click', function () {
        const next = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        document.documentElement.dataset.theme = next;
        try { localStorage.setItem('theme', next); } catch (e) {}
    });
    // Aufklappmenüs (Sprache) schließen, wenn man daneben tippt
    // Close drop-down menus (language) when tapping outside them
    document.addEventListener('click', function (e) {
        document.querySelectorAll('details.lang-switch[open]').forEach(function (d) { if (!d.contains(e.target)) d.open = false; });
    });
</script>
</body>
</html>
