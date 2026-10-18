@extends('layout')
@section('title', __('Felder'))
@section('content')
    <h1>{{ __('Auswahl- und Freitextfelder') }}</h1>

    <div class="panel">
        <form method="get" action="{{ route('admin.fields') }}">
            <label for="role">{{ __('Rolle (Abteilung)') }}</label>
            <div class="row">
                <div>
                    <select id="role" name="role" onchange="this.form.submit()">
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" @selected($role && $role->id === $r->id)>{{ $r->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </form>
    </div>

    @if($role)
        <div class="panel">
            <h2 style="margin-top:0">Felder für „{{ $role->name }}“</h2>
            <table class="manage">
                <tr><th>{{ __('Reihenfolge') }}</th><th>{{ __('Bezeichnung') }}</th><th>{{ __('Typ') }}</th><th>{{ __('Optionen / Regex') }}</th><th>{{ __('Pflicht') }}</th><th>{{ __('Aktiv') }}</th><th></th></tr>
                @forelse($fields as $field)
                    <tr>
                        <form method="post" action="{{ route('admin.fields.update', $field) }}">
                            @csrf @method('PUT')
                            <input type="hidden" name="role_id" value="{{ $role->id }}">
                            <input type="hidden" name="type" value="{{ $field->type }}">
                            <td style="width:80px"><input type="number" name="sort_order" value="{{ $field->sort_order }}" min="0"></td>
                            <td>
                                <input type="text" name="label" value="{{ $field->label }}">
                                <input type="text" name="label_en" value="{{ $field->label_en }}" placeholder="{{ __('Bezeichnung (EN, optional)') }}" style="margin-top:.3rem">
                                <div class="proc-checks">
                                    @foreach(\App\Models\Process::orderBy('sort_order')->get() as $proc)
                                        <label class="choice"><input type="checkbox" name="processes[]" value="{{ $proc->key }}" @checked(in_array($proc->key, $field->processes ?? array_keys(\App\Models\Onboarding::PROCESSES)))> {{ $proc->displayName() }}</label>
                                    @endforeach
                                </div>
                                @if($role->is_intake)
                                    <select name="fill_stage" style="margin-top:.3rem">
                                        <option value="intake" @selected(($field->fill_stage ?? 'intake') === 'intake')>{{ __('Ausfüllen: HR bei der Anlage') }}</option>
                                        <option value="preparation" @selected($field->fill_stage === 'preparation')>{{ __('Ausfüllen: Vorgesetzter in der Vorbereitung') }}</option>
                                    </select>
                                    <label class="choice" style="margin-top:.3rem"><input type="checkbox" name="visible_to_all" value="1" @checked($field->visible_to_all)> {{ __('Für alle Abteilungen sichtbar') }}</label>
                                @endif
                            </td>
                            <td>{{ __(\App\Models\CustomField::TYPES[$field->type] ?? $field->type) }}</td>
                            <td>
                                @if(in_array($field->type, ['select', 'multiselect']))
                                    <textarea name="options_raw" rows="2" placeholder="eine Option pro Zeile">{{ implode("\n", $field->options ?? []) }}</textarea>
                                @elseif($field->type === 'text')
                                    <input type="text" name="regex" value="{{ $field->regex }}" placeholder="^[A-Z]{2}\d{4}$">
                                    <input type="text" name="regex_hint" value="{{ $field->regex_hint }}" placeholder="{{ __('Hinweistext bei Fehleingabe') }}">
                                @elseif($field->type === 'product')
                                    <span class="hint">Produkte im Reiter „Produkte" pflegen</span>
                                @else
                                    <span class="hint">–</span>
                                @endif
                            </td>
                            <td style="text-align:center"><input type="checkbox" name="required" value="1" @checked($field->required)></td>
                            <td style="text-align:center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($field->active)></td>
                            <td style="white-space:nowrap">
                                <button class="primary small">{{ __('Speichern') }}</button>
                        </form>
                        <form method="post" action="{{ route('admin.fields.destroy', $field) }}" class="inline-form" onsubmit="return confirm('Feld wirklich löschen?')">
                            @csrf @method('DELETE')
                            <button class="primary small danger">{{ __('Löschen') }}</button>
                        </form>
                            </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="hint">Für diese Rolle sind noch keine Felder angelegt.</td></tr>
                @endforelse
            </table>
        </div>

        <div class="panel">
            <h2 style="margin-top:0">Neues Feld für „{{ $role->name }}“</h2>
            <form method="post" action="{{ route('admin.fields.store') }}">
                @csrf
                <input type="hidden" name="role_id" value="{{ $role->id }}">
                <div class="row">
                    <div><label>{{ __('Bezeichnung') }}</label><input type="text" name="label" required></div>
                <div><label>{{ __('Bezeichnung (EN, optional)') }}</label><input type="text" name="label_en" placeholder="Desk selection"></div>
                <div>
                    <label>{{ __('Prozesse') }}</label>
                    <div class="proc-checks">
                        @foreach(\App\Models\Process::orderBy('sort_order')->get() as $proc)
                            <label class="choice"><input type="checkbox" name="processes[]" value="{{ $proc->key }}" @checked($proc->is_system)> {{ $proc->displayName() }}</label>
                        @endforeach
                    </div>
                </div>
                @if($role->is_intake)
                    <div>
                        <label>{{ __('Ausfüllen bei') }}</label>
                        <select name="fill_stage">
                            <option value="intake">{{ __('Anlage durch HR') }}</option>
                            <option value="preparation">{{ __('Vorbereitung durch den Vorgesetzten') }}</option>
                        </select>
                    </div>
                    <div><label>&nbsp;</label><label class="choice"><input type="checkbox" name="visible_to_all" value="1"> {{ __('Für alle Abteilungen sichtbar') }}</label></div>
                @endif
                    <div style="max-width:180px">
                        <label>{{ __('Typ') }}</label>
                        <select name="type" id="new-type">
                            @foreach(\App\Models\CustomField::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div style="max-width:120px"><label>{{ __('Reihenfolge') }}</label><input type="number" name="sort_order" value="{{ ($fields->max('sort_order') ?? 0) + 1 }}" min="0"></div>
                </div>
                <div id="new-select">
                    <label>{{ __('Optionen (eine pro Zeile)') }}</label>
                    <textarea name="options_raw" rows="3" placeholder="Notebook&#10;Desktop&#10;Thin Client"></textarea>
                </div>
                <p class="hint" id="new-product-hint" style="display:none">Die auswählbaren Produkte werden im Reiter „Produkte" gepflegt (inkl. Preis, Bild, Artikel- und Herstellernummer).</p>
                <div id="new-text" style="display:none">
                    <label>{{ __('Regulärer Ausdruck (optional, ohne Delimiter)') }}</label>
                    <input type="text" name="regex" placeholder="^\+49[0-9 ]{6,}$">
                    <label>{{ __('Hinweistext bei Fehleingabe') }}</label>
                    <input type="text" name="regex_hint" placeholder="Bitte Telefonnummer im Format +49 … eingeben">
                </div>
                <label><input type="checkbox" name="required" value="1"> Pflichtfeld</label>
                <label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> Aktiv</label>
                <button class="primary">{{ __('Feld anlegen') }}</button>
            </form>
        </div>

        <script>
            document.getElementById('new-type').addEventListener('change', function () {
                document.getElementById('new-select').style.display = (this.value === 'select' || this.value === 'multiselect') ? '' : 'none';
                document.getElementById('new-text').style.display = this.value === 'text' ? '' : 'none';
                document.getElementById('new-product-hint').style.display = this.value === 'product' ? '' : 'none';
            });
        </script>
    @endif
@endsection
