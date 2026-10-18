@extends('errors.layout')
@section('symbol', '🔍')
@section('code', '404')
@section('title', __('Seite nicht gefunden'))
@section('text', __('Diese Seite gibt es nicht (mehr). Vielleicht wurde der Eintrag gelöscht oder der Link ist falsch.'))
@section('tips')
    <li>{{ __('Prüfen Sie die Adresse auf Tippfehler.') }}</li>
    <li>{{ __('Gehen Sie über die Startseite und das Menü zum Ziel.') }}</li>
    <li>{{ __('Ein altes Lesezeichen? Speichern Sie es danach neu.') }}</li>
@endsection
