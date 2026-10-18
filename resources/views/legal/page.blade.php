@extends('layout')
@section('title', $title)
@section('content')
    <div class="panel legal-page">
        <h1 style="margin-top:0">{{ $type === 'imprint' ? '⚖️' : '🔒' }} {{ $title }}</h1>
        {!! $html !!}
    </div>
    <p><a href="{{ url('/') }}">‹ {{ __('Zur Startseite') }}</a></p>
@endsection
