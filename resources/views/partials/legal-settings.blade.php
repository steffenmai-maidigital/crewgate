{{-- Einstellungen: Impressum und Datenschutz (innerhalb des Einstellungsformulars)

     Settings: imprint (legal notice) and privacy policy (inside the settings form) --}}
@php($R = \App\Support\LegalPages::class)
<div class="panel legal-settings">
    <h2 style="margin-top:0">{{ $number }} · ⚖️ {{ __('Impressum und Datenschutz') }}</h2>
    <p class="hint">{{ __('Eingeschaltete Seiten sind ohne Anmeldung erreichbar und stehen auf der Anmeldeseite und unten auf jeder Seite.') }}</p>
    @foreach($R::TYPES as $type)
        @php($typeChoice = old($type . '_type', \App\Models\Setting::get($type . '_type', 'text')))
        <fieldset class="legal" data-type="{{ $type }}">
            <legend>{{ $type === 'imprint' ? '⚖️' : '🔒' }} {{ $R::title($type) }}
                @if($R::enabled($type)) · <a href="{{ $R::url($type) }}" target="_blank" rel="noopener">{{ __('Ansehen') }}</a>@endif</legend>
            <label class="check"><input type="checkbox" name="{{ $type }}_enabled" value="1" @checked(old('_token') !== null ? old($type . '_enabled') : \App\Models\Setting::get($type . '_enabled', '0') === '1')>
                <strong>{{ __(':seite anzeigen', ['seite' => $R::title($type)]) }}</strong></label>
            <div class="legal-choice">
                <label class="check"><input type="radio" name="{{ $type }}_type" value="text" @checked($typeChoice !== 'link')> {{ __('Text hier eingeben') }}</label>
                <label class="check"><input type="radio" name="{{ $type }}_type" value="link" @checked($typeChoice === 'link')> {{ __('Auf eine vorhandene Seite verlinken (z. B. Firmen-Homepage)') }}</label>
            </div>
            <div class="legal-text">
                <textarea name="{{ $type }}_text" id="{{ $type }}_text" rows="10" maxlength="{{ $R::MAX }}"
                          placeholder="{{ $type === 'imprint' ? __('Name und Anschrift der Firma, vertreten durch …, Telefon, E-Mail, Registereintrag …') : __('Wer verantwortlich ist, welche Daten verarbeitet werden, wo sie liegen, wie lange, Rechte der Betroffenen …') }}">{{ old($type . '_text', $R::text($type)) }}</textarea>
                <p class="hint">{{ __('Einfacher Text: Leerzeile = neuer Absatz, eine Zeile mit „# “ davor = Überschrift. Internet- und E-Mail-Adressen werden automatisch zu Links.') }}</p>
                @if($type === 'privacy')
                    <button type="button" class="small" id="privacy-template" data-template="{{ $R::privacyTemplate() }}">📝 {{ __('Vorlage einfügen') }}</button>
                    <p class="hint">{{ __('Die Vorlage beschreibt, was die Anwendung tatsächlich speichert. Angaben in [eckigen Klammern] ersetzen und den Text vor dem Einschalten vom Datenschutzbeauftragten prüfen lassen.') }}</p>
                @endif
            </div>
            <div class="legal-url">
                <label for="{{ $type }}_url">{{ __('Adresse der Seite') }}</label>
                <input type="url" id="{{ $type }}_url" name="{{ $type }}_url" maxlength="500" value="{{ old($type . '_url', \App\Models\Setting::get($type . '_url')) }}"
                       placeholder="https://www.firma.de/{{ $type === 'imprint' ? 'impressum' : 'datenschutz' }}" autocapitalize="none" spellcheck="false">
            </div>
        </fieldset>
    @endforeach
</div>
<script>
    // Je nach Wahl Textfeld oder Adresse zeigen; Vorlage einfügen
    // Show a text field or an address depending on the choice; insert the template
    (function () {
        document.querySelectorAll('fieldset.legal').forEach(function (fs) {
            var show = function () {
                var link = fs.querySelector('input[type=radio][value=link]').checked;
                fs.querySelector('.legal-text').hidden = link;
                fs.querySelector('.legal-url').hidden = !link;
            };
            fs.querySelectorAll('input[type=radio]').forEach(function (r) { r.addEventListener('change', show); });
            show();
        });
        var button = document.getElementById('privacy-template');
        if (button) button.addEventListener('click', function () {
            var field = document.getElementById('privacy_text');
            if (field.value.trim() && !confirm(@json(__('Den vorhandenen Text durch die Vorlage ersetzen?')))) return;
            field.value = button.dataset.template;
            field.focus();
        });
    })();
</script>
