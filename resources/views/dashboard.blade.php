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

    @if($myTasks->isEmpty() && $departmentOnboardings->isEmpty() && $participating->isEmpty() && ! ($user['is_hr'] ?? false))
        <div class="panel">
            <p style="margin:0">{{ __('Zurzeit wartet keine Aufgabe auf Sie.') }}</p>
            <p class="hint" style="margin-bottom:0">{{ __('Sobald ein Vorgang Ihre Abteilung betrifft, erscheint er hier. Mehr dazu unter „Hilfe“ im Footer.') }}</p>
        </div>
    @endif

    @if($myTasks->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Aufgaben für Ihre Abteilung') }}</h2>
            <table class="cards">
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Eintritt') }}</th><th>{{ __('Abteilung') }}</th><th>{{ __('Ihre Daten') }}</th><th><span class="sr-only">{{ __('Aktionen') }}</span></th></tr>
                @foreach($myTasks as $o)
                    @foreach($o->my_pending as $p)
                        <tr>
                            <td data-label="{{ __('Mitarbeiter') }}"><a href="{{ route('onboardings.show', $o) }}">{{ $o->fullName() }}</a> <span class="badge process">{{ $o->processLabel() }}</span></td>
                            <td data-label="{{ __('Eintritt') }}">{{ $o->start_date?->format('d.m.Y') ?? '–' }}</td>
                            <td data-label="{{ __('Abteilung') }}">{{ $o->department ?? '–' }}</td>
                            <td data-label="{{ __('Ihre Daten') }}">
                                @if($p['data_ready'])
                                    <span class="badge done">{{ __('ausgefüllt') }}</span>
                                @else
                                    <span class="badge open">{{ __('unvollständig') }}</span>
                                @endif
                            </td>
                            <td class="actions" style="white-space:nowrap">
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
            <h2 style="margin-top:0">{{ __('Vorgänge in Ihrer Abteilung') }} ({{ $user['department'] }})</h2>
            <table class="cards">
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Eintritt') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ausstehende Quittierungen') }}</th><th><span class="sr-only">{{ __('Aktionen') }}</span></th></tr>
                @foreach($departmentOnboardings as $o)
                    <tr>
                        <td data-label="{{ __('Mitarbeiter') }}"><a href="{{ route('onboardings.show', $o) }}">{{ $o->fullName() }}</a> <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td data-label="{{ __('Eintritt') }}">{{ $o->start_date?->format('d.m.Y') ?? '–' }}</td>
                        <td data-label="{{ __('Status') }}"><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                        <td data-label="{{ __('Ausstehende Quittierungen') }}">
                            @if($o->status === 'open')
                                @foreach($o->pendingRoles() as $pr)<span class="badge open">{{ $pr->displayName() }}</span> @endforeach
                            @else
                                –
                            @endif
                        </td>
                        <td class="actions"><a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a></td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @if($participating->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Vorgänge (Beteiligung Ihrer Abteilung)') }}</h2>
            <table class="cards">
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ausstehende Quittierungen') }}</th><th><span class="sr-only">{{ __('Aktionen') }}</span></th></tr>
                @foreach($participating as $o)
                    <tr>
                        <td data-label="{{ __('Mitarbeiter') }}"><a href="{{ route('onboardings.show', $o) }}">{{ $o->fullName() }}</a> <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td data-label="{{ __('Status') }}"><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                        <td data-label="{{ __('Ausstehende Quittierungen') }}">
                            @if($o->status === 'open')
                                @foreach($o->pendingRoles() as $pr)<span class="badge open">{{ $pr->displayName() }}</span> @endforeach
                            @else
                                –
                            @endif
                        </td>
                        <td class="actions"><a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a></td>
                    </tr>
                @endforeach
            </table>
            <p class="hint">{{ __('Sie sehen darin die Felder Ihrer Abteilung sowie die zusätzlich freigegebenen Daten anderer Abteilungen.') }}</p>
        </div>
    @endif

    @if(($user['is_hr'] ?? false))
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Offene Vorgänge (HR)') }}</h2>
            <table class="cards">
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Abteilung') }}</th><th>{{ __('Vorgesetzter') }}</th><th>{{ __('Status') }}</th><th>{{ __('Ausstehende Quittierungen') }}</th><th>{{ __('Angelegt') }}</th><th><span class="sr-only">{{ __('Aktionen') }}</span></th></tr>
                @forelse($openCases as $o)
                    <tr>
                        <td data-label="{{ __('Mitarbeiter') }}"><a href="{{ route('onboardings.show', $o) }}">{{ $o->fullName() }}</a> <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td data-label="{{ __('Abteilung') }}">{{ $o->department ?? '–' }}</td>
                        <td data-label="{{ __('Vorgesetzter') }}">{{ $o->supervisor_name ?? $o->supervisor_username }}</td>
                        <td data-label="{{ __('Status') }}"><span class="badge {{ $o->statusBadge() }}">{{ $o->statusLabel() }}</span></td>
                        <td data-label="{{ __('Ausstehende Quittierungen') }}">
                            @if($o->status === 'open')
                                @forelse($o->pendingRoles() as $pr)<span class="badge open">{{ $pr->displayName() }}</span> @empty – @endforelse
                            @elseif($o->status === 'review')
                                <span class="badge done">{{ __('alle quittiert') }}</span>
                            @else
                                –
                            @endif
                        </td>
                        <td data-label="{{ __('Angelegt') }}">{{ $o->created_at->format('d.m.Y') }}</td>
                        <td class="actions" style="white-space:nowrap">
                            <a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a>
                            @if($o->status === 'review')
                                <form method="post" action="{{ route('onboardings.close', $o) }}" class="inline-form" style="margin-left:.6rem" onsubmit="return confirm(@js(__('Vorgang abschließen? Danach beginnt die Aufbewahrungsfrist.')))">
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
            <table class="cards">
                <tr><th>{{ __('Mitarbeiter') }}</th><th>{{ __('Abteilung') }}</th><th>{{ __('Vorgesetzter') }}</th><th>{{ __('Abgeschlossen am') }}</th><th><span class="sr-only">{{ __('Aktionen') }}</span></th></tr>
                @forelse($closedCases as $o)
                    <tr>
                        <td data-label="{{ __('Mitarbeiter') }}"><a href="{{ route('onboardings.show', $o) }}">{{ $o->fullName() }}</a> <span class="badge process">{{ $o->processLabel() }}</span></td>
                        <td data-label="{{ __('Abteilung') }}">{{ $o->department ?? '–' }}</td>
                        <td data-label="{{ __('Vorgesetzter') }}">{{ $o->supervisor_name ?? $o->supervisor_username }}</td>
                        <td data-label="{{ __('Abgeschlossen am') }}">{{ $o->completed_at?->format('d.m.Y') ?? '–' }}</td>
                        <td class="actions"><a href="{{ route('onboardings.show', $o) }}">{{ __('Öffnen') }}</a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="hint">{{ __('Noch keine abgeschlossenen Vorgänge.') }}</td></tr>
                @endforelse
            </table>
            <p class="hint">{{ __('Abgeschlossene Vorgänge werden nach Ablauf der eingestellten Aufbewahrungsfrist automatisch gelöscht.') }}</p>
        </div>
    @endif
@endsection
