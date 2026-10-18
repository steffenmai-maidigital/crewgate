<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Notfall: eigenes Admin-Konto anlegen oder sein Passwort neu setzen —
 * z. B. wenn das Verzeichnis nicht erreichbar ist oder niemand mehr hineinkommt.
 *   docker exec crewgate php artisan crewgate:admin-password
 * (alter Name crewgate:admin-passwort bleibt als Alias)
 *
 * Emergency: create an own (local) admin account or reset its password —
 * e.g. when the directory is unreachable or nobody can get in any more.
 *   docker exec crewgate php artisan crewgate:admin-password
 * (the old name crewgate:admin-passwort remains as an alias)
 */
class AdminPassword extends Command
{
    protected $signature = 'crewgate:admin-password {username=admin}';

    /** Alter Name aus der Zeit vor Standard 10 / Old name from before standard 10 */
    protected $aliases = ['crewgate:admin-passwort'];

    protected $description = 'Legt ein eigenes Admin-Konto an bzw. setzt dessen Passwort neu';

    public function handle(): int
    {
        $name = mb_strtolower(trim((string) $this->argument('username')));
        $user = User::where('username', $name)->first();

        if ($user && ! $user->isLocal()) {
            $this->error("„{$name}“ ist ein Verzeichniskonto — sein Passwort verwaltet das Verzeichnis. Bitte einen anderen Namen wählen.");

            return self::FAILURE;
        }

        $new = User::initialPassword();
        if (! $user) {
            $user = User::create([
                'username' => $name, 'name' => 'Administrator', 'auth_source' => User::LOCAL,
                'password' => $new, 'is_admin' => true, 'is_hr' => true, 'can_create' => true, 'active' => true,
            ]);
            $this->info("Eigenes Admin-Konto „{$name}“ angelegt.");
        } else {
            $user->forceFill(['password' => $new, 'is_admin' => true, 'active' => true])->save();
            $this->info("Passwort für „{$name}“ neu gesetzt, Konto ist Administrator und aktiv.");
        }
        RateLimiter::clear('sign-in:' . $name);

        $this->line("Passwort: {$new}");
        $this->line('Bitte gleich anmelden und unter Benutzer ein eigenes Passwort vergeben.');

        return self::SUCCESS;
    }
}
