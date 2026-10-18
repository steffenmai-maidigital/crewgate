@extends('layout')
@section('title', __('Bestätigungscode'))
@section('content')
    <div class="panel login-box" style="max-width:420px;margin:3rem auto;">
        <h1>{{ __('Bestätigung') }}</h1>
        <p>{{ __('Geben Sie den 6-stelligen Code aus Ihrer Authenticator-App ein. Ohne Handy geht auch einer Ihrer Wiederherstellungscodes.') }}</p>
        <form method="post" action="{{ route('login.code') }}">
            @csrf
            <label for="code">{{ __('Code') }}</label>
            <input type="text" id="code" name="code" inputmode="numeric" autocomplete="one-time-code" autofocus required maxlength="20"
                   style="font-size:1.5rem;letter-spacing:.3em;text-align:center">
            <button class="primary" style="width:100%">{{ __('Bestätigen') }}</button>
        </form>
        <p class="hint" style="text-align:center;margin-bottom:0"><a href="{{ route('login') }}">{{ __('Abbrechen') }}</a></p>
    </div>
@endsection
