@extends('layout')
@section('title', __('Produkte'))
@section('content')
    <h1>Produkte (für Felder vom Typ „Produktauswahl")</h1>

    @if($productFields->isEmpty())
        <div class="panel">
            <p class="hint" style="margin:0">Für Ihre Rolle(n) gibt es noch kein Feld vom Typ „Produktauswahl". Legen Sie zuerst unter <a href="{{ route('admin.fields') }}">{{ __('Felder') }}</a> eines an – danach können Sie hier Produkte dafür pflegen.</p>
        </div>
    @endif

    @if($isAdmin)
    <div class="panel">
        <h2 style="margin-top:0">Kategorien (Reihenfolge bestimmt die Sortierung in der Produktauswahl)</h2>
        <table class="manage">
            <tr><th style="width:90px">{{ __('Reihenfolge') }}</th><th>{{ __('Name') }}</th><th style="width:90px">{{ __('Farbe') }}</th><th></th></tr>
            @forelse($categories as $c)
                <tr>
                    <form method="post" action="{{ route('admin.categories.update', $c) }}">
                        @csrf @method('PUT')
                        <td><input type="number" name="sort_order" value="{{ $c->sort_order }}" min="0"></td>
                        <td>
                            <input type="text" name="name" value="{{ $c->name }}" required>
                            <input type="text" name="name_en" value="{{ $c->name_en }}" placeholder="{{ __('Name (EN, optional)') }}" style="margin-top:.3rem">
                        </td>
                        <td><input type="color" name="color" value="{{ $c->color }}"></td>
                        <td style="white-space:nowrap">
                            <button class="primary small">{{ __('Speichern') }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.categories.destroy', $c) }}" class="inline-form" onsubmit="return confirm('Kategorie „{{ $c->name }}“ löschen? Produkte bleiben erhalten.')">
                        @csrf @method('DELETE')
                        <button class="primary small danger">{{ __('Löschen') }}</button>
                    </form>
                        </td>
                </tr>
            @empty
                <tr><td colspan="4" class="hint">Noch keine Kategorien.</td></tr>
            @endforelse
        </table>
        <form method="post" action="{{ route('admin.categories.store') }}">
            @csrf
            <div class="row">
                <div style="max-width:120px"><label>{{ __('Reihenfolge') }}</label><input type="number" name="sort_order" value="{{ ($categories->max('sort_order') ?? 0) + 1 }}" min="0"></div>
                <div><label>{{ __('Name') }}</label><input type="text" name="name" required placeholder="Möbel"></div>
                <div><label>{{ __('Name (EN, optional)') }}</label><input type="text" name="name_en" placeholder="Furniture"></div>
                <div style="max-width:110px"><label>{{ __('Farbe') }}</label><input type="color" name="color" value="#14557b"></div>
                <div style="flex:0"><button class="primary small" style="margin-top:1.75rem">{{ __('Anlegen') }}</button></div>
            </div>
        </form>
    </div>
    @else
        <div class="panel">
            <h2 style="margin-top:0">{{ __('Kategorien') }}</h2>
            <div class="pipeline">
                @forelse($categories as $c)
                    <span class="step"><span class="cat-dot" style="background: {{ $c->color }}; margin-right:.35rem"></span>{{ $c->displayName() }}</span>
                @empty
                    <span class="hint">Noch keine Kategorien.</span>
                @endforelse
            </div>
            <p class="hint" style="margin-bottom:0">Kategorien (Name, Farbe, Reihenfolge) werden von Administratoren gepflegt.</p>
        </div>
    @endif

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Produkte') }}</h2>
        @if($isAdmin)
            <form method="post" action="{{ route('admin.products.purge') }}" class="inline-form" onsubmit="return confirm('{{ $purgeable }} seit mind. 6 Monaten deaktivierte(s) Produkt(e) endgültig löschen?')">
                @csrf
                <button class="primary small danger" @disabled($purgeable === 0)>Deaktivierte Produkte bereinigen ({{ $purgeable }})</button>
            </form>
            <span class="hint">Löscht Produkte, die seit mindestens 6 Monaten deaktiviert sind. Die Bereinigung wird protokolliert.</span>
        @endif
        <table style="margin-top:.6rem">
            <tr><th style="width:70px">{{ __('Bild') }}</th><th>{{ __('Name') }}</th><th>{{ __('Feld (Rolle)') }}</th><th>{{ __('Kategorie') }}</th><th>{{ __('Artikel-Nr.') }}</th><th>{{ __('Hersteller-Nr.') }}</th><th style="width:100px">{{ __('Preis (€)') }}</th><th style="width:55px">{{ __('Aktiv') }}</th><th></th></tr>
            @forelse($products as $p)
                <tr>
                    <form method="post" action="{{ route('admin.products.update', $p) }}" enctype="multipart/form-data">
                        @csrf @method('PUT')
                        <td>
                            @if($p->image_path)
                                <img src="{{ asset($p->image_path) }}" alt="" class="product-thumb"><br>
                                <label class="hint" style="margin:0"><input type="checkbox" name="remove_image" value="1"> entf.</label>
                            @endif
                            <input type="file" name="image" accept=".png,.jpg,.jpeg,.webp" style="font-size:.7rem">
                        </td>
                        <td>
                            <input type="text" name="name" value="{{ $p->name }}" required>
                            <input type="text" name="name_en" value="{{ $p->name_en }}" placeholder="{{ __('Name (EN, optional)') }}" style="margin-top:.3rem">
                            @if(!$p->active && $p->deactivated_at)<span class="hint">deaktiviert seit {{ $p->deactivated_at->format('d.m.Y') }}</span>@endif
                        </td>
                        <td>
                            <select name="custom_field_id">
                                @if($isAdmin)<option value="">{{ __('– alle Felder –') }}</option>@endif
                                @foreach($productFields as $f)
                                    <option value="{{ $f->id }}" @selected($p->custom_field_id === $f->id)>{{ $f->label }} ({{ $f->role?->name }})</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <select name="category_id">
                                <option value="">{{ __('– keine –') }}</option>
                                @foreach($categories as $c)
                                    <option value="{{ $c->id }}" @selected($p->category_id === $c->id)>{{ $c->displayName() }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="article_no" value="{{ $p->article_no }}"></td>
                        <td><input type="text" name="manufacturer_no" value="{{ $p->manufacturer_no }}"></td>
                        <td><input type="number" name="price" value="{{ $p->price }}" step="0.01" min="0"></td>
                        <td style="text-align:center"><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" @checked($p->active)></td>
                        <td style="white-space:nowrap">
                            <button class="primary small">{{ __('Speichern') }}</button>
                    </form>
                    <form method="post" action="{{ route('admin.products.destroy', $p) }}" class="inline-form" onsubmit="return confirm('Produkt „{{ $p->name }}“ wirklich löschen?')">
                        @csrf @method('DELETE')
                        <button class="primary small danger">{{ __('Löschen') }}</button>
                    </form>
                        </td>
                </tr>
            @empty
                <tr><td colspan="9" class="hint">Noch keine Produkte angelegt.</td></tr>
            @endforelse
        </table>
        <p class="hint">Jedes Produkt ist einem Produktauswahl-Feld einer Rolle zugewiesen und wird von dieser Rolle gepflegt. Deaktivierte Produkte erscheinen nicht mehr in der Auswahl, bleiben aber in bereits ausgefüllten Vorgängen sichtbar.</p>
    </div>

    <div class="panel">
        <h2 style="margin-top:0">{{ __('Neues Produkt anlegen') }}</h2>
        <form method="post" action="{{ route('admin.products.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div><label>{{ __('Name') }}</label><input type="text" name="name" required placeholder="Höhenverstellbarer Schreibtisch"></div>
                <div><label>{{ __('Name (EN, optional)') }}</label><input type="text" name="name_en" placeholder="Height-adjustable desk"></div>
                <div>
                    <label>{{ __('Feld (Rolle)') }}</label>
                    <select name="custom_field_id" @if(!$isAdmin) required @endif>
                        @if($isAdmin)<option value="">{{ __('– alle Felder –') }}</option>@endif
                        @foreach($productFields as $f)
                            <option value="{{ $f->id }}">{{ $f->label }} ({{ $f->role?->name }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>{{ __('Kategorie') }}</label>
                    <select name="category_id">
                        <option value="">{{ __('– keine –') }}</option>
                        @foreach($categories as $c)
                            <option value="{{ $c->id }}">{{ $c->displayName() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div><label>{{ __('Artikelnummer') }}</label><input type="text" name="article_no" placeholder="A-10234"></div>
                <div><label>{{ __('Herstellernummer') }}</label><input type="text" name="manufacturer_no" placeholder="HM-88812"></div>
                <div style="max-width:140px"><label>{{ __('Preis (€)') }}</label><input type="number" name="price" step="0.01" min="0" placeholder="499.00"></div>
            </div>
            <label>Produktbild (PNG, JPG oder WebP, max. 2&nbsp;MB, optional)</label>
            <input type="file" name="image" accept=".png,.jpg,.jpeg,.webp">
            <label><input type="hidden" name="active" value="0"><input type="checkbox" name="active" value="1" checked> Aktiv</label>
            <button class="primary">{{ __('Produkt anlegen') }}</button>
        </form>
    </div>
@endsection
