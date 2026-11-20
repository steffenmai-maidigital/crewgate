{{-- Felder eines eigenen Kontos (anlegen und bearbeiten); $prefix macht die IDs je Formular eindeutig

     Fields of an own (local) account (create and edit); $prefix keeps the IDs unique per form --}}
@php($prefix = $prefix ?? 'user')
<div class="row">
    <div><label for="{{ $prefix }}-username">{{ __('Benutzername') }}</label><input type="text" id="{{ $prefix }}-username" name="username" value="{{ old('username', $user?->username) }}" required autocapitalize="none" spellcheck="false" placeholder="vorname.nachname"></div>
    <div><label for="{{ $prefix }}-name">{{ __('Name') }}</label><input type="text" id="{{ $prefix }}-name" name="name" value="{{ old('name', $user?->name) }}" required placeholder="{{ __('Vorname Nachname') }}"></div>
</div>
<div class="row">
    <div><label for="{{ $prefix }}-email">{{ __('E-Mail') }}</label><input type="email" id="{{ $prefix }}-email" name="email" value="{{ old('email', $user?->email) }}" placeholder="vorname.nachname@firma.local"></div>
    <div><label for="{{ $prefix }}-department">{{ __('Abteilung (als Vorgesetzter)') }}</label><input type="text" id="{{ $prefix }}-department" name="department" value="{{ old('department', $user?->department) }}" placeholder="{{ __('z. B. Vertrieb') }}"></div>
</div>
<span class="group-label" id="{{ $prefix }}-rights-label">{{ __('Rechte') }}</span>
<div class="choice-list" role="group" aria-labelledby="{{ $prefix }}-rights-label">
    <label class="choice"><input type="checkbox" name="is_hr" value="1" @checked(old('is_hr', $user?->is_hr))> {{ __('HR: alle Vorgänge, Protokoll, Vorgänge anlegen') }}</label>
    <label class="choice"><input type="checkbox" name="can_create" value="1" @checked(old('can_create', $user?->can_create))> {{ __('Vorgänge anlegen') }}</label>
    <label class="choice"><input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user?->is_admin))> {{ __('Administrator') }}</label>
    <label class="choice"><input type="checkbox" name="is_auditor" value="1" @checked(old('is_auditor', $user?->is_auditor))> {{ __('Auditor (Protokoll lesen und exportieren)') }}</label>
</div>
<span class="group-label" id="{{ $prefix }}-roles-label">{{ __('Abteilungen (Rollen im Workflow)') }}</span>
<div class="choice-list" role="group" aria-labelledby="{{ $prefix }}-roles-label">
    @foreach($roles as $r)
        <label class="choice"><input type="checkbox" name="roles[]" value="{{ $r->id }}" @checked(in_array($r->id, old('roles', $user?->roles->pluck('id')->all() ?? [])))> {{ $r->displayName() }}</label>
    @endforeach
    @if($roles->isEmpty())<span class="hint">{{ __('Noch keine Rollen angelegt (Menü „Rollen“).') }}</span>@endif
</div>
