@extends('errors.layout')
@section('symbol', '🔒')
@section('code', '403')
@section('title', __('Kein Zugriff'))
@section('text', __('Diese Seite ist für Ihr Konto nicht freigegeben.'))
@section('tips')
    <li>{{ __('Prüfen Sie, ob Sie mit dem richtigen Konto angemeldet sind.') }}</li>
    <li>{{ __('Rechte vergibt Ihr Administrator – bei Verzeichniskonten über die Gruppen im Active Directory bzw. LDAP.') }}</li>
    <li>{{ __('Kamen Sie über einen Link hierher? Dann gehen Sie zurück und nehmen Sie den Weg über das Menü.') }}</li>
@endsection
