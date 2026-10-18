<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Englische Namen (Standard 10): Spalten, gespeicherte Werte, Einstellungs-
 * schlüssel und JSON-Inhalte bekommen ihre englischen Namen. Idempotent —
 * läuft gefahrlos auf einer schon umgestellten Datenbank und auf einer alten,
 * zurückgespielten Sicherung. Das Protokoll (activity_logs) bleibt unberührt:
 * seine Einträge hängen in einer SHA-256-Hash-Kette und werden nie geändert.
 * Die Werte stehen hier bewusst noch einmal ausgeschrieben, damit die
 * Migration auch nach späteren Änderungen am Code gleich arbeitet.
 *
 * English names (standard 10): columns, stored values, settings keys and JSON
 * contents get their English names. Idempotent — runs safely on a database
 * that is already converted and on an old restored backup. The audit log
 * (activity_logs) stays untouched: its entries form a SHA-256 hash chain and
 * are never changed. The values are spelled out here on purpose so that the
 * migration keeps working the same after later code changes.
 */
return new class extends Migration
{
    /** Einstellungs-Schlüssel alt => neu / settings keys old => new */
    private const SETTINGS = [
        'fuchs_aktiv'            => 'fox_enabled',
        'ldap_aktiv'             => 'ldap_enabled',
        'ldap_verschluesselung'  => 'ldap_encryption',
        'ldap_bind_passwort'     => 'ldap_bind_password',
        'ldap_bind_vorlage'      => 'ldap_bind_template',
        'ldap_benutzer_attribut' => 'ldap_user_attribute',
        'ldap_benutzer_filter'   => 'ldap_user_filter',
        'ldap_zertifikat'        => 'ldap_certificate',
        'mfa_gruppen_pflicht'    => 'mfa_required_groups',
        'mfa_abteilung'          => 'mfa_department',
        'mfa_sonst'              => 'mfa_other',
        'impressum_aktiv'        => 'imprint_enabled',
        'impressum_art'          => 'imprint_type',
        'impressum_text'         => 'imprint_text',
        'impressum_link'         => 'imprint_url',
        'datenschutz_aktiv'      => 'privacy_enabled',
        'datenschutz_art'        => 'privacy_type',
        'datenschutz_text'       => 'privacy_text',
        'datenschutz_link'       => 'privacy_url',
    ];

    /** Zwei-Faktor-Stufen (users.mfa_policy, Einstellungen mfa_<group>) / two-factor levels */
    private const MFA_LEVELS = ['pflicht' => 'required', 'aus' => 'off'];

    /** Eingebaute Prozessarten (processes.key, onboardings.process, custom_fields.processes) / built-in process types */
    private const PROCESSES = ['wechsel' => 'job_change', 'austritt' => 'exit', 'stammdaten' => 'master_data_change'];

    /** custom_fields.fill_stage */
    private const STAGES = ['anlage' => 'intake', 'vorbereitung' => 'preparation'];

    /** Checkbox-Werte in onboarding_values / checkbox values */
    private const CHECKBOX = ['ja' => 'yes', 'nein' => 'no'];

    /** mail_logs.status */
    private const MAIL_STATUS = ['gesendet' => 'sent', 'fehlgeschlagen' => 'failed'];

    /** Platzhalter in den Mail-Vorlagen (Einstellungen mail_tpl_*) / placeholders in the mail templates */
    private const PLACEHOLDERS = [
        'vorgesetzter' => 'supervisor', 'prozess' => 'process', 'abteilung' => 'department',
        'eintritt' => 'date', 'rolle' => 'role', 'daten' => 'data', 'frist' => 'due_date',
        'aufgaben' => 'tasks', 'vorname' => 'first_name', 'nachname' => 'last_name',
    ];

    public function up(): void
    {
        $this->convert(false);
    }

    /**
     * Rückweg (so weit möglich): alte Namen und Werte wiederherstellen
     *
     * Way back (as far as possible): restore the old names and values
     */
    public function down(): void
    {
        $this->convert(true);
    }

    private function convert(bool $back): void
    {
        $flip = fn (array $map) => $back ? array_flip($map) : $map;

        // ---- Spalten / Columns ----------------------------------------------
        [$from, $to] = $back ? ['fox', 'fuchs'] : ['fuchs', 'fox'];
        if (Schema::hasTable('users') && Schema::hasColumn('users', $from) && ! Schema::hasColumn('users', $to)) {
            Schema::table('users', fn (Blueprint $t) => $t->renameColumn($from, $to));
        }

        // ---- Einstellungen / Settings ---------------------------------------
        if (Schema::hasTable('settings')) {
            foreach ($flip(self::SETTINGS) as $old => $new) {
                $this->renameSetting($old, $new);
            }
            // Stufen je Gruppe / levels per group
            $levels = $flip(self::MFA_LEVELS);
            foreach (DB::table('settings')->where('key', 'like', 'mfa_%')->get() as $row) {
                if (in_array($row->key, ['mfa_required_groups', 'mfa_gruppen_pflicht'], true)) {
                    continue;
                }
                if (isset($levels[(string) $row->value])) {
                    DB::table('settings')->where('key', $row->key)->update(['value' => $levels[$row->value]]);
                }
            }
            // Platzhalter in eigenen Mail-Vorlagen / placeholders in custom mail templates
            $placeholders = [];
            foreach ($flip(self::PLACEHOLDERS) as $old => $new) {
                $placeholders['{' . $old . '}'] = '{' . $new . '}';
            }
            foreach (DB::table('settings')->where('key', 'like', 'mail_tpl_%')->whereNotNull('value')->get() as $row) {
                $value = strtr((string) $row->value, $placeholders);
                if ($value !== $row->value) {
                    DB::table('settings')->where('key', $row->key)->update(['value' => $value]);
                }
            }
        }

        // ---- Benutzer / Users -------------------------------------------------
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'mfa_policy')) {
            foreach ($flip(self::MFA_LEVELS) as $old => $new) {
                DB::table('users')->where('mfa_policy', $old)->update(['mfa_policy' => $new]);
            }
        }

        // ---- Prozessarten / Process types -------------------------------------
        $processes = $flip(self::PROCESSES);
        if (Schema::hasTable('processes')) {
            foreach ($processes as $old => $new) {
                if (! DB::table('processes')->where('key', $old)->exists()) {
                    continue;
                }
                if (DB::table('processes')->where('key', $new)->exists()) {
                    // beide vorhanden (z. B. Upgrade lief halb): den alten Eintrag aufgeben
                    // both present (e.g. a half-finished upgrade): drop the old row
                    DB::table('processes')->where('key', $old)->delete();
                } else {
                    DB::table('processes')->where('key', $old)->update(['key' => $new]);
                }
            }
        }
        if (Schema::hasTable('onboardings') && Schema::hasColumn('onboardings', 'process')) {
            foreach ($processes as $old => $new) {
                DB::table('onboardings')->where('process', $old)->update(['process' => $new]);
            }
        }
        if (Schema::hasTable('custom_fields')) {
            if (Schema::hasColumn('custom_fields', 'processes')) {
                foreach (DB::table('custom_fields')->whereNotNull('processes')->get(['id', 'processes']) as $row) {
                    $list = json_decode((string) $row->processes, true);
                    if (! is_array($list)) {
                        continue;
                    }
                    $new = array_values(array_unique(array_map(fn ($p) => is_string($p) ? ($processes[$p] ?? $p) : $p, $list)));
                    if ($new !== $list) {
                        DB::table('custom_fields')->where('id', $row->id)->update(['processes' => json_encode($new)]);
                    }
                }
            }
            if (Schema::hasColumn('custom_fields', 'fill_stage')) {
                foreach ($flip(self::STAGES) as $old => $new) {
                    DB::table('custom_fields')->where('fill_stage', $old)->update(['fill_stage' => $new]);
                }
            }
        }

        // ---- Werte der Checkbox-Felder / values of checkbox fields --------------
        if (Schema::hasTable('onboarding_values') && Schema::hasTable('custom_fields')) {
            $checkboxes = DB::table('custom_fields')->where('type', 'checkbox')->pluck('id');
            foreach ($flip(self::CHECKBOX) as $old => $new) {
                DB::table('onboarding_values')->whereIn('custom_field_id', $checkboxes)->where('value', $old)->update(['value' => $new]);
            }
        }

        // ---- Mail-Log / mail log ------------------------------------------------
        if (Schema::hasTable('mail_logs')) {
            foreach ($flip(self::MAIL_STATUS) as $old => $new) {
                DB::table('mail_logs')->where('status', $old)->update(['status' => $new]);
            }
        }
    }

    /**
     * Einstellung umbenennen; gibt es den neuen Schlüssel schon, gilt er und der alte entfällt
     *
     * Rename a setting; if the new key exists already it wins and the old one is removed
     */
    private function renameSetting(string $old, string $new): void
    {
        if (! DB::table('settings')->where('key', $old)->exists()) {
            return;
        }
        if (DB::table('settings')->where('key', $new)->exists()) {
            DB::table('settings')->where('key', $old)->delete();

            return;
        }
        DB::table('settings')->where('key', $old)->update(['key' => $new]);
    }
};
