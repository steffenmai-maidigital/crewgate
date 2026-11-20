<?php

namespace App\Services;

use App\Models\ActivityLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Sicherung der Datenbank, des Schlüssels .app_key und der Uploads (Logo,
 * Produktbilder) — Standard 04.
 *
 * - jede Nacht um 02:30 Uhr in den Sicherungsordner (Compose: ./backups)
 * - Aufbewahrung BACKUP_KEEP_DAYS (Standard 14, mindestens 4 Tage)
 * - „Jetzt sichern“: zusätzliche Sicherung unter backups/manual, wird nie
 *   automatisch gelöscht
 * - zu jeder Sicherung eine SHA-256-Prüfsumme
 *
 * Die Sicherungen bleiben auf dem Server; einen Download im Browser gibt es
 * nicht (käme einer dazu: ohne .app_key). Sie enthalten Personaldaten und den
 * Schlüssel — den Ordner nur Administratoren zugänglich machen.
 *
 * Backup of the database, the key .app_key and the uploads (logo, product
 * images) — Standard 04.
 *
 * - every night at 02:30 into the backup folder (Compose: ./backups)
 * - retention BACKUP_KEEP_DAYS (default 14, at least 4 days)
 * - “Back up now”: additional backup under backups/manual, never
 *   deleted automatically
 * - a SHA-256 checksum for every backup
 *
 * The backups stay on the server; there is no download in the browser (if
 * one were added: without .app_key). They contain personnel data and the
 * key — make the folder accessible to administrators only.
 */
class BackupService
{
    public function runScheduledBackup(): string
    {
        $path = $this->createArchive($this->basePath(), 'System');
        $this->cleanup();

        return $path;
    }

    /** @param string|null $by Benutzername für das Protokoll / username for the log */
    public function backupNow(?string $by = null): string
    {
        return $this->createArchive($this->basePath() . '/manual', $by);
    }

    public function basePath(): string
    {
        return rtrim((string) config('backup.path', '/backups'), '/');
    }

    /**
     * Datei mit dem APP_KEY, die der Entrypoint neben der Datenbank anlegt
     * (fehlt, wenn APP_KEY aus der Umgebung kommt – dann steht er in der Compose-Datei)
     *
     * File with the APP_KEY that the entrypoint creates next to the database
     * (missing if APP_KEY comes from the environment – then it is in the Compose file)
     */
    public function keyFile(): string
    {
        return dirname((string) config('database.connections.sqlite.database')) . '/.app_key';
    }

    private function createArchive(string $dir, ?string $by): string
    {
        File::ensureDirectoryExists($dir);

        $stamp = now()->format('Ymd_His');
        $target = "$dir/crewgate_backup_{$stamp}.zip";

        // SQLite konsistent sichern: VACUUM INTO statt Dateikopie
        // Back up SQLite consistently: VACUUM INTO instead of a file copy
        $tmpDb = sys_get_temp_dir() . "/crewgate_{$stamp}.sqlite";
        DB::statement('VACUUM INTO ' . DB::getPdo()->quote($tmpDb));

        $zip = new \ZipArchive();
        if ($zip->open($target, \ZipArchive::CREATE) !== true) {
            File::delete($tmpDb);
            throw new \RuntimeException("Sicherung konnte nicht angelegt werden: $target");
        }
        $zip->addFile($tmpDb, 'database/database.sqlite');
        // Schlüssel neben der Datenbank (/data/.app_key): ohne ihn sind das
        // Servicekonto-Passwort und die Zwei-Faktor-Geheimnisse nach einer
        // Wiederherstellung auf neuer Hardware unlesbar
        // Key next to the database (/data/.app_key): without it the service
        // account password and the two-factor secrets cannot be read after a
        // restore on new hardware
        $key = $this->keyFile();
        if (is_file($key) && is_readable($key)) {
            $zip->addFile($key, '.app_key');
        } elseif (is_file($key)) {
            logger()->warning('Sicherung ohne .app_key: Datei nicht lesbar / backup without .app_key: file not readable', ['file' => $key]);
        }
        if (File::isDirectory(public_path('uploads'))) {
            foreach (File::allFiles(public_path('uploads')) as $file) {
                $zip->addFile($file->getPathname(), 'uploads/' . $file->getRelativePathname());
            }
        }
        $zip->close();
        File::delete($tmpDb);
        // Enthält den App-Schlüssel: nicht für alle lesbar (wie LinkHub, Standard 04)
        // Holds the app key: not readable by everyone (like LinkHub, standard 04)
        @chmod($target, 0640);

        File::put($target . '.sha256', hash_file('sha256', $target) . '  ' . basename($target) . PHP_EOL);

        ActivityLog::write(ActivityLog::TYPE_SETTINGS, ($by === 'System' ? 'Sicherung erstellt: ' : 'Manuelle Sicherung erstellt: ') . basename($target), $by);

        return $target;
    }

    private function cleanup(): void
    {
        $limit = CarbonImmutable::now()->subDays((int) config('backup.days', 14));

        foreach (File::files($this->basePath()) as $file) {
            if (CarbonImmutable::createFromTimestamp($file->getMTime())->lessThan($limit)) {
                File::delete($file->getPathname());
            }
        }
    }
}
