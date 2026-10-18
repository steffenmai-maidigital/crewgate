{{-- Footer nach Standard 03: 1 eigener Text · 2 Produkt, Version, Lizenz, Links · 3 Macher-Zeile

     Footer according to Standard 03: 1 custom text · 2 product, version, licence, links · 3 maker line --}}
@php
    $footerText = \App\Models\Setting::get('brand_footer_text');
    $source = \App\Support\ProductInfo::sourceUrl();
@endphp
<footer class="bottom">
    @if($footerText)<div>{{ $footerText }}</div>@endif
    <div>
        {{ \App\Support\ProductInfo::name() }} {{ \App\Support\ProductInfo::edition() }} {{ config('version.number') }}
        · @if($source)<a href="{{ $source }}" target="_blank" rel="noopener">{{ __('freie Software (AGPL-3.0)') }}</a>@else{{ __('freie Software (AGPL-3.0)') }}@endif
        @if($signedIn ?? false) · <a href="{{ route('help') }}">❓ {{ __('Hilfe') }}</a>@endif
        @foreach(\App\Support\LegalPages::links() as $r)
            · <a href="{{ $r['url'] }}" @if($r['external']) target="_blank" rel="noopener" @endif>{{ $r['title'] }}</a>
        @endforeach
    </div>
    <div class="maker">
        <a href="{{ \App\Support\ProductInfo::projectUrl() }}" target="_blank" rel="noopener">
            <img src="{{ asset('img/mai-digital.svg') }}" alt="" width="22" height="22">
            <span>Steffen Mai · www.mai-digital.de</span>
        </a>
    </div>
</footer>
