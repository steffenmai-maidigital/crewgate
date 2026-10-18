<?php

namespace App\Console\Commands;

use App\Services\LdapService;
use Illuminate\Console\Command;

class SyncAdGroups extends Command
{
    protected $signature = 'ad:sync-groups';

    protected $description = 'Liest die Gruppen des Verzeichnisses ein und legt sie als Rollen an.';

    public function handle(LdapService $ldap): int
    {
        if (! config('demo.enabled') && ! $ldap->enabled()) {
            $this->info('Kein Verzeichnis eingerichtet – nichts zu tun.');

            return self::SUCCESS;
        }
        try {
            $count = $ldap->syncGroupsToRoles();
        } catch (\Throwable $e) {
            $this->error('Gruppenabgleich fehlgeschlagen: ' . $e->getMessage());

            return self::FAILURE;
        }
        $this->info($count . ' neue Rolle(n) angelegt.');

        return self::SUCCESS;
    }
}
