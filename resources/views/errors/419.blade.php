@extends('errors.layout')
@section('symbol', '⏰')
@section('code', '419')
@section('title', __('Die Sitzung ist abgelaufen'))
@section('text', __('Sie waren eine Weile nicht aktiv. Aus Sicherheitsgründen wurde das Formular nicht verarbeitet.'))
@section('tips')
    <li>{{ __('Gehen Sie zurück, laden Sie die Seite neu und senden Sie das Formular noch einmal ab.') }}</li>
    <li>{{ __('Tipp: Längere Texte vor dem Absenden kopieren.') }}</li>
@endsection
