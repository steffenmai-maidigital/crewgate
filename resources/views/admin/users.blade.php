@extends('layout')
@section('title', __('Benutzer'))
@section('content')
    <h1>{{ __('Benutzer') }}</h1>

    @if($credentials)
        <div class="panel" style="border-left:4px solid var(--ok-ink)">
            <h2 style="margin-top:0">{{ __('Zugangsdaten') }}</h2>
            <p>{{ __('Das Passwort wird nur jetzt angezeigt. Bitte sicher weitergeben.') }}</p>
            <p style="font-size:1.1rem"><code>{{ $credentials['username'] }}</code> · <code>{{ $credentials['password'] }}</code></p>
        </div>
    @endif

    <div class="panel">
        <form method="get" class="search-bar">
            <div><label for="search">{{ __('Suche') }}</label><input type="search" id="search" name="search" value="{{ request('search') }}" placeholder="{{ __('Name, Benutzername oder E-Mail') }}"></div>
            <div><button class="primary">{{ __('Filtern') }}</button></div>
        </form>
        <p class="hint">
            {{ __('Eigene Konten melden sich mit Benutzername und Passwort an; Rechte und Abteilungen vergeben Sie hier.') }}
            @if($directory)
                {{ __('Verzeichniskonten entstehen bei der ersten Anmeldung; ihre Rechte kommen aus den Gruppen (Einstellungen → Berechtigungen).') }}
            @else
                {{ __('Ein Verzeichnis (Active Directory / LDAP) lässt sich unter Einstellungen → Anmeldung einschalten.') }}
            @endif
        </p>
        <table class="manage">
            <tr><th>{{ __('Name') }}</th><th>{{ __('Benutzername') }}</th><th>{{ __('Herkunft') }}</th><th>{{ __('Rechte') }}</th><th>{{ __('Abteilungen') }}</th><th>{{ __('Zuletzt angemeldet') }}</th><th></th></tr>
            @forelse($users as $u)
                <tr @if(! $u->active) style="opacity:.55" @endif>
                    <td><b>{{ $u->name }}</b>@if($u->department) <span class="hint">· {{ $u->department }}</span>@endif
                        @if($u->email)<br><span class="hint">{{ $u->email }}</span>@endif</td>
                    <td><code>{{ $u->username }}</code></td>
                    <td><span class="badge {{ $u->isLocal() ? '' : 'process' }}">{{ $u->origin() }}</span>
                        @if($u->hasTwoFactor()) <span class="badge done" title="{{ __('Zwei-Faktor eingerichtet') }}">2FA</span>@elseif(\App\Support\Mfa::isRequired($u)) <span class="badge open" title="{{ __('Zwei-Faktor ist Pflicht, aber noch nicht eingerichtet') }}">2FA !</span>@endif
                        @unless($u->active) <span class="badge cancelled">{{ __('gesperrt') }}</span>@endunless</td>
                    <td>
                        @if($u->is_admin)<span class="badge done">{{ __('Admin') }}</span> @endif
                        @if($u->is_hr)<span class="badge done">{{ __('HR') }}</span> @endif
                        @if($u->can_create && ! $u->is_hr)<span class="badge open">{{ __('Vorgänge anlegen') }}</span> @endif
                        @if($u->is_auditor)<span class="badge">{{ __('Auditor') }}</span> @endif
                    </td>
                    <td>{{ $u->roles->pluck('name')->join(', ') ?: '–' }}</td>
                    <td class="hint">{{ $u->last_login_at?->format('d.m.Y H:i') ?? '–' }}</td>
                    <td style="white-space:nowrap">
                        <a class="btn" style="padding:.28rem .8rem;font-size:.82rem" href="{{ route('admin.users', ['edit' => $u->id]) }}#edit">{{ __('Bearbeiten') }}</a>
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="hint">{{ __('Noch keine Konten.') }}</td></tr>
            @endforelse
        </table>
    </div>

    @if($editing)
        @php($u = $editing)
        <div class="panel" id="edit" style="border-left:4px solid var(--accent)">
            <h2 style="margin-top:0">{{ __('Konto bearbeiten') }}: {{ $u->name }} <span class="badge">{{ $u->origin() }}</span></h2>
            <form method="post" action="{{ route('admin.users.update', $u) }}">
                @csrf @method('PUT')
                @if($u->isLocal())
                    @include('admin.partials.user-fields', ['user' => $u, 'roles' => $roles])
                    <label>{{ __('Neues Passwort (leer = unverändert, mindestens 8 Zeichen)') }}</label>
                    <input type="text" name="password" value="" autocomplete="off">
                @else
                    <p class="hint">{{ __('Name, E-Mail, Abteilung, Rechte und Rollen dieses Kontos kommen bei jeder Anmeldung aus dem Verzeichnis und lassen sich hier nicht ändern.') }}</p>
                    <input type="hidden" name="username" value="{{ $u->username }}"><input type="hidden" name="name" value="{{ $u->name }}">
                @endif
                @if($u->id !== (int) (session('ldap_user')['id'] ?? 0))
                    <label class="choice"><input type="checkbox" name="active" value="1" @checked($u->active)> {{ __('Konto aktiv (ohne Haken: gesperrt, keine Anmeldung möglich)') }}</label>
                @else
                    <input type="hidden" name="active" value="1">
                @endif
                <div class="row">
                    <div style="max-width:320px">
                        <label>{{ __('Zwei-Faktor-Anmeldung (Ausnahme für dieses Konto)') }}</label>
                        <select name="mfa_policy">
                            <option value="" @selected(! $u->mfa_policy)>{{ __('wie Gruppe') }} ({{ \App\Support\Mfa::label(\App\Support\Mfa::policy($u->replicate(['mfa_policy']))) }})</option>
                            @foreach(\App\Support\Mfa::LEVELS as $level => $levelLabel)
                                <option value="{{ $level }}" @selected($u->mfa_policy === $level)>{{ __($levelLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="row">
                    <button class="primary">{{ __('Speichern') }}</button>
                    <a class="btn" style="margin-top:.9rem;background:var(--chip);color:var(--ink) !important" href="{{ route('admin.users') }}">{{ __('Abbrechen') }}</a>
                </div>
            </form>
            <div class="row" style="margin-top:.8rem;gap:.6rem">
                @if($u->isLocal())
                    <form method="post" action="{{ route('admin.users.password', $u) }}" class="inline-form" onsubmit="return confirm(@js(__('Neues Startpasswort für :name vergeben?', ['name' => $u->name])))">
                        @csrf <button class="primary small">{{ __('Passwort zurücksetzen') }}</button>
                    </form>
                @endif
                @if($u->hasTwoFactor())
                    <form method="post" action="{{ route('admin.users.2fa', $u) }}" class="inline-form" onsubmit="return confirm(@js(__('Zwei-Faktor für :name zurücksetzen (z. B. Handy verloren)?', ['name' => $u->name])))">
                        @csrf @method('DELETE') <button class="primary small">{{ __('Zwei-Faktor zurücksetzen') }}</button>
                    </form>
                @endif
                @if($u->id !== (int) (session('ldap_user')['id'] ?? 0))
                    <form method="post" action="{{ route('admin.users.destroy', $u) }}" class="inline-form" onsubmit="return confirm(@js(__('Konto :name wirklich löschen?', ['name' => $u->name])))">
                        @csrf @method('DELETE') <button class="primary small danger">{{ __('Löschen') }}</button>
                    </form>
                @endif
            </div>
        </div>
    @endif

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Eigenes Konto anlegen') }}</h2>
        <form method="post" action="{{ route('admin.users.store') }}">
            @csrf
            @include('admin.partials.user-fields', ['user' => null, 'roles' => $roles])
            <label>{{ __('Passwort (leer = Startpasswort wird erzeugt und einmal angezeigt)') }}</label>
            <input type="text" name="password" value="" autocomplete="off" minlength="8">
            <button class="primary">{{ __('Anlegen') }}</button>
        </form>
    </div>
@endsection
