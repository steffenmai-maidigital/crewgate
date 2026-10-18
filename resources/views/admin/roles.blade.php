@extends('layout')
@section('title', __('Rollen'))
@section('content')
    <h1>{{ __('Rollen (Abteilungen)') }}</h1>

    <div class="panel">
        @if($directory)
            <form method="post" action="{{ route('admin.roles.sync') }}" class="inline-form">
                @csrf
                <button class="primary small">{{ __('Gruppen aus dem Verzeichnis einlesen') }}</button>
            </form>
            <span class="hint">{{ __('Legt für jede Gruppe unterhalb der Suchbasis eine Rolle an (Filter in den Einstellungen). Neue Rollen sind zunächst nicht im Workflow aktiv.') }}</span>
        @else
            <p class="hint" style="margin-top:0">{{ __('Ohne Verzeichnis legen Sie Rollen unten von Hand an und ordnen eigene Konten unter „Benutzer“ ihren Abteilungen zu.') }}</p>
        @endif
        <p class="hint" style="margin-bottom:0">{{ __('„Sieht Daten von“: Die Mitglieder einer Rolle sehen in Vorgängen immer die eigenen Felder – hier lässt sich zusätzlich freigeben, welche Daten anderer Rollen sie sehen (z. B. IT sieht die Angaben der HR-Anlage). Mehrfachauswahl mit Strg/Cmd. HR, Admins und Vorgesetzte sehen immer alles.') }}</p>
    </div>

    <div class="panel">
        <table class="manage">
            <tr><th>{{ __('Reihenfolge') }}</th><th>{{ __('Name') }}</th><th>{{ __('Gruppe im Verzeichnis (DN oder Name)') }}</th><th>{{ __('Zentrale E-Mail') }}</th><th>{{ __('Sieht Daten von') }}</th><th>{{ __('Im Workflow') }}</th><th></th></tr>
            @foreach($roles as $role)
                <tr>
                    <form method="post" action="{{ route('admin.roles.update', $role) }}">
                        @csrf @method('PUT')
                        <td style="width:90px"><input type="number" name="sort_order" value="{{ $role->sort_order }}" min="0" aria-label="{{ __('Reihenfolge') }}"></td>
                        <td><input type="text" name="name" value="{{ $role->name }}" aria-label="{{ __('Name') }}"></td>
                        <td><input type="text" name="ad_group_dn" value="{{ $role->ad_group_dn }}" placeholder="CN=IT,OU=Gruppen,DC=firma,DC=local" aria-label="{{ __('Gruppe im Verzeichnis (DN oder Name)') }}"></td>
                        <td><input type="email" name="central_email" value="{{ $role->central_email }}" placeholder="it-onboarding@firma.local" aria-label="{{ __('Zentrale E-Mail') }}"></td>
                        <td style="width:170px">
                            <select name="visible_role_ids[]" multiple size="3" title="{{ __('Mehrfachauswahl mit Strg/Cmd. Eigene Felder sieht die Rolle immer.') }}" aria-label="{{ __('Sieht Daten von') }}">
                                @foreach($roles->where('id', '!=', $role->id) as $other)
                                    <option value="{{ $other->id }}" @selected(in_array($other->id, $role->visible_role_ids ?? []))>{{ $other->name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td style="width:70px;text-align:center">
                            @if($role->is_intake)
                                <span class="badge" title="{{ __('Felder erscheinen beim Anlegen eines Vorgangs') }}">{{ __('Anlage') }}</span>
                            @else
                                <input type="checkbox" name="in_workflow" value="1" @checked($role->in_workflow) aria-label="{{ __('Im Workflow') }}">
                            @endif
                        </td>
                        <td style="white-space:nowrap">
                            <button class="primary small">{{ __('Speichern') }}</button>
                    </form>
                    @unless($role->is_intake)
                    <form method="post" action="{{ route('admin.roles.destroy', $role) }}" class="inline-form" onsubmit="return confirm(@js(__('Rolle „:name“ wirklich löschen? Zugehörige Felder werden mitgelöscht.', ['name' => $role->name])))">
                        @csrf @method('DELETE')
                        <button class="primary small danger">{{ __('Löschen') }}</button>
                    </form>
                    @endunless
                        </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Rolle manuell anlegen') }}</h2>
        <form method="post" action="{{ route('admin.roles.store') }}">
            @csrf
            <div class="row">
                <div><label>{{ __('Name') }}</label><input type="text" name="name" required></div>
                <div><label>{{ __('Gruppe im Verzeichnis (DN oder Name)') }}</label><input type="text" name="ad_group_dn"></div>
                <div><label>{{ __('Zentrale E-Mail') }}</label><input type="email" name="central_email"></div>
                <div style="max-width:120px"><label>{{ __('Reihenfolge') }}</label><input type="number" name="sort_order" value="{{ ($roles->max('sort_order') ?? 0) + 1 }}" min="0"></div>
            </div>
            <label class="choice"><input type="checkbox" name="in_workflow" value="1" checked> {{ __('Im Workflow aktiv') }}</label>
            <button class="primary">{{ __('Anlegen') }}</button>
        </form>
    </div>
@endsection
