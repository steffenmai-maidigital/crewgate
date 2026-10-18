<?php

use App\Services\BackupService;
use Illuminate\Support\Facades\Schedule;

// Datensätze 6 Monate nach Fertigstellung automatisch löschen
// Automatically delete records 6 months after completion
Schedule::command('onboarding:purge')->dailyAt('02:00');

// Nächtliche Sicherung von Datenbank und Uploads nach ./backups (Standard 04)
// Nightly backup of the database and uploads to ./backups (Standard 04)
Schedule::call(fn () => app(BackupService::class)->runScheduledBackup())
    ->dailyAt('02:30')->name('nightly-backup');

// Rollen nächtlich mit AD-Gruppen abgleichen
// Sync roles with AD groups every night
Schedule::command('ad:sync-groups')->dailyAt('03:00');

// Fristen prüfen: Erinnerungen an Vorgesetzte und (wenn aktiviert) Abteilungen
// Check due dates: reminders to supervisors and (if enabled) departments
Schedule::command('reminders:send')->dailyAt('07:00');

// Monatlicher Audit-Bericht per Mail an die Auditoren (Schalter in den
// Einstellungen; läuft am 1. des Monats um 06:00 Uhr)
// Monthly audit report by mail to the auditors (switch in the
// settings; runs on the 1st of the month at 06:00)
Schedule::command('audit:report')->monthlyOn(1, '06:00');
