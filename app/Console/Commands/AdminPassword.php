<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Notfall: eigenes Admin-Konto anlegen oder sein Passwort neu setzen —
 * z. B. wenn das Verzeichnis nicht erreichbar ist oder niemand mehr hineinkommt.
 * Das neue Passwort ist ein Startpasswort (bei der Anmeldung ändern), laufende
 * Sitzungen des Kontos enden. --disable-2fa setzt zusätzlich die
 * Zwei-Faktor-Anmeldung dieses Kontos zurück (Handy verloren).
 *   docker exec crewgate php artisan crewgate:admin-password [benutzername] [--disable-2fa]
 * (alter Name crewgate:admin-passwort bleibt als Alias)
 *
 * Emergency: create an own (local) admin account or reset its password —
 * e.g. when the directory is unreachable or nobody can get in any more.
 * The new password is an initial password (change it at sign-in), running
 * sessions of the account end. --disable-2fa also resets the two-factor
 * sign-in of this account (phone lost).
 *   docker exec crewgate php artisan crewgate:admin-password [username] [--disable-2fa]
 * (the old name crewgate:admin-passwort remains as an alias)
 */
class AdminPassword extends Command
{
    protected $signature = 'crewgate:admin-password {username=admin}
        {--disable-2fa : auch die Zwei-Faktor-Anmeldung zurücksetzen / also reset two-factor sign-in}';

    /** Alter Name aus der Zeit vor Standard 10 / Old name from before standard 10 */
    protected $aliases = ['crewgate:admin-passwort'];

    protected $description = 'Legt ein eigenes Admin-Konto an bzw. setzt dessen Passwort neu (optional ohne Zwei-Faktor)';

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
            $user = new User([
                'username' => $name, 'name' => 'Administrator', 'auth_source' => User::LOCAL,
                'is_admin' => true, 'is_hr' => true, 'can_create' => true, 'active' => true,
            ]);
            $user->assignInitialPassword($new);
            $user->save();
            $this->info("Eigenes Admin-Konto „{$name}“ angelegt.");
        } else {
            $user->assignInitialPassword($new);
            $user->forceFill(['is_admin' => true, 'active' => true])->save();
            $this->info("Passwort für „{$name}“ neu gesetzt, Konto ist Administrator und aktiv.");
        }
        RateLimiter::clear('sign-in:' . $name);
        RateLimiter::clear('2fa:' . $user->id);

        $disable2fa = (bool) $this->option('disable-2fa');
        if ($disable2fa) {
            $user->disableTwoFactor();
            $this->info('Zwei-Faktor-Anmeldung zurückgesetzt. Ist sie für Administratoren Pflicht, wird sie nach der Anmeldung neu eingerichtet.');
        }
        ActivityLog::write(ActivityLog::TYPE_USER,
            'Notzugang: Passwort neu gesetzt' . ($disable2fa ? ', Zwei-Faktor zurückgesetzt' : '') . ': ' . $name, 'System');

        $this->line("Passwort: {$new}");
        $this->line($user->mustChangePassword()
            ? 'Bei der Anmeldung muss ein eigenes Passwort vergeben werden.'
            : 'Bitte gleich anmelden und unter „Mein Konto“ ein eigenes Passwort vergeben.');

        return self::SUCCESS;
    }
}
