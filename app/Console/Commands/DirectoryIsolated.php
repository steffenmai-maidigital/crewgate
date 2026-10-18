<?php

namespace App\Console\Commands;

use App\Support\Directory;
use Illuminate\Console\Command;

/**
 * Führt eine Verzeichnis-Abfrage in einem frischen PHP-Prozess aus. Wird nur
 * intern von App\Support\Directory aufgerufen, wenn sich die
 * Zertifikatseinstellungen geändert haben, während ein Webserver-Prozess läuft
 * (libldap hält den TLS-Kontext je Prozess). Ein- und Ausgabe als JSON.
 *
 * Runs a directory query in a fresh PHP process. Only called internally by
 * App\Support\Directory when the certificate settings changed while a web
 * server process is running (libldap keeps the TLS context per process).
 * Input and output as JSON.
 */
class DirectoryIsolated extends Command
{
    protected $signature = 'directory:isolated';

    protected $description = 'Intern: Verzeichnis-Abfrage in eigenem Prozess (JSON über stdin/stdout)';

    protected $hidden = true;

    public function handle(): int
    {
        $this->getOutput()->write(Directory::handleIsolated((string) stream_get_contents(STDIN)));

        return self::SUCCESS;
    }
}
