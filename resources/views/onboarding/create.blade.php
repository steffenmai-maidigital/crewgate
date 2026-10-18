@extends('layout')
@section('title', __('Neuen Vorgang anlegen'))
@section('content')
    <h1>{{ __('Neuen Vorgang anlegen') }}</h1>
    <div class="panel">
        <form method="post" action="{{ route('onboardings.store') }}">
            @csrf
            <div class="row">
                <div style="max-width:260px">
                    <label for="process">{{ __('Prozess') }}</label>
                    <select id="process" name="process">
                        @foreach($processes as $p)
                            <option value="{{ $p->key }}" @selected(old('process', 'onboarding') === $p->key)>{{ $p->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label for="first_name">{{ __('Vorname') }}</label>
                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}" required>
                </div>
                <div>
                    <label for="last_name">{{ __('Nachname') }}</label>
                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}" required>
                </div>
                <div>
                    <label for="start_date" data-datelabel data-onboarding="{{ __('Eintrittsdatum') }}" data-job-change="{{ __('Wechseldatum') }}" data-exit="{{ __('Austrittsdatum') }}" data-master-data-change="{{ __('Gültig ab') }}" data-default="{{ __('Datum') }}">{{ __('Eintrittsdatum') }}</label>
                    <input type="date" id="start_date" name="start_date" value="{{ old('start_date') }}">
                </div>
            </div>

            <label for="supervisor_username">{{ __('Vorgesetzter (AD-Benutzername oder Name)') }}</label>
            <div class="row">
                <div><input type="text" id="supervisor_username" name="supervisor_username" value="{{ old('supervisor_username') }}" required></div>
                <div style="flex:0"><button type="button" class="primary small" id="btn-lookup" style="margin-top:0">{{ __('Im AD suchen') }}</button></div>
            </div>
            <p class="hint" id="lookup-result">Die Abteilung des Vorgesetzten wird aus dem Active Directory gelesen – dort wird der neue Mitarbeiter arbeiten.</p>

            @if($intakeFields->isNotEmpty())
                <h2>{{ __('Angaben zur Stelle') }}</h2>
                @foreach($intakeFields as $field)
                    <div data-processes="{{ implode(',', $field->processes ?? array_keys(\App\Models\Onboarding::PROCESSES)) }}">
                        @include('partials.field-input', ['field' => $field])
                    </div>
                @endforeach
            @endif

            <div class="row">
                <div><label>{{ __('Erledigen bis: Vorgesetzter (optional)') }}</label><input type="date" name="supervisor_due_date" value="{{ old('supervisor_due_date') }}"></div>
                <div><label>{{ __('Erledigen bis: Abteilungen (optional)') }}</label><input type="date" name="roles_due_date" value="{{ old('roles_due_date') }}"></div>
            </div>
            <p class="hint">{{ __('Bei Erreichen der Frist geht automatisch eine Erinnerung mit den offenen Aufgaben an den Vorgesetzten. Erinnerungen an die Abteilungen lassen sich in den Einstellungen aktivieren.') }}</p>

            <label for="notes">{{ __('Notizen (optional)') }}</label>
            <textarea id="notes" name="notes" rows="3">{{ old('notes') }}</textarea>

            <button class="primary">{{ __('Anlegen') }}</button>

            <script>
                // Felder je gewählter Prozessart ein-/ausblenden;
                // ausgeblendete Eingaben werden deaktiviert (nicht gesendet).
                // Show/hide fields depending on the selected process type;
                // hidden inputs are disabled (not submitted).
                (function () {
                    const sel = document.getElementById('process');
                    const dateLabel = document.querySelector('[data-datelabel]');
                    function apply() {
                        document.querySelectorAll('[data-processes]').forEach(function (el) {
                            const ok = el.dataset.processes.split(',').includes(sel.value);
                            el.style.display = ok ? '' : 'none';
                            el.querySelectorAll('input, select, textarea').forEach(function (i) { i.disabled = !ok; });
                        });
                        if (dateLabel) dateLabel.textContent = dateLabel.getAttribute('data-' + sel.value.replace(/_/g, '-')) || dateLabel.getAttribute('data-default') || dateLabel.getAttribute('data-onboarding');
                    }
                    sel.addEventListener('change', apply);
                    apply();
                })();
            </script>
        </form>
    </div>

    <script>
        document.getElementById('btn-lookup').addEventListener('click', async () => {
            const q = document.getElementById('supervisor_username').value.trim();
            const out = document.getElementById('lookup-result');
            if (q.length < 2) { out.textContent = 'Bitte mindestens 2 Zeichen eingeben.'; return; }
            out.textContent = 'Suche im AD …';
            try {
                const res = await fetch('{{ route('ad.supervisor') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                if (!res.ok) { out.textContent = data.error || 'Fehler bei der AD-Suche.'; return; }
                out.textContent = 'Gefunden: ' + (data.name || data.username) + ' – Abteilung: ' + (data.department || 'nicht gepflegt');
                if (data.username) document.getElementById('supervisor_username').value = data.username;
            } catch (e) {
                out.textContent = 'AD-Suche nicht erreichbar.';
            }
        });
    </script>

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Mehrere Mitarbeiter per CSV anlegen') }}</h2>
        <p class="hint" style="margin-top:0">{{ __('Für Sammel-Anlagen (z. B. Ausbildungsjahrgang) gibt es einen geführten Assistenten: Vorlage herunterladen, ausfüllen, hochladen – anschließend prüfen Sie jede Zeile, wählen den Vorgesetzten aus dem AD und legen die Vorgänge einzeln an.') }}</p>
        <a class="primary small" style="text-decoration:none;padding:.45rem .9rem;border-radius:10px;display:inline-block" href="{{ route('onboardings.import') }}">{{ __('Import-Assistenten öffnen') }}</a>
    </div>
@endsection
