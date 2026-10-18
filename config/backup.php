<?php

// Nächtliche Sicherung (Standard 04): Ordner (im Compose ./backups) und
// Aufbewahrung in Tagen (harte Untergrenze 4 Tage)
// Nightly backup (Standard 04): folder (./backups in Compose) and
// retention in days (hard minimum of 4 days)
return [
    'path' => env('BACKUP_PATH', '/backups'),
    'days' => max(4, (int) env('BACKUP_KEEP_DAYS', 14)),
];
