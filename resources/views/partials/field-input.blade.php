{{-- Eingabeelement für ein konfigurierbares Feld (alle Feldarten).
     Optional: $prefill = [field_id => gespeicherter Wert] zur Vorbelegung.

     Input element for a configurable field (all field types).
     Optional: $prefill = [field_id => stored value] for prefilling. --}}
@php
    $stored = ($prefill ?? [])[$field->id] ?? null;
    $storedArray = in_array($field->type, ['multiselect', 'product']) ? (json_decode($stored ?? '[]', true) ?: []) : [];
@endphp
<label for="f{{ $field->id }}">{{ $field->displayLabel() }}@if($field->required) *@endif</label>

@if($field->type === 'select')
    <select id="f{{ $field->id }}" name="fields[{{ $field->id }}]" @if($field->required) required @endif>
        <option value="">{{ __('– bitte wählen –') }}</option>
        @foreach($field->options ?? [] as $opt)
            <option value="{{ $opt }}" @selected(old('fields.' . $field->id, $stored) === $opt)>{{ $opt }}</option>
        @endforeach
    </select>

@elseif($field->type === 'multiselect')
    <div class="choice-list">
        @foreach($field->options ?? [] as $opt)
            <label class="choice">
                <input type="checkbox" name="fields[{{ $field->id }}][]" value="{{ $opt }}"
                       @checked(in_array($opt, (array) old('fields.' . $field->id, $storedArray)))>
                {{ $opt }}
            </label>
        @endforeach
    </div>

@elseif($field->type === 'checkbox')
    <label class="choice">
        <input type="checkbox" name="fields[{{ $field->id }}]" value="1"
               @checked(old('fields.' . $field->id, $stored === \App\Models\CustomField::CHECKBOX_YES)) @if($field->required) required @endif>
        Ja
    </label>

@elseif($field->type === 'date')
    <input type="date" id="f{{ $field->id }}" name="fields[{{ $field->id }}]"
           value="{{ old('fields.' . $field->id, $stored) }}" @if($field->required) required @endif>

@elseif($field->type === 'product')
    @php
        // Produkte dieses Feldes (plus feldunabhängige), sortiert nach
        // Kategorie-Reihenfolge und innerhalb der Kategorie nach Name
        // Products of this field (plus field-independent ones), sorted by
        // category order and, within a category, by name
        $products = \App\Models\Product::with('category')
            ->where('active', true)
            ->where(fn ($q) => $q->where('custom_field_id', $field->id)->orWhereNull('custom_field_id'))
            ->get()
            ->sortBy(fn ($p) => [$p->category?->sort_order ?? 9999, $p->name]);
        $grouped = $products->groupBy(fn ($p) => $p->category?->id ?? 0);
    @endphp
    @if($products->isEmpty())
        <p class="hint">Für dieses Feld sind noch keine Produkte gepflegt (Menü → Produkte).</p>
    @else
        @foreach($grouped as $group)
            @php($cat = $group->first()->category)
            <div class="cat-head" @if($cat) style="border-left-color: {{ $cat->color }}" @endif>
                @if($cat)<span class="cat-dot" style="background: {{ $cat->color }}"></span>{{ $cat->displayName() }}@else {{ __('Ohne Kategorie') }} @endif
            </div>
            <div class="product-grid">
                @foreach($group as $p)
                    <label class="product-card" @if($p->category) style="border-top: 3px solid {{ $p->category->color }}" @endif>
                        <input type="checkbox" name="fields[{{ $field->id }}][]" value="{{ $p->id }}"
                               @checked(in_array($p->id, array_map('intval', (array) old('fields.' . $field->id, $storedArray))))>
                        @if($p->image_path)
                            <img src="{{ asset($p->image_path) }}" alt="{{ $p->displayName() }}">
                        @endif
                        <span class="p-name">{{ $p->displayName() }}</span>
                        <span class="p-meta">
                            @if($p->article_no)Art.: {{ $p->article_no }}<br>@endif
                            @if($p->manufacturer_no)Herst.: {{ $p->manufacturer_no }}<br>@endif
                            @if($p->price !== null)<strong>{{ $p->priceFormatted() }}</strong>@endif
                        </span>
                    </label>
                @endforeach
            </div>
        @endforeach
    @endif

@else {{-- Freitext / Free text --}}
    <input type="text" id="f{{ $field->id }}" name="fields[{{ $field->id }}]"
           value="{{ old('fields.' . $field->id, $stored) }}"
           @if($field->regex) pattern="{{ $field->regex }}" @endif
           @if($field->required) required @endif>
    @if($field->regex_hint)<p class="hint">{{ $field->regex_hint }}</p>@endif
@endif
