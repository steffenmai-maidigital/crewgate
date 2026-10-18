<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<style>
    body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #1d2733; margin: 0; }
    .head { background: {{ $headerColor }}; color: #fff; padding: 14px 20px; }
    .head h1 { margin: 0; font-size: 16px; }
    .head .sub { font-size: 9px; color: #ffffffcc; margin-top: 3px; }
    .head img { height: 30px; margin-bottom: 6px; }
    .meta { padding: 10px 20px; background: #f0f2f5; font-size: 9px; }
    .content { padding: 12px 20px; }
    h2 { font-size: 12px; margin: 16px 0 6px; border-bottom: 1px solid #d5dae1; padding-bottom: 3px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
    th, td { text-align: left; padding: 3px 5px; border-bottom: 0.5px solid #d5dae1; vertical-align: top; word-wrap: break-word; }
    th { background: #eef1f4; font-size: 8px; text-transform: uppercase; }
    .hint { color: #64748b; font-size: 8px; }
    .hash { font-family: "DejaVu Sans Mono", monospace; font-size: 6.5px; color: #64748b; }
    .foot { padding: 8px 20px; color: #64748b; font-size: 8px; border-top: 1px solid #d5dae1; margin-top: 14px; }
</style>
</head>
<body>
    <div class="head">
        @if($logo)<img src="{{ $logo }}" alt="Logo"><br>@endif
        <h1>{{ $company }} – Audit-Bericht / Audit Report</h1>
    @if(config('test.enabled'))
        <div style="border:2.5px solid #c0392b;color:#c0392b;font-weight:800;text-align:center;letter-spacing:.3em;padding:5px 0;margin:6px 0 10px">TESTSYSTEM</div>
    @endif
        <div class="sub">CrewGate – Protokoll des HR-Prozessportals (revisionssicher, append-only, SHA-256-Hash-Kette) · CrewGate – audit log of the HR process portal (tamper-evident, append-only, SHA-256 hash chain)</div>
    </div>
    <div class="meta">
        <strong>Export-Zeitstempel / Export timestamp:</strong> {{ $generatedAt->format('d.m.Y H:i:s') }} Uhr &nbsp;·&nbsp;
        <strong>Exportiert durch / Exported by:</strong> {{ $exportedBy }} &nbsp;·&nbsp;
        <strong>Automatische Löschung / Automatic deletion:</strong> {{ $retentionEnabled ? 'aktiv, nach ' . $retentionMonths . ' Monat(en)' : 'deaktiviert' }} &nbsp;·&nbsp;
        <strong>Protokolleinträge gesamt / Total log entries:</strong> {{ $logs->count() }}
    </div>
    <div class="content">

        <h2>1. Löschungen / Deletions ({{ $deletions->count() }})</h2>
        @if($deletions->isEmpty())
            <p class="hint">Es wurden bisher keine Vorgänge gelöscht.</p>
        @else
            <table>
                <tr><th style="width:90px">Gelöscht am / Deleted at</th><th>Mitarbeiter / Employee</th><th>Details</th></tr>
                @foreach($deletions as $d)
                    <tr>
                        <td>{{ $d->created_at->format('d.m.Y H:i') }}</td>
                        <td>{{ $d->onboarding_name }}</td>
                        <td>@foreach($d->details ?? [] as $k => $v){{ $k }}: {{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}@if(!$loop->last) · @endif @endforeach</td>
                    </tr>
                @endforeach
            </table>
        @endif

        <h2>2. Zuletzt angelegte Vorgänge / Recently created cases ({{ $recent->count() }})</h2>
        @if($recent->isEmpty())
            <p class="hint">Keine Vorgänge vorhanden.</p>
        @else
            <table>
                @php
                    // Zweisprachige Labels für alle Status und Prozesse
                    // Bilingual labels for all statuses and processes
                    $statusMap = [
                        'draft' => 'in Vorbereitung / in preparation',
                        'open' => 'in Bearbeitung / in progress',
                        'review' => 'wartet auf HR-Abschluss / awaiting HR closure',
                        'done' => 'abgeschlossen / completed',
                        'cancelled' => 'abgebrochen / cancelled',
                    ];
                    $processMap = \App\Models\Process::all()
                        ->mapWithKeys(fn ($p) => [$p->key => $p->bilingualName()])->all();
                @endphp
                <tr><th>Mitarbeiter / Employee</th><th style="width:88px">Prozess / Process</th><th>Abteilung / Department</th><th style="width:56px">Datum / Date</th><th style="width:92px">Status</th><th style="width:66px">Angelegt / Created</th><th>Quittierungen / Acknowledgements (wer / wann · who / when)</th></tr>
                @foreach($recent as $o)
                    <tr>
                        <td>{{ $o->fullName() }}</td>
                        <td>{{ $processMap[$o->process ?? 'onboarding'] ?? $o->process }}</td>
                        <td>{{ $o->department ?? '–' }}</td>
                        <td>{{ $o->start_date?->format('d.m.Y') ?? '–' }}</td>
                        <td>{{ $statusMap[$o->status] ?? $o->status }}</td>
                        <td>{{ $o->created_at->format('d.m.Y') }}</td>
                        <td>
                            @forelse($o->steps->sortBy('completed_at') as $s)
                                {{ $s->role?->name }} ({{ $s->completed_by }}, {{ $s->completed_at?->format('d.m.Y H:i') }})@if(!$loop->last); @endif
                            @empty
                                <span class="hint">noch keine Quittierungen / no acknowledgements yet</span>
                            @endforelse
                        </td>
                    </tr>
                @endforeach
            </table>
        @endif

        <h2>3. Vollständiges Protokoll / Full audit log ({{ $logs->count() }} Einträge / entries)</h2>
        <table>
            <tr><th style="width:26px">ID</th><th style="width:82px">Zeitpunkt / Time</th><th style="width:58px">Typ / Type</th><th style="width:70px">Benutzer / User</th><th>Vorgang / Ereignis / Details · Case / Event / Details</th></tr>
            @foreach($logs as $log)
                <tr>
                    <td>{{ $log->id }}</td>
                    <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                    <td>{{ $log->type }}</td>
                    <td>{{ $log->user ?? 'System' }}</td>
                    <td>
                        @if($log->onboarding_name)<strong>{{ $log->onboarding_name }}:</strong> @endif{{ $log->message }}
                        @if($log->details)<br><span class="hint">@foreach($log->details as $k => $v){{ $k }}: {{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}@if(!$loop->last) · @endif @endforeach</span>@endif
                        <br><span class="hash">{{ $log->hash }}</span>
                    </td>
                </tr>
            @endforeach
        </table>
    </div>
    <div class="foot">
        {{ $footerText ?: $company }} · Bericht erzeugt am {{ $generatedAt->format('d.m.Y H:i:s') }} Uhr · Die Hash-Kette (SHA-256, jeder Eintrag referenziert den Vorgänger) dient dem Manipulationsnachweis gem. ISO/IEC 27001 A.8.15. · The hash chain (SHA-256, each entry references its predecessor) provides tamper evidence per ISO/IEC 27001 A.8.15.
    </div>
</body>
</html>
