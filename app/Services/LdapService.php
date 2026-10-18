<?php

namespace App\Services;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Directory;

/**
 * Benutzersuche, Gruppenabgleich und Auditoren-Adressen — aus den eigenen
 * Konten und, wenn eingeschaltet, aus dem Verzeichnis (App\Support\Directory).
 * Die Anmeldung selbst läuft im AuthController.
 *
 * User search, group sync and auditor addresses — from the own
 * accounts and, if switched on, from the directory (App\Support\Directory).
 * The sign-in itself runs in the AuthController.
 */
class LdapService
{
    /** Ist ein Verzeichnis eingerichtet und eingeschaltet? / Is a directory set up and switched on? */
    public function enabled(): bool
    {
        return Directory::enabled();
    }

    /**
     * Benutzer finden (z. B. Vorgesetzter): zuerst im Verzeichnis, sonst
     * unter den eigenen Konten. Liefert username, name, mail, department,
     * manager_dn oder null.
     *
     * Find a user (e.g. supervisor): first in the directory, otherwise
     * among the own accounts. Returns username, name, mail, department,
     * manager_dn or null.
     */
    public function findUser(string $usernameOrName): ?array
    {
        $text = trim($usernameOrName);
        if ($text === '') {
            return null;
        }

        if ($v = Directory::fromSettings()) {
            if ($e = $v->find($text)) {
                return [
                    'username'   => $e['username'],
                    'name'       => $e['name'],
                    'mail'       => $e['mail'],
                    'department' => $e['department'],
                    'manager_dn' => $e['manager_dn'],
                ];
            }
        }

        $user = User::where('active', true)
            ->where(fn ($q) => $q->where('username', mb_strtolower($text))
                ->orWhere('email', mb_strtolower($text))
                ->orWhere('name', 'like', '%' . $text . '%'))
            ->orderBy('name')
            ->first();

        return $user ? [
            'username'   => $user->username,
            'name'       => $user->name,
            'mail'       => $user->email,
            'department' => $user->department,
            'manager_dn' => null,
        ] : null;
    }

    /**
     * Gruppen aus dem Verzeichnis als Rollen anlegen bzw. umbenennen.
     * Optional per Filter (Einstellung ldap_group_filter, z. B. "OB-").
     * Im Demo-Modus kommen die Rollen aus dem Demo-Verzeichnis.
     *
     * Create or rename groups from the directory as roles.
     * Optionally via filter (setting ldap_group_filter, e.g. "OB-").
     * In demo mode the roles come from the demo directory.
     */
    public function syncGroupsToRoles(): int
    {
        if (config('demo.enabled')) {
            return $this->syncDemoGroups();
        }
        $v = Directory::fromSettings();
        if (! $v) {
            throw new \RuntimeException(__('Es ist kein Verzeichnis eingerichtet (Einstellungen → Anmeldung).'));
        }

        $count = 0;
        $maxOrder = (int) Role::max('sort_order');

        foreach ($v->groups((string) Setting::get('ldap_group_filter', '')) as $g) {
            $role = Role::firstOrNew(['ad_group_dn' => $g['dn']]);
            if (! $role->exists) {
                $role->name = $g['name'];
                $role->sort_order = ++$maxOrder;
                $role->in_workflow = false;   // neue Rollen erst von Hand aktivieren / activate new roles manually
                $count++;
            } else {
                $role->name = $g['name'];
            }
            $role->save();
        }

        return $count;
    }

    /**
     * E-Mail-Adressen der Auditoren: eigene Konten und Mitglieder der Gruppe
     *
     * E-mail addresses of the auditors: own accounts and members of the group
     */
    public function auditorEmails(): array
    {
        $mails = User::where('active', true)->where('is_auditor', true)->whereNotNull('email')->pluck('email')->all();

        $group = Setting::get('ad_group_auditor');
        if ($group && ($v = Directory::fromSettings())) {
            foreach ($v->members($group) as $e) {
                if (! empty($e['mail'])) {
                    $mails[] = $e['mail'];
                }
            }
        }

        return array_values(array_unique(array_map('mb_strtolower', $mails)));
    }

    /**
     * Demo-Modus: Rollen aus dem fest verdrahteten Demo-Verzeichnis anlegen
     *
     * Demo mode: create roles from the hard-wired demo directory
     */
    private function syncDemoGroups(): int
    {
        $count = 0;
        $maxOrder = (int) Role::max('sort_order');

        foreach (DemoDirectory::groups() as $dn => $name) {
            $role = Role::firstOrNew(['ad_group_dn' => $dn]);
            if (! $role->exists) {
                $role->name = $name;
                $role->sort_order = ++$maxOrder;
                $role->in_workflow = true;
                $count++;
            }
            $role->save();
        }

        return $count;
    }
}
