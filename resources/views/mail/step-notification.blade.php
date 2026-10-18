<p>Guten Tag,</p>
<p>für den neuen Mitarbeiter <strong>{{ $onboarding->fullName() }}</strong>
    (Abteilung: {{ $onboarding->department ?? 'nicht gepflegt' }},
    Eintritt: {{ $onboarding->start_date?->format('d.m.Y') ?? 'offen' }})
    liegt jetzt eine Aufgabe bei <strong>{{ $role->name }}</strong>.</p>
<p>Bitte im Onboarding-System anmelden und die Felder für Ihre Abteilung ausfüllen:</p>
<p><a href="{{ route('onboardings.show', $onboarding) }}">{{ route('onboardings.show', $onboarding) }}</a></p>
<p>Diese Nachricht wurde automatisch an die zentrale Adresse der Rolle gesendet.</p>
