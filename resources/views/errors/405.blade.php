@extends('errors.layout')
@section('symbol', '🧭')
@section('code', '405')
@section('title', __('Diese Adresse geht so nicht'))
@section('text', __('Die Seite lässt sich auf diesem Weg nicht aufrufen – meist nach dem Neuladen eines abgeschickten Formulars oder über ein altes Lesezeichen.'))
@section('tips')
    <li>{{ __('Gehen Sie zurück und öffnen Sie die Seite über das Menü neu.') }}</li>
@endsection
