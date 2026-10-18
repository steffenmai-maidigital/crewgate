@extends('errors.layout')
@section('symbol', '📦')
@section('code', '413')
@section('title', __('Die Datei ist zu groß'))
@section('text', __('Die gesendeten Daten sind größer, als der Server annimmt (höchstens :max).', ['max' => \App\Support\ErrorPage::uploadLimit()]))
@section('tips')
    <li>{{ __('Wählen Sie eine kleinere Datei.') }}</li>
    <li>{{ __('Bilder vor dem Hochladen verkleinern hilft meist schon.') }}</li>
    <li>{{ __('Ihr Administrator kann die Grenze erhöhen (PHP: upload_max_filesize und post_max_size).') }}</li>
@endsection
