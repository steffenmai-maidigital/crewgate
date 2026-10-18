@extends('errors.layout')
@section('symbol', '✋')
@section('code', '429')
@section('title', __('Einen Moment bitte'))
@section('text', __('Das waren zu viele Versuche in kurzer Zeit.'))
@section('tips')
    @php($seconds = \App\Support\ErrorPage::retryAfter($exception ?? null))
    <li>{{ $seconds ? __('Versuchen Sie es in etwa :sek Sekunden noch einmal.', ['sek' => $seconds]) : __('Warten Sie eine Minute und versuchen Sie es dann noch einmal.') }}</li>
    <li>{{ __('Nach mehreren falschen Anmeldungen bremst die Anwendung kurz – das schützt vor dem Erraten von Passwörtern.') }}</li>
@endsection
