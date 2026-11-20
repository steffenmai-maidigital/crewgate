@extends('layout')
@section('title', __('Prozesse'))
@section('content')
    <h1>{{ __('Prozesse') }}</h1>

    <div class="panel">
        <p class="hint" style="margin-top:0">{{ __('Hier legen Sie Prozessarten an, z. B. eine „Azubi-Übernahme“ mit verkürztem Workflow: Nicht ausgewählte Abteilungen werden weder benachrichtigt noch müssen sie quittieren; ihre Felder entfallen. Keine Auswahl = alle Abteilungen. Welche Felder je Prozess abgefragt werden, pflegen Sie zusätzlich unter „Felder“.') }}</p>
        <table class="manage cards">
            <tr><th style="width:60px">{{ __('Reihenfolge') }}</th><th>{{ __('Name') }}</th><th>{{ __('Beteiligte Abteilungen') }}</th><th style="width:60px">{{ __('Aktiv') }}</th><th style="width:170px"><span class="sr-only">{{ __('Aktionen') }}</span></th></tr>
            @foreach($processes as $p)
                <tr>
                    <form method="post" action="{{ route('processes.update', $p) }}">
                        @csrf
                        <td data-label="{{ __('Reihenfolge') }}"><input type="number" name="sort_order" value="{{ $p->sort_order }}" aria-label="{{ __('Reihenfolge') }}"></td>
                        <td data-label="{{ __('Name') }}">
                            <input type="text" name="name" value="{{ $p->name }}" required aria-label="{{ __('Name') }}">
                            <input type="text" name="name_en" value="{{ $p->name_en }}" placeholder="{{ __('Name (EN, optional)') }}" aria-label="{{ __('Name (EN, optional)') }}" style="margin-top:.3rem">
                            @if($p->is_system)<span class="hint">{{ __('eingebaut') }} · <code>{{ $p->key }}</code></span>@else<span class="hint"><code>{{ $p->key }}</code></span>@endif
                        </td>
                        <td data-label="{{ __('Beteiligte Abteilungen') }}">
                            <div class="proc-checks" role="group" aria-label="{{ __('Beteiligte Abteilungen') }}">
                                @foreach($roles as $r)
                                    <label class="choice"><input type="checkbox" name="role_ids[]" value="{{ $r->id }}" @checked(in_array($r->id, array_map('intval', $p->role_ids ?? [])) || ($p->role_ids ?? []) === [])> {{ $r->displayName() }}</label>
                                @endforeach
                            </div>
                            <span class="hint">{{ __('Keine Auswahl = alle Abteilungen') }}</span>
                        </td>
                        <td data-label="{{ __('Aktiv') }}"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($p->active) aria-label="{{ __('Aktiv') }}"></td>
                        <td class="actions" style="white-space:nowrap">
                            <button class="primary small">{{ __('Speichern') }}</button>
                    </form>
                    @unless($p->is_system)
                        <form method="post" action="{{ route('processes.destroy', $p) }}" class="inline-form" style="margin-left:.4rem" onsubmit="return confirm(@js(__('Prozess „:name“ wirklich löschen?', ['name' => $p->name])))">
                            @csrf @method('DELETE')
                            <button class="primary small danger">{{ __('Löschen') }}</button>
                        </form>
                    @endunless
                        </td>
                </tr>
            @endforeach
        </table>
    </div>

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Prozess anlegen') }}</h2>
        <form method="post" action="{{ route('processes.store') }}">
            @csrf
            <div class="row">
                <div><label for="process-new-name">{{ __('Name') }}</label><input type="text" id="process-new-name" name="name" required placeholder="Azubi-Übernahme"></div>
                <div><label for="process-new-name-en">{{ __('Name (EN, optional)') }}</label><input type="text" id="process-new-name-en" name="name_en" placeholder="Apprentice takeover"></div>
                <div style="max-width:130px"><label for="process-new-order">{{ __('Reihenfolge') }}</label><input type="number" id="process-new-order" name="sort_order" value="10"></div>
            </div>
            <span class="group-label" id="process-new-roles">{{ __('Beteiligte Abteilungen') }}</span>
            <div class="proc-checks" role="group" aria-labelledby="process-new-roles">
                @foreach($roles as $r)
                    <label class="choice"><input type="checkbox" name="role_ids[]" value="{{ $r->id }}"> {{ $r->displayName() }}</label>
                @endforeach
            </div>
            <p class="hint">{{ __('Keine Auswahl = alle Abteilungen') }}</p>
            <button class="primary">{{ __('Anlegen') }}</button>
        </form>
    </div>
@endsection
