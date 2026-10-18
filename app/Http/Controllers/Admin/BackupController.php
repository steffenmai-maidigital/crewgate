<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupService;

class BackupController extends Controller
{
    /**
     * „Jetzt sichern“: zusätzliche Sicherung mit Prüfsumme, unabhängig vom Zeitplan
     *
     * “Back up now”: additional backup with checksum, independent of the schedule
     */
    public function backupNow(BackupService $backup)
    {
        try {
            $file = $backup->backupNow($this->user()['username'] ?? null);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['backup' => __('Die Sicherung ist fehlgeschlagen: :fehler', ['fehler' => $e->getMessage()])]);
        }

        return back()->with('ok', __('Sicherung erstellt:') . ' ' . basename($file));
    }
}
