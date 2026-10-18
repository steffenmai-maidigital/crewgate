@extends('layout')
@section('title', __($title))
@section('content')
    <p><a href="{{ route('help') }}">← {{ __('Hilfe') }}</a></p>
    <h1>{{ __($title) }}</h1>
    <div class="panel">
        <p class="hint" style="margin-top:0">{{ __('Weitere Dokumente:') }}
            @foreach($docs as $key => [$label, $file, $adminOnly])
                @continue($key === $doc || ($adminOnly && ! $isAdmin))
                <a href="{{ route('help.doc', $key) }}">{{ __($label) }}</a>{{ ! $loop->last ? ' · ' : '' }}
            @endforeach
        </p>
        <div class="md-body">{!! $html !!}</div>
    </div>
@endsection
