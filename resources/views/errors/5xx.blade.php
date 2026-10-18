@extends('errors.layout')
@section('symbol', '🛠')
@section('code', (string) (isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 500))
@section('title', __('Da ist etwas schiefgegangen'))
@section('text', __('Ein unerwarteter Fehler ist aufgetreten. Ihre letzte Aktion wurde vermutlich nicht ausgeführt.'))
@section('tips')
    <li>{{ __('Versuchen Sie es in ein paar Minuten noch einmal.') }}</li>
    <li>{{ __('Tritt der Fehler wieder auf, nennen Sie Ihrem Administrator die Fehlernummer unten. Mit ihr findet sich der Eintrag unter Hilfe → Letzte Fehler.') }}</li>
@endsection
