@extends('errors.layout')
@section('symbol', '🔑')
@section('code', '401')
@section('title', __('Bitte melden Sie sich an'))
@section('text', __('Diese Seite ist nur nach der Anmeldung erreichbar.'))
@section('tips')
    <li>{{ __('Melden Sie sich mit Ihrem Benutzernamen und Passwort an.') }}</li>
    <li>{{ __('Sie haben noch kein Konto? Ihr Administrator legt eines an.') }}</li>
@endsection
