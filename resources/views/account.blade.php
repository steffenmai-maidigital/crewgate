@extends('layout')
@section('title', __('Mein Konto'))
@section('content')
    <h1>{{ __('Mein Konto') }}</h1>

    @if($mfaPending)
        <div class="flash err">{{ __('Für Ihre Gruppe ist die Zwei-Faktor-Anmeldung vorgeschrieben. Bitte richten Sie sie jetzt ein — vorher ist die Anwendung gesperrt.') }}</div>
    @endif

    <div class="panel">
        <b>{{ $user->name }}</b>
        @if($user->is_admin ?? false) · <span class="badge done">{{ __('Administrator') }}</span>@endif
        <div class="hint">{{ __('Benutzername') }}: <code>{{ $user->username }}</code> · {{ __('Herkunft') }}: {{ $user->origin() }}@if($user->email) · {{ $user->email }}@endif</div>
    </div>

    @if(\App\Models\Setting::get('fox_enabled', '1') === '1')
        <div class="panel">
            <h2 style="margin-top:0">🦊 {{ __('Fuchs-Helfer') }}</h2>
            <form method="post" action="{{ route('account.fox') }}" class="row" style="align-items:center">
                @csrf
                <p style="margin:0;flex:1 1 260px">{{ $user->showsFox() ? __('Der Fuchs unten rechts begrüßt Sie und gibt Tipps.') : __('Der Fuchs macht gerade Pause.') }}</p>
                <input type="hidden" name="fox" value="{{ $user->showsFox() ? 0 : 1 }}">
                <div style="flex:0"><button class="primary small" style="margin:0">{{ $user->showsFox() ? __('Fuchs ausblenden') : __('Fuchs anzeigen') }}</button></div>
            </form>
        </div>
    @endif

    @if($user->isLocal())
        <div class="panel">
            <h2 style="margin-top:0">🔑 {{ __('Passwort ändern') }}</h2>
            <form method="post" action="{{ route('account.password') }}" style="max-width:420px">
                @csrf @method('PUT')
                <label for="current">{{ __('Bisheriges Passwort') }}</label>
                <input type="password" id="current" name="current" required autocomplete="current-password">
                <label for="password">{{ __('Neues Passwort (mindestens 8 Zeichen)') }}</label>
                <input type="password" id="password" name="password" required autocomplete="new-password" minlength="8">
                <label for="password_confirmation">{{ __('Neues Passwort wiederholen') }}</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                <button class="primary">{{ __('Passwort speichern') }}</button>
            </form>
        </div>
    @else
        <div class="panel">
            <h2 style="margin-top:0">🏢 {{ __('Verzeichniskonto') }}</h2>
            <p style="margin:0">{{ __('Sie melden sich mit Ihrem Firmenkonto an. Das Passwort ändern Sie dort, nicht hier.') }}</p>
        </div>
    @endif

    @if($policy !== \App\Support\Mfa::OFF)
    <div class="panel">
        <h2 style="margin-top:0">🔐 {{ __('Zwei-Faktor-Anmeldung') }}</h2>
        @if($codes)
            <div class="flash ok">
                <b>{{ __('Wiederherstellungscodes — jetzt notieren oder ausdrucken!') }}</b><br>
                {{ __('Jeder Code funktioniert genau einmal, falls das Handy fehlt. Sie werden nur jetzt angezeigt.') }}
            </div>
            <div class="codes">@foreach($codes as $c)<div>{{ $c }}</div>@endforeach</div>
        @endif

        @if($user->hasTwoFactor())
            <p><span class="badge done">{{ __('eingeschaltet') }}</span>
                {{ __('seit :datum. Bei jeder Anmeldung wird zusätzlich ein Code aus der App abgefragt.', ['datum' => $user->two_factor_confirmed_at->format('d.m.Y')]) }}
                {{ __('Noch :n Wiederherstellungscodes übrig.', ['n' => count($user->two_factor_recovery_codes ?? [])]) }}</p>
            @php($confirmation = $user->isLocal() ? __('Zur Bestätigung Ihr Passwort') : __('Zur Bestätigung ein aktueller Code aus der App'))
            <div class="row">
                <form method="post" action="{{ route('account.2fa.codes') }}">
                    @csrf
                    <label>{{ __('Neue Wiederherstellungscodes') }} <span class="hint">({{ $confirmation }})</span></label>
                    <div class="row"><div><input type="password" name="password" required autocomplete="{{ $user->isLocal() ? 'current-password' : 'one-time-code' }}"></div><div style="flex:0"><button class="primary small" style="margin-top:.2rem">{{ __('Neu erzeugen') }}</button></div></div>
                </form>
                @if($policy !== \App\Support\Mfa::REQUIRED)
                    <form method="post" action="{{ route('account.2fa.disable') }}">
                        @csrf @method('DELETE')
                        <label>{{ __('Ausschalten') }} <span class="hint">({{ $confirmation }})</span></label>
                        <div class="row"><div><input type="password" name="password" required autocomplete="{{ $user->isLocal() ? 'current-password' : 'one-time-code' }}"></div><div style="flex:0"><button class="primary small danger" style="margin-top:.2rem">{{ __('Ausschalten') }}</button></div></div>
                    </form>
                @else
                    <p class="hint">{{ __('Für Ihre Gruppe ist die Zwei-Faktor-Anmeldung vorgeschrieben und kann nicht ausgeschaltet werden.') }}</p>
                @endif
            </div>
        @elseif($setup)
            <ol>
                <li>{{ __('Authenticator-App öffnen (z. B. Aegis, FreeOTP, Google oder Microsoft Authenticator).') }}</li>
                <li>{{ __('Diesen QR-Code scannen — oder den Schlüssel von Hand eingeben.') }}</li>
                <li>{{ __('Den angezeigten 6-stelligen Code unten eintragen.') }}</li>
            </ol>
            <div class="row" style="align-items:center">
                <div class="qr" style="flex:0 0 auto">{!! $setup['qr'] !!}</div>
                <div>
                    <div class="hint">{{ __('Schlüssel') }}</div>
                    <code style="font-size:1.05rem;word-break:break-all">{{ $setup['secret'] }}</code>
                    <form method="post" action="{{ route('account.2fa.confirm') }}">
                        @csrf
                        <label for="code">{{ __('Code aus der App') }}</label>
                        <div class="row"><div><input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="10" required></div>
                            <div style="flex:0"><button class="primary small" style="margin-top:.2rem">{{ __('Einschalten') }}</button></div></div>
                    </form>
                </div>
            </div>
        @else
            <p>{{ __('Schützt das Konto zusätzlich mit einem Code aus einer Authenticator-App auf dem Handy.') }}
                @if($policy === \App\Support\Mfa::REQUIRED)<b>{{ __('Für Ihre Gruppe ist das vorgeschrieben.') }}</b>@else {{ __('Empfohlen für alle, die Daten pflegen.') }}@endif</p>
            <form method="post" action="{{ route('account.2fa.start') }}">@csrf<button class="primary">{{ __('Zwei-Faktor einrichten') }}</button></form>
        @endif
    </div>
    @endif
@endsection
