<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\Setting;
use Illuminate\Console\Command;

class PurgeCompletedOnboardings extends Command
{
    protected $signature = 'onboarding:purge';

    protected $description = 'Löscht Vorgänge nach Ablauf der konfigurierten Aufbewahrungsfrist (Einstellungen: Schalter + Monate).';

    public function handle(): int
    {
        // Schalter aus den Einstellungen (Standard: aktiv)
        // Switch from the settings (default: active)
        if (Setting::get('retention_enabled', '1') !== '1') {
            $this->info('Automatische Löschung ist in den Einstellungen deaktiviert – nichts zu tun.');
            return self::SUCCESS;
        }

        $months = (int) Setting::get('retention_months', '6');
        $months = max(1, min(120, $months));
        $cutoff = now()->subMonths($months);

        $candidates = Onboarding::whereIn('status', ['done', 'cancelled'])
            ->whereNotNull('completed_at')
            ->where('completed_at', '<=', $cutoff)
            ->get();

        foreach ($candidates as $onboarding) {
            // Löschung revisionssicher protokollieren, BEVOR gelöscht wird
            // Log the deletion in an audit-proof way BEFORE deleting
            ActivityLog::write(ActivityLog::TYPE_DELETION, ($onboarding->status === 'cancelled' ? 'Abgebrochener Vorgang nach Aufbewahrungsfrist automatisch gelöscht' : 'Vorgang nach Aufbewahrungsfrist automatisch gelöscht'), 'System', $onboarding, [
                'Prozess' => $onboarding->processLabel(),
                'Mitarbeiter'        => $onboarding->fullName(),
                'Abteilung'          => $onboarding->department ?? '–',
                'Angelegt am'        => $onboarding->created_at->format('d.m.Y H:i'),
                'Abgeschlossen am'   => $onboarding->completed_at->format('d.m.Y H:i'),
                'Aufbewahrung'       => $months . ' Monat(e)',
                'Gelöschte Feldwerte' => $onboarding->values()->count(),
                'Gelöschte Schritte' => $onboarding->steps()->count(),
            ]);

            $onboarding->delete(); // Werte & Schritte fallen per ON DELETE CASCADE mit / cascades to values & steps
        }

        $this->info($candidates->count() . ' Vorgang/Vorgänge gelöscht (Frist: ' . $months . ' Monate, Stichtag ' . $cutoff->format('d.m.Y') . ').');

        return self::SUCCESS;
    }
}
