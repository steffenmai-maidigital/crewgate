{{-- Grafische Workflow-Darstellung mit Kreisen – vollständige Kette:
     Anlage (HR) → Vorbereitung (Vorgesetzter) → Abteilungen → Abschluss (HR).
     Parameter: $roles (Collection), optional $doneIds (quittierte Rollen-IDs),
     optional $big (große Variante), optional $onboarding (für Status je Schritt)

     Graphical workflow display with circles – complete chain:
     intake (HR) → preparation (supervisor) → departments → completion (HR).
     Parameters: $roles (collection), optional $doneIds (acknowledged role IDs),
     optional $big (large variant), optional $onboarding (for status per step) --}}
@php
    $doneIds = $doneIds ?? [];
    $o = $onboarding ?? null;
    $status = $o?->status;

    // Schritt 1: Anlage durch HR (bei konkretem Vorgang immer erledigt)
    // Step 1: intake by HR (always done for a concrete case)
    $steps = [[
        'label' => __('Anlage (HR)'),
        'done' => $o !== null,
        'current' => false,
        'title' => $o
            ? __('Angelegt am :d durch :u', ['d' => $o->created_at->format('d.m.Y'), 'u' => $o->created_by ?? 'HR'])
            : __('HR legt den Vorgang an'),
    ]];

    // Schritt 2: Vorbereitung durch den Vorgesetzten
    // Step 2: preparation by the supervisor
    $prepDone = in_array($status, ['open', 'review', 'done'], true);
    $steps[] = [
        'label' => __('Vorgesetzter'),
        'done' => $prepDone,
        'current' => $status === 'draft',
        'title' => $o
            ? trim(($o->supervisor_name ?? $o->supervisor_username ?? '') . ' – ' . ($prepDone ? __('Vorbereitung abgeschlossen') : ($status === 'draft' ? __('Vorbereitung läuft') : __('Vorbereitung'))), ' –')
            : __('Vorgesetzter pflegt die Angaben'),
    ];

    // Abteilungen (Quittierungen)
    // Departments (acknowledgements)
    foreach ($roles as $r) {
        $isDone = in_array($r->id, $doneIds);
        $steps[] = [
            'label' => $r->name,
            'done' => $isDone,
            'current' => $status === 'open' && ! $isDone,
            'title' => $o ? ($isDone ? __('quittiert') : __('Quittierung ausstehend')) : __('Abteilung quittiert den Erhalt'),
        ];
    }

    // Abschluss durch HR
    // Completion by HR
    $steps[] = [
        'label' => __('Abschluss (HR)'),
        'done' => $status === 'done',
        'current' => $status === 'review',
        'title' => match (true) {
            $status === 'done' => __('Abgeschlossen am :d', ['d' => $o?->completed_at?->format('d.m.Y') ?? '']),
            $status === 'review' => __('Wartet auf HR-Abschluss'),
            default => __('HR schließt den Vorgang ab'),
        },
    ];
@endphp
<div class="flow {{ !empty($big) ? 'big' : '' }} {{ $status === 'cancelled' ? 'cancelled' : '' }}">
    @if($roles->isEmpty())
        <span class="hint">{{ __('Noch keine Rollen im Workflow. Im Backend unter „Rollen“ pflegen.') }}</span>
    @else
        @foreach($steps as $i => $step)
            <div class="flow-step {{ $step['done'] ? 'done' : '' }} {{ $step['current'] ? 'current' : '' }}" title="{{ $step['title'] }}">
                <div class="flow-circle">{{ $step['done'] ? '✓' : $i + 1 }}</div>
                <div class="flow-label">{{ $step['label'] }}</div>
            </div>
            @if(!$loop->last)<div class="flow-line {{ $step['done'] ? 'done' : '' }}"></div>@endif
        @endforeach
        @if($status === 'cancelled')
            <span class="badge cancelled" style="margin-left:.5rem">{{ __('abgebrochen') }}</span>
        @endif
    @endif
</div>
