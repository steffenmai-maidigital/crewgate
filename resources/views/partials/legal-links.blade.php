{{-- Impressum und Datenschutz unter der Anmeldung / Imprint (legal notice) and privacy policy below the sign-in --}}
@if($legalLinks = \App\Support\LegalPages::links())
    <p class="login-legal">
        @foreach($legalLinks as $type => $r)
            <a href="{{ $r['url'] }}" @if($r['external']) target="_blank" rel="noopener" @endif>{{ $type === 'imprint' ? '⚖️' : '🔒' }} {{ $r['title'] }}</a>
        @endforeach
    </p>
@endif
