<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Letzte Fehler aus dem Anwendungsprotokoll für die Hilfe-Seite (nur Admins).
 * Berücksichtigt nur Einträge der letzten 30 Tage; lässt sich leeren.
 *
 * Latest errors from the application log for the help page (admins only).
 * Only considers entries from the last 30 days; can be cleared.
 */
class ErrorLog
{
    public const DAYS = 30;

    /**
     * @return string[] Protokolldateien (laravel.log bzw. laravel-JJJJ-MM-TT.log)
     *         / log files (laravel.log or laravel-YYYY-MM-DD.log)
     */
    public static function files(): array
    {
        return glob(storage_path('logs/laravel*.log')) ?: [];
    }

    /**
     * @return array{lines: string[], count: int} die letzten $max Fehler und die Gesamtzahl
     *         / the last $max errors and the total count
     */
    public static function recent(int $max = 3): array
    {
        $limit = now()->subDays(self::DAYS);
        $hits = [];

        foreach (self::files() as $file) {
            if (filemtime($file) < $limit->timestamp) {
                continue;   // ganze Datei älter als 30 Tage / whole file older than 30 days
            }
            // nur das Datei-Ende lesen (Protokolle können groß sein)
            // only read the end of the file (logs can be large)
            $fh = @fopen($file, 'r');
            if (! $fh) {
                continue;
            }
            fseek($fh, max(0, filesize($file) - 262144));
            $tail = stream_get_contents($fh) ?: '';
            fclose($fh);

            foreach (explode("\n", $tail) as $line) {
                if (! str_contains($line, '.ERROR:')
                    || ! preg_match('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $line, $m)) {
                    continue;
                }
                $time = Carbon::parse($m[1]);
                if ($time->lt($limit)) {
                    continue;
                }
                $hits[] = [$time->timestamp, mb_substr($line, 0, 420)];
            }
        }

        usort($hits, fn ($a, $b) => $a[0] <=> $b[0]);
        return [
            'lines' => array_column(array_slice($hits, -$max), 1),
            'count' => count($hits),
        ];
    }

    /** Alle Protokolldateien leeren (die Dateien bleiben bestehen) / Empty all log files (the files remain) */
    public static function clear(): void
    {
        foreach (self::files() as $file) {
            @file_put_contents($file, '');
        }
    }
}
