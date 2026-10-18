<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Grunddaten beim Start (docker/entrypoint.sh): der erste Administrator als
 * eigenes Konto — damit CrewGate auch ohne Verzeichnis bedienbar ist und es
 * immer einen Notzugang gibt. Demo-Konten legt `demo:seed` an.
 *
 * Initial data at start-up (docker/entrypoint.sh): the first administrator as
 * an own (local) account — so that CrewGate can be used without a directory
 * and there is always emergency access. Demo accounts are created by `demo:seed`.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (User::exists() || config('demo.enabled')) {
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
            'is_admin'    => true,
            'is_hr'       => true,
            'can_create'  => true,
            'active'      => true,
        ]);

        $this->command?->info("Administrator „{$name}“ als eigenes Konto angelegt.");
        if ($random) {
            $this->command?->warn('==============================================================');
            $this->command?->warn("  Erstes Admin-Passwort für „{$name}“: {$password}");
            $this->command?->warn('  Bitte gleich anmelden und unter Benutzer ein eigenes vergeben.');
            $this->command?->warn('  Es wird nicht erneut angezeigt.');
            $this->command?->warn('==============================================================');
        }
    }
}
