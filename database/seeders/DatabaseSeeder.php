<?php

namespace Database\Seeders;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\DemoDirectory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Grunddaten beim Start (docker/entrypoint.sh): der erste Administrator als
 * eigenes Konto — damit CrewGate auch ohne Verzeichnis bedienbar ist und es
 * immer einen Notzugang gibt. Demo-Konten legt `demo:seed` an. Außerhalb des
 * Demo-Modus verlieren übrig gebliebene Demo-Konten ihr Passwort „demo“.
 * Ein zufälliges Startpasswort muss bei der ersten Anmeldung geändert werden.
 *
 * Initial data at start-up (docker/entrypoint.sh): the first administrator as
 * an own (local) account — so that CrewGate can be used without a directory
 * and there is always emergency access. Demo accounts are created by
 * `demo:seed`. Outside demo mode, leftover demo accounts lose their password "demo".
 * A random initial password must be changed at the first sign-in.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (config('demo.enabled')) {
            return;
        }
        $this->secureDemoAccounts();
        if (User::exists()) {
            return;
        }

        // Ohne ADMIN_PASSWORD ein zufälliges Startpasswort — kein bekanntes
        // „admin/admin“. Es steht einmalig im Container-Protokoll.
        // Without ADMIN_PASSWORD a random initial password — no well-known
        // "admin/admin". It appears once in the container log.
        $password = (string) env('ADMIN_PASSWORD', '');
        $random = $password === '';
        if ($random) {
            $password = User::initialPassword();
        }
        $name = mb_strtolower((string) env('ADMIN_USERNAME', 'admin')) ?: 'admin';

        User::create([
            'username'    => $name,
            'name'        => 'Administrator',
            'auth_source' => User::LOCAL,
            'password'    => $password,
            // Zufallspasswort aus dem Protokoll: bei der ersten Anmeldung ändern
            // Random password from the log: change it at the first sign-in
            'must_change_password' => $random,
            'is_admin'    => true,
            'is_hr'       => true,
            'can_create'  => true,
            'active'      => true,
        ]);

        $this->command?->info("Administrator „{$name}“ als eigenes Konto angelegt.");
        if ($random) {
            $this->command?->warn('==============================================================');
            $this->command?->warn("  Erstes Admin-Passwort für „{$name}“: {$password}");
            $this->command?->warn('  Bei der ersten Anmeldung muss ein eigenes vergeben werden.');
            $this->command?->warn('  Es wird nicht erneut angezeigt.');
            $this->command?->warn('==============================================================');
        }
    }

    /**
     * Wer erst im Demo-Modus startet und dann auf Normalbetrieb umstellt, hätte
     * sonst Konten wie max.admin mit dem bekannten Passwort „demo“. Jedes eigene
     * Demo-Konto, dessen Passwort noch „demo“ ist, bekommt ein neues
     * Startpasswort (einmal im Container-Protokoll); Konten ohne Admin-Recht
     * werden zusätzlich gesperrt. Andere Passwörter bleiben unangetastet.
     *
     * Whoever starts in demo mode first and then switches to normal operation
     * would otherwise keep accounts such as max.admin with the well-known
     * password "demo". Every own demo account whose password is still "demo"
     * gets a new initial password (once in the container log); accounts without
     * the admin right are locked as well. Other passwords stay untouched.
     */
    private function secureDemoAccounts(): void
    {
        $users = User::whereIn('username', array_keys(DemoDirectory::users()))
            ->where('auth_source', User::LOCAL)->get();
        foreach ($users as $user) {
            if (! $user->password || ! Hash::check(DemoDirectory::PASSWORD, $user->password)) {
                continue;
            }
            $password = User::initialPassword();
            // Neues Startpasswort: bei der nächsten Anmeldung ändern
            // New initial password: change it at the next sign-in
            $user->assignInitialPassword($password);
            if (! $user->is_admin) {
                $user->active = false;
            }
            $user->save();
            ActivityLog::write(ActivityLog::TYPE_USER, 'Demo-Konto nach dem Demo-Modus gesichert (neues Startpasswort'
                . ($user->active ? '' : ', gesperrt') . '): ' . $user->username);

            $this->command?->warn('==============================================================');
            $this->command?->warn("  Demo-Konto „{$user->username}“ hatte noch das Passwort „demo“.");
            $this->command?->warn("  Neues Startpasswort: {$password}" . ($user->active ? '' : ' (Konto gesperrt)'));
            $this->command?->warn('  Demo account still had the password "demo" – new initial password above.');
            $this->command?->warn('==============================================================');
        }
    }
}
