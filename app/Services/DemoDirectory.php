<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;

/**
 * Demo-Verzeichnis für den Demo-Modus (DEMO_MODE=true): Die Konten werden
 * als eigene Konten in der Datenbank angelegt (Passwort für alle: „demo“),
 * die Gruppen als Rollen. Ein echtes Verzeichnis ist nicht nötig.
 *
 * Demo directory for demo mode (DEMO_MODE=true): the accounts are created
 * as own accounts in the database (password for all: “demo”),
 * the groups as roles. A real directory is not needed.
 */
class DemoDirectory
{
    public const PASSWORD = 'demo';

    public const GROUP_HR       = 'CN=HR,OU=Demo,DC=demo,DC=local';
    public const GROUP_ADMIN    = 'CN=Onboarding-Admins,OU=Demo,DC=demo,DC=local';
    public const GROUP_IT       = 'CN=IT,OU=Demo,DC=demo,DC=local';
    public const GROUP_PURCHASING = 'CN=Einkauf,OU=Demo,DC=demo,DC=local';
    public const GROUP_FACILITY = 'CN=Facility,OU=Demo,DC=demo,DC=local';
    public const GROUP_AUDITOR  = 'CN=Auditoren,OU=Demo,DC=demo,DC=local';

    /** Demo-Gruppen für den Rollen-Sync: DN => Anzeigename / Demo groups for the role sync: DN => display name */
    public static function groups(): array
    {
        return [
            self::GROUP_IT       => 'IT',
            self::GROUP_PURCHASING => 'Einkauf',
            self::GROUP_FACILITY => 'Facility',
        ];
    }

    /** Demo-Benutzer, Schlüssel = Benutzername / Demo users, key = username */
    public static function users(): array
    {
        return [
            'anna.hr'        => ['name' => 'Anna Hartmann (HR)', 'mail' => 'anna.hr@demo.local',        'department' => 'Personal',   'groups' => [self::GROUP_HR]],
            'max.admin'      => ['name' => 'Max Admin',          'mail' => 'max.admin@demo.local',      'department' => 'IT',         'groups' => [self::GROUP_ADMIN, self::GROUP_HR, self::GROUP_IT]],
            'petra.chef'     => ['name' => 'Petra Chefin',       'mail' => 'petra.chef@demo.local',     'department' => 'Vertrieb',   'groups' => []],
            'karl.chef'      => ['name' => 'Karl Vorgesetzt',    'mail' => 'karl.chef@demo.local',      'department' => 'Produktion', 'groups' => []],
            'tim.it'         => ['name' => 'Tim Techniker',      'mail' => 'tim.it@demo.local',         'department' => 'IT',         'groups' => [self::GROUP_IT]],
            'eva.einkauf'    => ['name' => 'Eva Einkauf',        'mail' => 'eva.einkauf@demo.local',    'department' => 'Einkauf',    'groups' => [self::GROUP_PURCHASING]],
            'rita.audit'     => ['name' => 'Rita Auditorin',     'mail' => 'rita.audit@demo.local',     'department' => 'Revision',   'groups' => [self::GROUP_AUDITOR]],
            'frank.facility' => ['name' => 'Frank Facility',     'mail' => 'frank.facility@demo.local', 'department' => 'Facility',   'groups' => [self::GROUP_FACILITY]],
        ];
    }

    /**
     * Demo-Konten als eigene Konten anlegen (idempotent; Passwort bleibt „demo“)
     *
     * Create the demo accounts as own accounts (idempotent; password stays “demo”)
     */
    public static function seedUsers(): int
    {
        $count = 0;
        foreach (self::users() as $username => $u) {
            $user = User::updateOrCreate(['username' => $username], [
                'name'        => $u['name'],
                'email'       => $u['mail'],
                'department'  => $u['department'],
                'auth_source' => User::LOCAL,
                'password'    => self::PASSWORD,
                'is_hr'       => in_array(self::GROUP_HR, $u['groups'], true),
                'is_admin'    => in_array(self::GROUP_ADMIN, $u['groups'], true),
                'is_auditor'  => in_array(self::GROUP_AUDITOR, $u['groups'], true),
                'can_create'  => in_array(self::GROUP_HR, $u['groups'], true),
                'active'      => true,
            ]);
            $roles = Role::whereIn('ad_group_dn', $u['groups'])->pluck('id')->all();
            $user->roles()->sync($roles);
            $count++;
        }

        return $count;
    }
}
