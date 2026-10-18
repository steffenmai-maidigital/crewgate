@extends('layout')
@section('title', __('Übersicht'))
@section('content')
    @if(session('import_errors') && count(session('import_errors')))
        <div class="panel" style="border-left:4px solid #c0392b">
            <strong>{{ __('Beim CSV-Import übersprungene Zeilen:') }}</strong>
            <ul style="margin:.4rem 0 0 1.1rem">
                @foreach(session('import_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    <h1>{{ __('Übersicht') }}</h1>

    <div class="stats">
        <div class="stat"><span class="num">{{ $stats['draft'] }}</span><span class="lbl">{{ __('Offen (in Vorbereitung)') }}</span></div>
        <div class="stat"><span class="num">{{ $stats['active'] }}</span><span class="lbl">{{ __('In Bearbeitung') }}</span></div>
        <div class="stat"><span class="num">{{ $stats['done'] }}</span><span class="lbl">{{ __('Abgeschlossen') }}</span></div>
    </div>

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Workflow-Reihenfolge') }}</h2>
        @include('partials.workflow-flow', ['roles' => $workflow, 'big' => true])
    </div>

    @if($myTasks->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Aufgaben für Ihre Abteilung') }}</h2>
            <table>
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Eintritt') }}</th><th>{{ __('Abteilung') }}</th><th>{{ __('Eure Daten') }}</th><th></th></tr>
                @foreach($myTasks as $o)
                    @foreach($o->my_pending as $p)
                        <tr>
                            <td>{{ $o->fullName() }} <span class="badge process">{{ $o->processLabel() }}</span></td>
                            <td>{{ $o->start_date?->format('d.m.Y') ?? '–' }}</td>
                            <td>{{ $o->department ?? '–' }}</td>
                            <td>
                                @if($p['data_ready'])
                                    <span class="badge done">{{ __('ausgefüllt') }}</span>
                                @else
                                    <span class="badge open">{{ __('unvollständig') }}</span>
                                @endif
                            </td>
                            <td style="white-space:nowrap">
                                <a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a>
                                @if($p['data_ready'])
                                    <form method="post" action="{{ route('onboardings.acknowledge', [$o, $p['role']]) }}" class="inline-form" style="margin-left:.6rem">
                                        @csrf
                                        <button class="primary small">{{ __('Erhalt quittieren') }}</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </table>
            <p class="hint">{{ __('Quittieren übernimmt die vorliegenden Daten unverändert. Zum Anpassen der Daten den Vorgang öffnen.') }}</p>
        </div>
    @endif

    @if($departmentOnboardings->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Neue Mitarbeiter in deiner Abteilung') }} ({{ $user['department'] }})</h2>
            <table>
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Eintritt') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ausstehende Quittierungen') }}</th><th></th></tr>
                @foreach($departmentOnboardings as $o)
                    <tr>
                        <td>{{ $o->fullName() }} <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td>{{ $o->start_date?->format('d.m.Y') ?? '–' }}</td>
                        <td><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                        <td>
                            @if($o->status === 'open')
                                @foreach($o->pendingRoles() as $pr)<span class="badge open">{{ $pr->name }}</span> @endforeach
                            @else
                                –
                            @endif
                        </td>
                        <td><a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a></td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if($participating->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Vorgänge (Beteiligung deiner Abteilung)') }}</h2>
            <table>
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ausstehende Quittierungen') }}</th><th></th></tr>
                @foreach($participating as $o)
                    <tr>
                        <td>{{ $o->fullName() }} <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                        <td>
                            @if($o->status === 'open')
                                @foreach($o->pendingRoles() as $pr)<span class="badge open">{{ $pr->name }}</span> @endforeach
                            @else
                                –
                            @endif
                        </td>
                        <td><a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a></td>
                    </tr>
                @endforeach
            </table>
            <p class="hint">{{ __('Du siehst darin die Felder deiner Abteilung sowie die im Backend zusätzlich freigegebenen Daten.') }}</p>
        </div>
    @endif

    @if(($user['is_hr'] ?? false))
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Offene Vorgänge (HR)') }}</h2>
            <table>
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Abteilung') }}</th><th>{{ __('Vorgesetzter') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ausstehende Quittierungen') }}</th><th>{{ __('Angelegt') }}</th><th></th></tr>
                @forelse($openCases as $o)
                    <tr>
                        <td>{{ $o->fullName() }} <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td>{{ $o->department ?? '–' }}</td>
                        <td>{{ $o->supervisor_name ?? $o->supervisor_username }}</td>
                        <td><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                        <td>
                            @if($o->status === 'open')
                                @forelse($o->pendingRoles() as $pr)<span class="badge open">{{ $pr->name }}</span> @empty – @endforelse
                            @elseif($o->status === 'review')
                                <span class="badge done">{{ __('alle quittiert') }}</span>
                            @else
                                –
                            @endif
                        </td>
                        <td>{{ $o->created_at->format('d.m.Y') }}</td>
                        <td style="white-space:nowrap">
                            <a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a>
                            @if($o->status === 'review')
                                <form method="post" action="{{ route('onboardings.close', $o) }}" class="inline-form" style="margin-left:.6rem" onsubmit="return confirm('{{ __('Vorgang abschließen? Danach beginnt die Aufbewahrungsfrist.') }}')">
                                    @csrf
                                    <button class="primary small">{{ __('Abschließen') }}</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="hint">{{ __('Keine offenen Vorgänge.') }}</td></tr>
                @endforelse
            </table>
        </div>

        <div class="panel">
            <h2 style="margin-top:0">{{ __('Abgeschlossene Vorgänge (HR)') }}</h2>
            <table>
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Abteilung') }}</th><th>{{ __('Vorgesetzter') }}</th><th>{{ __('Abgeschlossen am') }}</th><th></th></tr>
                @forelse($closedCases as $o)
                    <tr>
                        <td>{{ $o->fullName() }} <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td>{{ $o->department ?? '–' }}</td>
                        <td>{{ $o->supervisor_name ?? $o->supervisor_username }}</td>
                        <td>{{ $o->completed_at?->format('d.m.Y') ?? '–' }}</td>
                        <td><a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="hint">{{ __('Noch keine abgeschlossenen Vorgänge.') }}</td></tr>
                @endforelse
            </table>
            <p class="hint">{{ __('Abgeschlossene Vorgänge werden nach Ablauf der eingestellten Aufbewahrungsfrist automatisch gelöscht.') }}</p>
        </div>
    @endif
@endsection
