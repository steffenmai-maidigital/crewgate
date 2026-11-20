@extends('layout')
@section('title', __('Mail-Log'))
@section('content')
    <h1>{{ __('Mail-Log (ausgehende E-Mails)') }}</h1>

    <div class="panel">
        <table class="cards">
            <tr><th style="width:145px">{{ __('Zeitpunkt') }}</th><th>{{ __('Empfänger') }}</th><th>{{ __('Betreff') }}</th><th style="width:110px">{{ __('Status') }}</th></tr>
            @forelse($logs as $log)
                <tr>
                    <td data-label="{{ __('Zeitpunkt') }}">{{ $log->created_at->format('d.m.Y H:i:s') }}</td>
                    <td data-label="{{ __('Empfänger') }}">{{ $log->recipient }}</td>
                    <td data-label="{{ __('Betreff') }}">
                        <details>
                            <summary style="cursor:pointer">{{ $log->subject }}</summary>
                            <pre style="white-space:pre-wrap;font:inherit;background:var(--chip);padding:.7rem .9rem;border-radius:10px;margin:.5rem 0 0">{{ $log->body }}</pre>
                        </details>
                    </td>
                    <td data-label="{{ __('Status') }}">
                        <span class="badge {{ $log->status === \App\Models\MailLog::STATUS_SENT ? 'done' : 'open' }}">{{ $log->statusLabel() }}</span>
                        @if($log->error)<div class="hint">{{ $log->error }}</div>@endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="hint">{{ __('Noch keine E-Mails versendet.') }}</td></tr>
            @endforelse
        </table>
        <div style="margin-top:.8rem">
            @if(!$logs->onFirstPage())<a href="{{ $logs->previousPageUrl() }}">&larr; {{ __('Neuere') }}</a>@endif
            @if($logs->hasMorePages())<a href="{{ $logs->nextPageUrl() }}" style="margin-left:1rem">{{ __('Ältere') }} &rarr;</a>@endif
            <span class="hint" style="margin-left:1rem">{{ __('Seite') }} {{ $logs->currentPage() }}</span>
        </div>
        <p class="hint">{{ __('Auf den Betreff klicken, um den vollständigen Nachrichtentext zu sehen. Im Demo-Modus werden Mails nicht wirklich zugestellt, aber hier protokolliert.') }}</p>
    </div>
@endsection
