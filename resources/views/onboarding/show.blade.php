@extends('layout')
@section('title', $onboarding->fullName())
@section('content')
    <h1>{{ $onboarding->fullName() }} <span class="badge process" style="vertical-align:middle">{{ $onboarding->processLabel() }}</span></h1>

    <div class="panel">
        <div class="row">
            <div><label>{{ __('Abteilung (aus AD)') }}</label>{{ $onboarding->department ?? __('nicht gepflegt') }}</div>
            <div><label>{{ __('Vorgesetzter') }}</label>{{ $onboarding->supervisor_name ?? $onboarding->supervisor_username }}</div>
            <div><label>{{ __('Eintritt') }}</label>{{ $onboarding->start_date?->format('d.m.Y') ?? '–' }}</div>
            @if($onboarding->supervisor_due_date)
                <div><label>{{ __('Frist Vorgesetzter') }}</label>
                    <span @if($onboarding->status === 'draft' && $onboarding->supervisor_due_date->isPast()) style="color:#c0392b;font-weight:700" @endif>{{ $onboarding->supervisor_due_date->format('d.m.Y') }}</span>
                </div>
            @endif
            @if($onboarding->roles_due_date)
                <div><label>{{ __('Frist Abteilungen') }}</label>
                    <span @if(in_array($onboarding->status, ['draft','open']) && $onboarding->roles_due_date->isPast()) style="color:#c0392b;font-weight:700" @endif>{{ $onboarding->roles_due_date->format('d.m.Y') }}</span>
                </div>
            @endif
            <div><label>{{ __('Status') }}</label>
                <span class="badge {{ $onboarding->statusBadge() }}">
                    {{ $onboarding->status === 'done' ? __('abgeschlossen') . ' ' . $onboarding->completed_at?->format('d.m.Y') : $onboarding->statusLabel() }}
                </span>
            </div>
        </div>
        @if($onboarding->notes)<label>{{ __('Notizen') }}</label>{{ $onboarding->notes }}@endif

        <label>{{ __('Quittierungen') }}</label>
        @php($doneRoleIds = $onboarding->steps->pluck('role_id')->all())
        @include('partials.workflow-flow', ['roles' => $onboarding->workflowRoles(), 'doneIds' => $doneRoleIds, 'onboarding' => $onboarding])


    </div>

    @if($user['is_hr'] ?? false)
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Aktionen (HR)') }}</h2>
            @if($onboarding->status === 'draft')
                <p class="hint" style="margin-top:0">{{ __('Der/die Vorgesetzte wurde per E-Mail gebeten, die Angaben zu pflegen. Sie können die Angaben aber auch selbst unten ausfüllen und absenden – zum Beispiel, wenn es schnell gehen muss.') }}</p>
            @endif
            <div style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
                @if($onboarding->status === 'draft' && $draftRoles->isNotEmpty())
                    <a class="primary small" style="text-decoration:none;padding:.45rem .9rem;border-radius:10px" href="#draft-form">{{ __('Angaben selbst pflegen') }}</a>
                @endif
                @if($onboarding->status !== 'done' && $onboarding->status !== 'cancelled')
                    <form method="post" action="{{ route('onboardings.cancel', $onboarding) }}" class="inline-form" onsubmit="return confirm('{{ __('Vorgang wirklich abbrechen? Der Workflow wird gestoppt, der Vorgesetzte wird per E-Mail informiert; der Vorgang bleibt sichtbar.') }}')">
                        @csrf
                        <button class="primary small danger">{{ __('Vorgang abbrechen') }}</button>
                    </form>
                @endif
                <form method="post" action="{{ route('onboardings.destroy', $onboarding) }}" class="inline-form" onsubmit="return confirm('{{ __('Vorgang endgültig löschen? Alle erfassten Daten werden entfernt; die Löschung wird protokolliert.') }}')">
                    @csrf @method('DELETE')
                    <button class="primary small danger">{{ __('Vorgang löschen') }}</button>
                </form>
            </div>
            <p class="hint">{{ __('Abbrechen stoppt den Workflow und informiert den Vorgesetzten per E-Mail (Vorlage anpassbar in den Einstellungen). Löschen entfernt den Vorgang mit allen Daten endgültig – nur der Protokolleintrag bleibt.') }}</p>
        </div>
    @endif

    @if($onboarding->status === 'draft' && $draftRoles->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Vorbereitung: Angaben für die Abteilungen pflegen') }}</h2>
            <p class="hint">{{ __('Sie können jederzeit zwischenspeichern. Erst mit „Absenden" werden alle Abteilungen gleichzeitig per E-Mail benachrichtigt und quittieren dann einzeln den Erhalt.') }}</p>
            <form method="post" action="{{ route('onboardings.draft', $onboarding) }}" id="draft-form">
                @csrf
                @foreach($draftRoles as $draftRole)
                    <h2>{{ $draftRole->name }}</h2>
                    @forelse($draftRole->fields as $field)
                        @include('partials.field-input', ['field' => $field, 'prefill' => $prefill])
                    @empty
                        <p class="hint">{{ __('Für diese Abteilung sind keine Felder konfiguriert.') }}</p>
                    @endforelse
                @endforeach
                <div class="row" style="align-items:center">
                    <div style="flex:0"><button class="primary">{{ __('Zwischenspeichern') }}</button></div>
                    <div style="flex:0"><button class="primary" formaction="{{ route('onboardings.submit', $onboarding) }}" onclick="return confirm('{{ __('Vorgang jetzt absenden? Danach werden alle Abteilungen per E-Mail benachrichtigt.') }}')">{{ __('Absenden & Workflow starten') }}</button></div>
                </div>
            </form>
        </div>
    @elseif($onboarding->status === 'draft')
        <div class="panel">
            <p class="hint" style="margin:0">{{ __('Dieser Vorgang befindet sich in Vorbereitung – der Vorgesetzte pflegt aktuell die Angaben. Die Abteilungen werden nach dem Absenden benachrichtigt.') }}</p>
        </div>
    @endif

    @if($onboarding->status === 'review')
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Alle Abteilungen haben quittiert') }}</h2>
            @if($user['is_hr'] ?? false)
                <p class="hint">{{ __('Der Vorgesetzte hat die Daten ausgefüllt und alle Fachabteilungen haben die Erledigung quittiert. Sie können den Vorgang jetzt abschließend quittieren – danach beginnt die Aufbewahrungsfrist.') }}</p>
                <form method="post" action="{{ route('onboardings.close', $onboarding) }}" onsubmit="return confirm('{{ __('Vorgang abschließen? Danach beginnt die Aufbewahrungsfrist.') }}')">
                    @csrf
                    <button class="primary">{{ __('Vorgang abschließen') }}</button>
                </form>
            @else
                <p class="hint" style="margin:0">{{ __('Der Vorgang wartet auf den Abschluss durch HR.') }}</p>
            @endif
        </div>
    @endif

    @foreach($actionRoles as $actionRole)
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Erhalt quittieren') }}: {{ $actionRole->name }}</h2>
            <p class="hint">{{ __('Prüfen bzw. ergänzen Sie die Angaben Ihrer Abteilung und bestätigen Sie den Erhalt.') }}</p>
            <form method="post" action="{{ route('onboardings.complete', [$onboarding, $actionRole]) }}">
                @csrf
                @forelse($actionRole->fields as $field)
                    @include('partials.field-input', ['field' => $field, 'prefill' => $prefill])
                @empty
                    <p class="hint">{{ __('Für diese Rolle sind keine Felder konfiguriert – der Erhalt kann direkt quittiert werden.') }}</p>
                @endforelse
                <button class="primary">{{ __('Speichern & Erhalt quittieren') }}</button>
            </form>
        </div>
    @endforeach

    @if($visibleValues->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Bisher erfasste Daten') }}</h2>
            <table>
                <tr><th>{{ __('Abteilung') }}</th><th>{{ __('Feld') }}</th><th>{{ __('Wert') }}</th><th>{{ __('Ausgefüllt von') }}</th></tr>
                @foreach($visibleValues->sortBy(fn($v) => [$v->field?->role?->sort_order, $v->field?->sort_order]) as $v)
                    <tr>
                        <td>{{ $v->field?->role?->name }}</td>
                        <td>{{ $v->field?->displayLabel() }}</td>
                        <td>{{ $v->field?->displayValue($v->value) ?? '–' }}</td>
                        <td>{{ $v->filled_by }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif

    @php($showLog = ($user['is_hr'] ?? false) || ($user['is_admin'] ?? false) || ($user['is_auditor'] ?? false))
    @if($showLog)
        @php($caseLogs = \App\Models\ActivityLog::where('onboarding_id', $onboarding->id)->orderByDesc('created_at')->limit(100)->get())
        @if($caseLogs->isNotEmpty())
            <div class="panel">
                <h2 style="margin-top:0">{{ __('Protokoll zu diesem Vorgang') }}</h2>
                <table>
                    <tr><th style="width:145px">{{ __('Zeitpunkt') }}</th><th style="width:100px">{{ __('Typ') }}</th><th style="width:130px">{{ __('Benutzer') }}</th><th>{{ __('Ereignis') }}</th></tr>
                    @foreach($caseLogs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                            <td><span class="badge">{{ $log->type }}</span></td>
                            <td>{{ $log->user ?? __('System') }}</td>
                            <td>
                                {{ $log->message }}
                                @if($log->details)
                                    <div class="hint" style="margin-top:.25rem">
                                        @foreach($log->details as $k => $v)
                                            <div><strong>{{ $k }}:</strong> {{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    @endif

    @if($onboarding->steps->isNotEmpty())
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Quittierungen') }}</h2>
            <table>
                <tr><th>{{ __('Abteilung') }}</th><th>{{ __('Quittiert von') }}</th><th>{{ __('Am') }}</th></tr>
                @foreach($onboarding->steps->sortBy('completed_at') as $s)
                    <tr>
                        <td>{{ $s->role?->name }}</td>
                        <td>{{ $s->completed_by }}</td>
                        <td>{{ $s->completed_at?->format('d.m.Y H:i') }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    @endif
@endsection
