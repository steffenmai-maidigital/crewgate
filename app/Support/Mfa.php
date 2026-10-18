<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Richtlinie für die Zwei-Faktor-Anmeldung (Standard 09) — gemeinsamer
 * Baustein, nur die Gruppenliste groups() ist je Projekt verschieden.
 *
 * Je Gruppe gilt eine Stufe: Pflicht (muss eingerichtet werden), wählbar
 * (jeder kann), aus (nicht möglich). Zusätzlich lassen sich Gruppen des
 * Verzeichnisses mit Pflicht belegen, und je Benutzer eine Ausnahme setzen
 * (users.mfa_policy). Gehört jemand zu mehreren Gruppen, gilt die strengste
 * Stufe. Einstellungs-Schlüssel: mfa_<group>, mfa_required_groups.
 *
 * Policy for two-factor sign-in (Standard 09) — shared
 * component, only the group list groups() differs per project.
 *
 * Each group has a level: mandatory (must be set up), optional
 * (anyone may), off (not possible). In addition, directory groups
 * can be made mandatory, and an exception can be set per user
 * (users.mfa_policy). If someone belongs to several groups, the strictest
 * level applies. Setting keys: mfa_<group>, mfa_required_groups.
 */
class Mfa
{
    public const REQUIRED = 'required';
    public const OPTIONAL = 'optional';
    public const OFF = 'off';

    public const LEVELS = [self::REQUIRED => 'Pflicht', self::OPTIONAL => 'wählbar', self::OFF => 'aus'];

    private const RANK = [self::OFF => 0, self::OPTIONAL => 1, self::REQUIRED => 2];

    /**
     * Gruppen des Projekts: Schlüssel => [Bezeichnung, Prüfung(User): bool].
     * CrewGate: Administratoren, HR, Auditoren, Vorgänge anlegen, Abteilungen, alle übrigen.
     *
     * Project groups: key => [label, check(User): bool].
     * CrewGate: administrators, HR, auditors, case creators, departments, everyone else.
     */
    public static function groups(): array
    {
        return [
            'admin'      => [__('Administratoren'), fn (User $u) => (bool) $u->is_admin],
            'hr'         => [__('HR'), fn (User $u) => (bool) $u->is_hr],
            'auditor'    => [__('Auditoren'), fn (User $u) => (bool) $u->is_auditor],
            'create'     => [__('Vorgänge anlegen (ohne HR)'), fn (User $u) => $u->can_create && ! $u->is_hr],
            'department' => [__('Abteilungen (Rollen im Workflow)'), fn (User $u) => $u->roles()->exists()],
            'other'      => [__('Alle übrigen (z. B. Vorgesetzte)'), fn (User $u) => ! $u->is_admin && ! $u->is_hr && ! $u->is_auditor && ! $u->can_create && ! $u->roles()->exists()],
        ];
    }

    /** Eingestellte Stufe einer Gruppe (Standard: wählbar) / Configured level of a group (default: optional) */
    public static function level(string $group): string
    {
        $s = (string) Setting::get('mfa_' . $group, self::OPTIONAL);

        return isset(self::LEVELS[$s]) ? $s : self::OPTIONAL;
    }

    /**
     * Verzeichnisgruppen mit Pflicht (eine je Zeile, DN oder Name)
     *
     * Directory groups with mandatory level (one per line, DN or name)
     */
    public static function requiredGroups(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) Setting::get('mfa_required_groups', '')))));
    }

    /**
     * Wirksame Stufe für einen Benutzer: Ausnahme je Benutzer, sonst die
     * strengste Stufe seiner Gruppen; Verzeichnisgruppen mit Pflicht zählen,
     * wenn die Gruppen der Anmeldung übergeben werden.
     *
     * Effective level for a user: per-user exception, otherwise the
     * strictest level of their groups; mandatory directory groups count
     * when the groups from the sign-in are passed in.
     */
    public static function policy(User $user, array $directoryGroups = []): string
    {
        if (isset(self::LEVELS[(string) $user->mfa_policy])) {
            return $user->mfa_policy;
        }
        $best = null;
        foreach (self::groups() as $key => [$label, $test]) {
            if ($test($user)) {
                $s = self::level($key);
                if ($best === null || self::RANK[$s] > self::RANK[$best]) {
                    $best = $s;
                }
            }
        }
        foreach (self::requiredGroups() as $group) {
            if ($directoryGroups && Directory::isMemberOf($directoryGroups, $group)) {
                $best = self::REQUIRED;
            }
        }

        return $best ?? self::OPTIONAL;
    }

    public static function isRequired(User $user, array $directoryGroups = []): bool
    {
        return self::policy($user, $directoryGroups) === self::REQUIRED;
    }

    public static function isAllowed(User $user, array $directoryGroups = []): bool
    {
        return self::policy($user, $directoryGroups) !== self::OFF;
    }

    /** Anzeigename einer Stufe / Display name of a level */
    public static function label(string $level): string
    {
        return __(self::LEVELS[$level] ?? self::LEVELS[self::OPTIONAL]);
    }
}
