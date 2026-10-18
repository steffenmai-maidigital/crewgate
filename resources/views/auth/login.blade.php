@extends('layout')
@section('title', __('Anmelden'))
@section('content')
    <div class="panel login-box" style="max-width:420px;margin:3rem auto;">
        <div style="text-align:center;margin-bottom:.6rem">
            @php($logo = \App\Models\Setting::get('brand_logo_path'))
            <img src="{{ $logo ? asset($logo) : asset('img/crewgate.svg') }}" alt="" width="56" height="56" style="border-radius:14px;box-shadow:0 8px 22px color-mix(in srgb, var(--accent) 35%, transparent);object-fit:contain">
            <div style="font-weight:700;font-size:1.25rem;letter-spacing:.3px;margin-top:.35rem">{{ \App\Models\Setting::get('brand_company_name') ?: 'CrewGate' }}</div>
            <div class="hint" style="margin-top:.15rem">CrewGate · {{ __('Version') }} {{ config('version.number') }}@if(config('test.enabled')) · <span style="color:#c0392b;font-weight:700">TESTSYSTEM</span>@endif</div>
        </div>
        <h1>{{ __('Anmelden') }}</h1>
        <p class="hint">
            @if(config('demo.enabled'))
                {{ __('Melden Sie sich mit einem der Demo-Konten an.') }}
            @elseif($directory)
                {{ __('Melden Sie sich mit Ihrem Firmenkonto (Windows-Anmeldung) oder mit Ihrem CrewGate-Konto an.') }}
            @else
                {{ __('Melden Sie sich mit Ihrem CrewGate-Konto an.') }}
            @endif
        </p>
        @if(config('demo.enabled'))
            <div class="flash ok" style="margin-top:.6rem">
                <strong>{{ __('Demo-Modus aktiv') }}</strong> – {{ __('kein Verzeichnis nötig.') }}<br>
                {{ __('Passwort für alle Konten:') }} <code>demo</code><br><br>
                <code>anna.hr</code> – {{ __('HR (Vorgänge anlegen)') }}<br>
                <code>max.admin</code> – {{ __('Admin (Backend)') }}<br>
                <code>petra.chef</code> – {{ __('Vorgesetzte (Vertrieb)') }}<br>
                <code>tim.it</code>, <code>eva.einkauf</code>, <code>frank.facility</code> – {{ __('Abteilungen') }}<br>
                <code>rita.audit</code> – {{ __('Auditorin (Protokoll & Export)') }}
            </div>
        @endif
        <form method="post" action="{{ route('login.post') }}">
            @csrf
            <label for="username">{{ __('Benutzername') }}</label>
            <input type="text" id="username" name="username" value="{{ old('username') }}" autofocus required
                   autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false">
            <label for="password">{{ __('Passwort') }}</label>
            <input type="password" id="password" name="password" required autocomplete="current-password">
            <button class="primary" style="width:100%">{{ __('Anmelden') }}</button>
        </form>
        @include('partials.legal-links')
    </div>
@endsection
