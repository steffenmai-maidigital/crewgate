@extends('layout')
@section('title', __('CSV-Import-Assistent'))
@section('content')
    <h1>{{ __('Mehrere Mitarbeiter anlegen – Assistent') }}</h1>

    @php($hasRows = count($rows) > 0)
    @php($finished = !$hasRows && $total > 0)

    {{-- Fortschritt / Progress --}}
    <div class="panel" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
        <span class="badge {{ !$hasRows && !$finished ? 'open' : 'done' }}">1 · {{ __('Vorlage') }}</span>
        <span class="badge {{ $hasRows || $finished ? 'done' : 'draft' }}">2 · {{ __('Hochladen') }}</span>
        <span class="badge {{ $finished ? 'done' : ($hasRows ? 'open' : 'draft') }}">3 · {{ __('Zeilen abarbeiten') }}</span>
        @if($total > 0)
            <span class="hint" style="margin-left:auto">{{ __(':a von :b angelegt', ['a' => $created, 'b' => $total]) }}@if($hasRows) · {{ __(':n offen', ['n' => count($rows)]) }}@endif</span>
        @endif
    </div>

    @if(!$hasRows && !$finished)
        {{-- ===== Schritt 1: Vorlage & Anleitung / Step 1: template & instructions ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Schritt 1: Aktuelle Vorlage herunterladen') }}</h2>
            <p class="hint" style="margin-top:0">{{ __('Bitte verwenden Sie immer die frisch heruntergeladene Vorlage – sie enthält die aktuellen Spalten und Beispiele passend zu Ihren Prozessarten.') }}</p>
            <p><a class="primary small" style="text-decoration:none;padding:.45rem .9rem;border-radius:10px" href="{{ route('onboardings.import.template') }}">{{ __('Vorlage herunterladen (CSV)') }}</a></p>
            <table class="manage">
                <tr><th style="width:190px">{{ __('Spalte') }}</th><th>{{ __('Bedeutung / Format') }}</th><th style="width:80px">{{ __('Pflicht') }}</th></tr>
                <tr><td><code>vorname</code> / <code>nachname</code></td><td>{{ __('Name des Mitarbeiters') }}</td><td>{{ __('ja') }}</td></tr>
                <tr><td><code>prozess</code></td><td>{{ __('Schlüssel oder Name der Prozessart – verfügbar:') }} {{ $processes->map(fn($p) => $p->displayName() . ' (' . $p->key . ')')->implode(', ') }}. {{ __('Leer = Einstellung; in Schritt 3 änderbar.') }}</td><td>{{ __('nein') }}</td></tr>
                <tr><td><code>eintrittsdatum</code></td><td>{{ __('TT.MM.JJJJ oder JJJJ-MM-TT (je nach Prozess: Eintritt, Wechsel, Austritt, Gültig ab)') }}</td><td>{{ __('nein') }}</td></tr>
                <tr><td><code>vorgesetzter</code></td><td>{{ __('AD-Anmeldename; kann leer bleiben und wird in Schritt 3 über die AD-Suche gewählt') }}</td><td>{{ __('nein') }}</td></tr>
                <tr><td><code>frist_vorgesetzter</code> / <code>frist_abteilungen</code></td><td>{{ __('Erledigungsdaten für die Erinnerungen (optional)') }}</td><td>{{ __('nein') }}</td></tr>
                <tr><td><code>notizen</code></td><td>{{ __('Freitext für HR und Vorgesetzte') }}</td><td>{{ __('nein') }}</td></tr>
            </table>
            <p class="hint">{{ __('Trennzeichen ; oder , werden erkannt. Eine Zeile je Person. In Schritt 3 prüfen Sie jede Zeile, korrigieren bei Bedarf und legen die Vorgänge einzeln an – nichts wird ungeprüft angelegt.') }}</p>
        </div>

        {{-- ===== Schritt 2: Upload / Step 2: upload ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Schritt 2: Ausgefüllte CSV hochladen') }}</h2>
            <form method="post" action="{{ route('onboardings.import.upload') }}" enctype="multipart/form-data">
                @csrf
                <div class="row" style="align-items:end">
                    <div><label>{{ __('CSV-Datei') }}</label><input type="file" name="csv" accept=".csv,text/csv" required></div>
                    <div style="max-width:220px"><button class="primary">{{ __('Einlesen & prüfen') }}</button></div>
                </div>
            </form>
        </div>
    @endif

    @if($finished)
        <div class="panel" style="border-left:4px solid #2e8b57">
            <h2 style="margin-top:0">{{ __('Fertig!') }}</h2>
            <p>{{ __('Alle Zeilen sind abgearbeitet: :n Vorgänge wurden angelegt, die Vorgesetzten wurden benachrichtigt und ergänzen jetzt die Angaben.', ['n' => $created]) }}</p>
            <div style="display:flex;gap:.5rem;flex-wrap:wrap">
                <a class="primary small" style="text-decoration:none;padding:.45rem .9rem;border-radius:10px" href="{{ route('dashboard') }}">{{ __('Zur Übersicht') }}</a>
                <form method="post" action="{{ route('onboardings.import.reset') }}">@csrf<button class="primary small">{{ __('Weitere Datei importieren') }}</button></form>
            </div>
        </div>
    @endif

    @if($hasRows)
        {{-- ===== Schritt 3: Zeilen abarbeiten / Step 3: work through the rows ===== --}}
        <div class="panel" style="display:flex;gap:.6rem;align-items:center;flex-wrap:wrap">
            <strong>{{ __('Schritt 3: Zeilen prüfen und Vorgänge anlegen') }}</strong>
            <span class="hint">{{ __('Je Zeile: Angaben korrigieren, Vorgesetzten über „Im AD suchen" bestätigen, dann „Vorgang anlegen".') }}</span>
            <form method="post" action="{{ route('onboardings.import.reset') }}" style="margin-left:auto" onsubmit="return confirm('{{ __('Import abbrechen? Noch nicht angelegte Zeilen gehen verloren.') }}')">
                @csrf<button class="primary small danger">{{ __('Import abbrechen') }}</button>
            </form>
        </div>

        @foreach($rows as $key => $row)
            <div class="panel">
                <div style="display:flex;gap:.6rem;align-items:baseline;flex-wrap:wrap">
                    <h2 style="margin:0">{{ __('Zeile :nr', ['nr' => $row['line']]) }}: {{ $row['first_name'] ?: '–' }} {{ $row['last_name'] ?: '' }}</h2>
                </div>
                @if(!empty($row['hints']))
                    <div style="border-left:4px solid #c0392b;padding:.4rem .7rem;margin:.5rem 0;background:rgba(192,57,43,.06);border-radius:8px">
                        @foreach($row['hints'] as $hint)<div>{{ $hint }}</div>@endforeach
                    </div>
                @endif
                <form method="post" action="{{ route('onboardings.import.row') }}" id="row-form-{{ $key }}">
                    @csrf
                    <input type="hidden" name="row_key" value="{{ $key }}">
                    <div class="row">
                        <div><label>{{ __('Vorname') }}</label><input type="text" name="first_name" value="{{ $row['first_name'] }}" required></div>
                        <div><label>{{ __('Nachname') }}</label><input type="text" name="last_name" value="{{ $row['last_name'] }}" required></div>
                        <div style="max-width:240px">
                            <label>{{ __('Prozess') }}</label>
                            <select name="process" required>
                                @if(!$row['process'])<option value="" selected disabled>{{ __('bitte wählen') }}</option>@endif
                                @foreach($processes as $p)
                                    <option value="{{ $p->key }}" @selected($row['process'] === $p->key)>{{ $p->displayName() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div><label>{{ __('Datum') }}</label><input type="date" name="start_date" value="{{ $row['start_date'] }}"></div>
                    </div>
                    <div class="row" style="align-items:end">
                        <div>
                            <label>{{ __('Vorgesetzter (AD-Benutzername oder Name)') }}</label>
                            <input type="text" name="supervisor_username" class="js-supervisor" value="{{ $row['supervisor_username'] }}" required>
                        </div>
                        <div style="flex:0"><button type="button" class="primary small js-lookup" style="margin-top:0">{{ __('Im AD suchen') }}</button></div>
                    </div>
                    <p class="hint js-lookup-result" style="min-height:1.1em"></p>
                    <div class="row">
                        <div><label>{{ __('Erledigen bis: Vorgesetzter (optional)') }}</label><input type="date" name="supervisor_due_date" value="{{ $row['supervisor_due_date'] }}"></div>
                        <div><label>{{ __('Erledigen bis: Abteilungen (optional)') }}</label><input type="date" name="roles_due_date" value="{{ $row['roles_due_date'] }}"></div>
                        <div><label>{{ __('Notizen (optional)') }}</label><input type="text" name="notes" value="{{ $row['notes'] }}"></div>
                    </div>
                </form>
                <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin-top:.2rem">
                    <button class="primary" form="row-form-{{ $key }}">{{ __('Vorgang anlegen') }}</button>
                    <form method="post" action="{{ route('onboardings.import.discard') }}" class="inline-form" onsubmit="return confirm('{{ __('Diese Zeile verwerfen?') }}')">
                        @csrf
                        <input type="hidden" name="row_key" value="{{ $key }}">
                        <button class="primary small danger">{{ __('Zeile verwerfen') }}</button>
                    </form>
                </div>
            </div>
        @endforeach

        <script>
            // AD-Suche je Zeile (gleicher Endpunkt wie bei der Einzelanlage)
            // AD search per row (same endpoint as for creating a single case)
            document.querySelectorAll('.js-lookup').forEach(function (btn) {
                btn.addEventListener('click', async function () {
                    const form = btn.closest('form');
                    const input = form.querySelector('.js-supervisor');
                    const out = form.parentElement.querySelector('.js-lookup-result');
                    const q = input.value.trim();
                    if (q.length < 2) { out.textContent = '{{ __('Bitte mindestens 2 Zeichen eingeben.') }}'; return; }
                    out.textContent = '{{ __('Suche im AD …') }}';
                    try {
                        const res = await fetch('{{ route('ad.supervisor') }}?q=' + encodeURIComponent(q), { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        if (!res.ok) { out.textContent = data.error || '{{ __('Fehler bei der AD-Suche.') }}'; return; }
                        out.textContent = '{{ __('Gefunden:') }} ' + (data.name || data.username) + ' – {{ __('Abteilung:') }} ' + (data.department || '{{ __('nicht gepflegt') }}');
                        if (data.username) input.value = data.username;
                    } catch (e) {
                        out.textContent = '{{ __('AD-Suche nicht erreichbar.') }}';
                    }
                });
            });
        </script>
    @endif
@endsection
