@extends('layout')
@section('title', __('Protokoll'))
@section('content')
    <h1>{{ __('Protokoll') }}</h1>

    <div class="panel">
        <div class="row" style="align-items:flex-end">
            <div style="max-width:280px">
                <form method="get" action="{{ route('logs') }}">
                    <label for="type">{{ __('Filter') }}</label>
                    <select id="type" name="type" onchange="this.form.submit()">
                        @foreach($types as $key => $label)
                            <option value="{{ $key }}" @selected($type === $key || (!$type && $key === ''))>{{ $label }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div style="flex:0">
                <a class="btn" href="{{ route('logs.export') }}">{{ __('Als CSV exportieren') }}</a>
            </div>
            <div style="flex:0">
                <a class="btn" href="{{ route('logs.export_pdf') }}">{{ __('Audit-Bericht (PDF)') }}</a>
            </div>
        </div>
        <p class="hint" style="margin-bottom:0">Das Protokoll ist append-only mit SHA-256-Hash-Kette (Manipulationsschutz gem. ISO/IEC 27001, A.8.15). Der Export enthält alle Einträge inkl. Löschungen und Hashes; jeder Export wird selbst protokolliert.</p>
    </div>

    <div class="panel">
        <table>
            <tr><th style="width:145px">{{ __('Zeitpunkt') }}</th><th style="width:100px">{{ __('Typ') }}</th><th style="width:130px">{{ __('Benutzer') }}</th><th>{{ __('Vorgang') }}</th><th>{{ __('Ereignis') }}</th></tr>
            @forelse($logs as $log)
                <tr>
                    <td>{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                    <td><span class="badge">{{ $log->type }}</span></td>
                    <td>{{ $log->user ?? 'System' }}</td>
                    <td>
                        @if($log->onboarding_id)
                            <a href="{{ route('onboardings.show', $log->onboarding_id) }}">{{ $log->onboarding_name }}</a>
                            @if($log->onboarding)<span class="badge process">{{ $log->onboarding->processLabel() }}</span>@endif
                        @else
                            {{ $log->onboarding_name ?? '–' }}
                        @endif
                    </td>
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
            @empty
                <tr><td colspan="5" class="hint">{{ __('Noch keine Protokolleinträge.') }}</td></tr>
            @endforelse
        </table>
        <div style="margin-top:.8rem">
            @if($logs->onFirstPage()) @else <a href="{{ $logs->previousPageUrl() }}">&larr; Neuere</a> @endif
            @if($logs->hasMorePages()) <a href="{{ $logs->nextPageUrl() }}" style="margin-left:1rem">Ältere &rarr;</a> @endif
            <span class="hint" style="margin-left:1rem">Seite {{ $logs->currentPage() }}</span>
        </div>
        <p class="hint">Protokolleinträge zu einem Vorgang bleiben auch nach dessen automatischer Löschung erhalten (ohne Verknüpfung, nur mit Namen). Löschungen erscheinen als eigener Typ „löschung".</p>
    </div>
@endsection
