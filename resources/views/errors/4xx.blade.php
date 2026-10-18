@extends('errors.layout')
@section('symbol', '⚠️')
@section('code', (string) (isset($exception) && method_exists($exception, 'getStatusCode') ? $exception->getStatusCode() : 400))
@section('title', __('Das hat nicht geklappt'))
@section('text', __('Die Anfrage konnte nicht ausgeführt werden.'))
@section('tips')
    <li>{{ __('Gehen Sie zurück und versuchen Sie es noch einmal.') }}</li>
    <li>{{ __('Klappt es wieder nicht, wenden Sie sich an Ihren Administrator.') }}</li>
@endsection
