@extends('errors.layout')
@section('symbol', '🚧')
@section('code', '503')
@section('reload', '30')
@section('title', __('Gleich wieder da'))
@section('text', __('Die Anwendung wird gerade gewartet, zum Beispiel für ein Update.'))
@section('tips')
    <li>{{ __('Die Seite lädt sich alle 30 Sekunden selbst neu.') }}</li>
    <li>{{ __('Dauert es länger als eine Viertelstunde, fragen Sie Ihren Administrator.') }}</li>
@endsection
