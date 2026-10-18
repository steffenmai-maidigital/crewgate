{{-- Rua, der Fuchs-Helfer (eigene Zeichnung, kein Markenlogo; Figur: docs/RUA.md – Umhang mit Fibel, Runen-Amulett, ein Ohr schief, KEIN Hörnerhelm). Daten für fox.js im data-Attribut.
     Einbinden im Layout: @if(\App\Support\Fox::visible()) @include('partials.fox') @endif

     Fox helper (own drawing, no brand logo). Data for fox.js in the data attribute.
     Include in the layout as shown above. --}}
@php($foxData = \App\Support\Fox::display())
@php($foxMode = auth()->check() || session('ldap_user') ? 'page' : 'login')
<div class="fox on-{{ $foxMode }}" id="fox" hidden data-prefix="{{ $foxPrefix ?? 'fox' }}" data-fox='@json($foxData)'>
    <div class="fox-bubble" role="status" aria-live="polite" hidden>
        <p class="fox-text"></p>
        <div class="fox-bubble-buttons">
            <button type="button" class="fox-next">{{ $foxData['texts']['next'] }} ›</button>
            @if(! empty($foxData['link']))
                <a class="fox-link" href="{{ $foxData['link']['url'] }}" target="_blank" rel="noopener">{{ $foxData['link']['text'] }} ↗</a>
            @endif
            <button type="button" class="fox-sleep">💤 {{ $foxData['texts']['sleep'] }}</button>
        </div>
        <button type="button" class="fox-close" aria-label="{{ $foxData['texts']['close'] }}">×</button>
    </div>
    <button type="button" class="fox-figure" aria-label="{{ $foxData['texts']['fox'] }}" title="{{ $foxData['texts']['fox'] }}">
        <svg viewBox="0 0 120 120" width="96" height="96" aria-hidden="true">
            <ellipse cx="60" cy="112" rx="34" ry="5" fill="#000" opacity=".12"/>
            <g class="f-tail">
                <path d="M72 96 C 98 104 118 86 112 60 C 108 44 94 42 94 54 C 94 68 92 82 72 86 Z" fill="#f2711c"/>
                <path d="M112 60 C 108 44 94 42 94 54 C 98 52 104 54 107 62 C 109 66 111 64 112 60 Z" fill="#fff6ec"/>
            </g>
            <g class="f-body">
                <ellipse cx="58" cy="88" rx="24" ry="21" fill="#f37b1d"/>
                <ellipse cx="58" cy="93" rx="12.5" ry="14" fill="#fff6ec"/>
                <!-- Ruas Umhang: grob gewebt, vorn offen, damit die weiße Brust bleibt
                     Rua's cloak: coarsely woven, open at the front so the white chest stays -->
                <g class="f-cloak">
                    <path d="M41 73 C 30 79 29 96 35 109 L 46 107 C 42 96 44 84 51 77 Z" fill="#4f6b5a"/>
                    <path d="M75 73 C 86 79 87 96 81 109 L 70 107 C 74 96 72 84 65 77 Z" fill="#4f6b5a"/>
                    <path d="M40 73 Q 58 82 76 73 L 74 79.5 Q 58 87.5 42 79.5 Z" fill="#5d7b68"/>
                    <path d="M36 86 L 44 85 M35 94 L 43 93 M36 102 L 44 101 M80 86 L 72 85 M81 94 L 73 93 M80 102 L 72 101" stroke="#3c5446" stroke-width="1.2" stroke-linecap="round" stroke-dasharray="1.6 1.8" fill="none"/>
                </g>
                <!-- Runen-Amulett (Rune Raidō: Reise) / rune amulet (rune Raidō: journey) -->
                <path d="M52.5 81 Q 58 90 63.5 81" stroke="#7a5a3a" stroke-width="1" fill="none"/>
                <rect x="54.4" y="89.5" width="7.2" height="9" rx="2.4" fill="#a7b0b5" stroke="#5f686d" stroke-width="1"/>
                <path d="M56.6 91.5 v5.2 M56.6 91.5 h1.7 a1.3 1.3 0 0 1 0 2.6 h-1.7 M58.1 94.1 l1.6 2.6" stroke="#3b4246" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                <!-- runde Fibel hält den Umhang / round brooch holds the cloak -->
                <circle cx="58" cy="79.5" r="4.6" fill="#d9b03a" stroke="#7a5a10" stroke-width="1.3"/>
                <circle cx="58" cy="79.5" r="2" fill="none" stroke="#7a5a10" stroke-width="1"/>
                <ellipse cx="47" cy="106" rx="7.5" ry="5" fill="#5a3a2a"/>
                <ellipse cx="69" cy="106" rx="7.5" ry="5" fill="#5a3a2a"/>
            </g>
            <g class="f-paw">
                <path d="M38 84 C 30 78 26 70 24 64" stroke="#f37b1d" stroke-width="9" stroke-linecap="round" fill="none"/>
                <circle cx="24" cy="62" r="5.5" fill="#5a3a2a"/>
            </g>
            <g class="f-head">
                <g class="f-ear-l"><path d="M34 40 L 28 10 L 52 28 Z" fill="#f37b1d"/><path d="M36 35 L 32 17 L 47 29 Z" fill="#5a3a2a"/></g>
                <g class="f-ear-r"><g transform="rotate(11 76 34)"><path d="M82 40 L 88 10 L 64 28 Z" fill="#f37b1d"/><path d="M80 35 L 84 17 L 69 29 Z" fill="#5a3a2a"/></g></g>
                <path d="M28 48 C 28 31 42 24 58 24 C 74 24 88 31 88 48 C 88 64 74 74 58 76 C 42 74 28 64 28 48 Z" fill="#f37b1d"/>
                <path d="M33 54 C 42 55 51 61 58 72 C 65 61 74 55 83 54 C 80 66 70 75 58 76 C 46 75 36 66 33 54 Z" fill="#fff6ec"/>
                <circle cx="41" cy="58" r="4" fill="#ff9a8a" opacity=".55"/>
                <circle cx="75" cy="58" r="4" fill="#ff9a8a" opacity=".55"/>
                <g class="f-eyes">
                    <ellipse cx="47" cy="47" rx="4.2" ry="5.2" fill="#2b1d16"/>
                    <ellipse cx="69" cy="47" rx="4.2" ry="5.2" fill="#2b1d16"/>
                    <circle cx="48.5" cy="45" r="1.5" fill="#fff"/>
                    <circle cx="70.5" cy="45" r="1.5" fill="#fff"/>
                </g>
                <g class="f-sleep-eyes">
                    <path d="M43 48 Q 47 51 51 48" stroke="#2b1d16" stroke-width="2.2" fill="none" stroke-linecap="round"/>
                    <path d="M65 48 Q 69 51 73 48" stroke="#2b1d16" stroke-width="2.2" fill="none" stroke-linecap="round"/>
                </g>
                <ellipse cx="58" cy="63" rx="4.2" ry="3.2" fill="#2b1d16"/>
                <path class="f-mouth" d="M53 67 Q 58 71.5 63 67" stroke="#2b1d16" stroke-width="2" fill="none" stroke-linecap="round"/>
            </g>
            <g class="f-zzz">
                <text class="f-z1" x="84" y="30" font-size="12" font-weight="800">z</text>
                <text class="f-z2" x="92" y="20" font-size="15" font-weight="800">z</text>
                <text class="f-z3" x="101" y="8" font-size="19" font-weight="800">Z</text>
            </g>
        </svg>
    </button>
</div>
<script src="{{ asset('js/fox.js') }}?v={{ config('version.build') }}" defer></script>
