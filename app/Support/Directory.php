<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Verzeichnisdienst (Active Directory, Samba AD, OpenLDAP, Univention …)
 * über die PHP-Erweiterung „ldap“ — gemeinsamer Baustein aller Projekte
 * (Standard 09: Anmeldung mit und ohne Verzeichnis).
 *
 * Grundsätze:
 *  - Die Anmeldung ist ein LDAP-Bind mit dem Passwort des Benutzers.
 *    Passwörter werden nie gespeichert.
 *  - Ein Servicekonto (nur lesend) ist optional. Ohne Servicekonto wird der
 *    Benutzer direkt nach einer Vorlage gebunden („%s@firma.local“ oder
 *    „uid=%s,ou=people,dc=firma,dc=local“) und sucht danach sich selbst.
 *  - Gruppen kommen aus memberOf (AD, OpenLDAP mit memberOf-Overlay) und
 *    zusätzlich aus einer Gruppensuche (member, uniqueMember, memberUid),
 *    bei Active Directory auch verschachtelt.
 *  - Einstellungen liegen in der Tabelle settings (Schlüssel ldap_*),
 *    Umgebungsvariablen LDAP_* sind nur Startwerte.
 *
 * Directory service (Active Directory, Samba AD, OpenLDAP, Univention …)
 * via the PHP extension “ldap” — shared building block of all projects
 * (Standard 09: sign-in with and without a directory).
 *
 * Principles:
 *  - Sign-in is an LDAP bind with the user's password.
 *    Passwords are never stored.
 *  - A service account (read-only) is optional. Without a service account the
 *    user is bound directly via a template (“%s@firma.local” or
 *    “uid=%s,ou=people,dc=firma,dc=local”) and then searches for themselves.
 *  - Groups come from memberOf (AD, OpenLDAP with the memberOf overlay) and
 *    additionally from a group search (member, uniqueMember, memberUid),
 *    nested as well for Active Directory.
 *  - Settings live in the settings table (keys ldap_*),
 *    environment variables LDAP_* are only initial values.
 */
class Directory
{
    /** Einstellungs-Schlüssel, die die Oberfläche pflegt / Settings keys maintained by the interface */
    public const KEYS = [
        'ldap_enabled', 'ldap_host', 'ldap_port', 'ldap_encryption', 'ldap_base_dn',
        'ldap_bind_dn', 'ldap_bind_password', 'ldap_bind_template',
        'ldap_user_attribute', 'ldap_user_filter', 'ldap_certificate', 'ldap_ca_certificate', 'ldap_timeout',
    ];

    public const ENCRYPTION = ['ldaps' => 'LDAPS (Port 636)', 'starttls' => 'STARTTLS (Port 389)', 'none' => 'keine (nur im Testnetz)'];

    private const GROUP_CLASSES = '(|(objectClass=group)(objectClass=groupOfNames)(objectClass=groupOfUniqueNames)(objectClass=posixGroup))';

    /**
     * Standard-Benutzerfilter, wenn keiner eingetragen ist: AD-Personen ohne
     * deaktivierte Konten (keine Computer, Kontakte, Gruppen) oder die üblichen
     * Personenklassen von OpenLDAP. Unbekannte Attribute machen den AD-Zweig auf
     * OpenLDAP nur unwahr, nicht den ganzen Filter.
     *
     * Default user filter when none is set: AD persons without disabled
     * accounts (no computers, contacts, groups) or the usual person classes of
     * OpenLDAP. Unknown attributes only make the AD branch false on OpenLDAP,
     * not the whole filter.
     */
    public const DEFAULT_FILTER = '(|(&(objectCategory=person)(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))'
        . '(objectClass=inetOrgPerson)(objectClass=posixAccount))';

    /** @var \LDAP\Connection|resource|null */
    private $connection = null;

    /**
     * TLS-Einstellung, mit der libldap in diesem PHP-Prozess arbeitet. libldap
     * baut den TLS-Kontext einmal je Prozess global auf; PHP kann ihn weder je
     * Verbindung umstellen noch neu bauen (kein LDAP_OPT_X_TLS_NEWCTX). Weichen
     * die Einstellungen davon ab (geändert, während der Apache-Prozess läuft),
     * läuft die Abfrage in einem frischen Prozess (artisan directory:isolated).
     *
     * TLS setting libldap uses in this PHP process. libldap builds the TLS
     * context once per process globally; PHP can neither change it per
     * connection nor rebuild it (no LDAP_OPT_X_TLS_NEWCTX). If the settings
     * differ (changed while the Apache process runs), the query runs in a
     * fresh process (artisan directory:isolated).
     */
    private static ?string $processTls = null;

    public function __construct(private array $k)
    {
    }

    // ---- Einstellungen / Settings -------------------------------------------

    /**
     * Wirksame Einstellungen: Tabelle settings, sonst Umgebung (config/ldap.php)
     *
     * Effective settings: settings table, otherwise environment (config/ldap.php)
     */
    public static function settings(): array
    {
        $c = (array) config('ldap', []);
        $value = function (string $key, $default) {
            $v = Setting::get($key);
            return ($v === null || $v === '') ? $default : $v;
        };
        $password = $value('ldap_bind_password', null);
        $unreadable = false;
        if ($password !== null) {
            try {
                $password = Crypt::decryptString($password);
            } catch (\Throwable) {
                // Sieht der Wert verschlüsselt aus (Laravel: base64-JSON mit iv/value/mac),
                // fehlt der APP_KEY oder er hat sich geändert — nicht als Passwort benutzen.
                // Sonst: unverschlüsselt gespeichert (ältere Stände) — so lassen
                // If the value looks encrypted (Laravel: base64 JSON with iv/value/mac),
                // APP_KEY is missing or has changed — do not use it as a password.
                // Otherwise: stored unencrypted (older versions) — leave as is
                $package = json_decode((string) base64_decode((string) $password, true), true);
                if (is_array($package) && isset($package['iv'], $package['value'], $package['mac'])) {
                    $password = '';
                    $unreadable = true;
                }
            }
        }

        $k = [
            'host'           => trim((string) $value('ldap_host', $c['host'] ?? '')),
            'port'           => (int) $value('ldap_port', $c['port'] ?? 389),
            'encryption'     => (string) $value('ldap_encryption', $c['encryption'] ?? 'starttls'),
            'base_dn'        => trim((string) $value('ldap_base_dn', $c['base_dn'] ?? '')),
            'bind_dn'        => trim((string) $value('ldap_bind_dn', $c['bind_dn'] ?? '')),
            'bind_password'  => (string) ($password ?? ($c['bind_password'] ?? '')),
            'bind_password_unreadable' => $unreadable,
            'bind_template'  => trim((string) $value('ldap_bind_template', $c['bind_template'] ?? '')),
            'user_attribute' => trim((string) $value('ldap_user_attribute', $c['user_attribute'] ?? 'sAMAccountName')) ?: 'sAMAccountName',
            'user_filter'    => trim((string) $value('ldap_user_filter', $c['user_filter'] ?? '')),
            'certificate'    => (string) $value('ldap_certificate', ($c['certificate'] ?? true) ? '1' : '0') === '1',
            'ca_certificate' => trim((string) $value('ldap_ca_certificate', $c['ca_certificate'] ?? '')),
            'timeout'        => max(1, (int) $value('ldap_timeout', $c['timeout'] ?? 5)),
        ];
        // Eingeschaltet: ausdrücklich gespeichert, sonst wenn ein Host aus der
        // Umgebung kommt (bestehende Installationen) und kein Demo-Modus läuft
        // Enabled: explicitly stored, otherwise if a host comes from the
        // environment (existing installations) and demo mode is not running
        $enabled = Setting::get('ldap_enabled');
        $k['enabled'] = ($enabled === null || $enabled === '')
            ? (($c['enabled'] ?? null) !== null ? (bool) $c['enabled'] : ($k['host'] !== '' && ! ($c['demo'] ?? false)))
            : $enabled === '1';

        return $k;
    }

    /**
     * Ist die Anmeldung über das Verzeichnis eingeschaltet und konfiguriert?
     *
     * Is directory sign-in enabled and configured?
     */
    public static function enabled(): bool
    {
        $k = self::settings();
        return $k['enabled'] && $k['host'] !== '' && $k['base_dn'] !== '';
    }

    /**
     * Fertige Instanz mit den wirksamen Einstellungen (null = ausgeschaltet)
     *
     * Ready-made instance with the effective settings (null = disabled)
     */
    public static function fromSettings(): ?self
    {
        return self::enabled() ? new self(self::settings()) : null;
    }

    /**
     * Einstellungen aus einem Formular speichern (leeres Passwort = altes behalten)
     *
     * Save settings from a form (empty password = keep the old one)
     */
    public static function save(array $data): void
    {
        foreach (self::KEYS as $key) {
            if ($key === 'ldap_bind_password') {
                if (($data[$key] ?? '') !== '') {
                    Setting::set($key, Crypt::encryptString((string) $data[$key]));
                }
                continue;
            }
            if (in_array($key, ['ldap_enabled', 'ldap_certificate'], true)) {
                Setting::set($key, ! empty($data[$key]) ? '1' : '0');
                continue;
            }
            if (array_key_exists($key, $data)) {
                Setting::set($key, trim((string) ($data[$key] ?? '')));
            }
        }
    }

    /** Validierungsregeln für das Einstellungsformular / Validation rules for the settings form */
    public static function rules(): array
    {
        return [
            'ldap_host'           => ['nullable', 'string', 'max:255', 'regex:/^[A-Za-z0-9.\-]+(,[A-Za-z0-9.\-]+)*$/'],
            'ldap_port'           => ['nullable', 'integer', 'between:1,65535'],
            'ldap_encryption'     => ['nullable', 'in:ldaps,starttls,none'],
            'ldap_base_dn'        => ['nullable', 'string', 'max:500'],
            'ldap_bind_dn'        => ['nullable', 'string', 'max:500'],
            'ldap_bind_password'  => ['nullable', 'string', 'max:255'],
            'ldap_bind_template'  => ['nullable', 'string', 'max:255'],
            'ldap_user_attribute' => ['nullable', 'string', 'max:60', 'regex:/^[A-Za-z][A-Za-z0-9\-]*$/'],
            'ldap_user_filter'    => ['nullable', 'string', 'max:500', 'regex:/^\(.*\)$/'],
            'ldap_ca_certificate' => ['nullable', 'string', 'max:20000', 'regex:/^\s*-----BEGIN CERTIFICATE-----[A-Za-z0-9+\/=\s-]+-----END CERTIFICATE-----\s*$/'],
            'ldap_timeout'        => ['nullable', 'integer', 'between:1,60'],
        ];
    }

    // ---- Anmeldung / Sign-in ------------------------------------------------

    /**
     * Benutzer anmelden. Liefert den Eintrag (dn, username, name, first_name,
     * last_name, mail, department, manager_dn, groups) oder null bei falschen
     * Zugangsdaten. Verbindungsfehler werfen eine RuntimeException.
     *
     * Sign a user in. Returns the entry (dn, username, name, first_name,
     * last_name, mail, department, manager_dn, groups) or null for wrong
     * credentials. Connection errors throw a RuntimeException.
     */
    public function authenticate(string $username, string $password): ?array
    {
        if (! $this->tlsMatches()) {
            return $this->runIsolated('authenticate', [$username, $password]);
        }
        $username = trim($username);
        if ($username === '' || $password === '' || str_contains($password, "\0")) {
            return null;
        }
        $c = $this->connect();
        // „FIRMA\name“ → „name“; „name@firma.test“ bleibt (UPN)
        // “FIRMA\name” → “name”; “name@firma.test” stays (UPN)
        $short = str_contains($username, '\\') ? substr($username, strrpos($username, '\\') + 1) : $username;

        if ($this->k['bind_dn'] === '' && $this->k['bind_template'] !== '') {
            // Ohne Servicekonto: direkt als Benutzer anmelden, dann sich selbst suchen
            // Without a service account: sign in directly as the user, then search for oneself
            $dn = $this->fromTemplate($username);
            if (! $this->userBind($dn, $password)) {
                return null;
            }
            $entry = $this->searchUsers($short) ?? [
                'dn' => $dn, 'username' => mb_strtolower($short), 'name' => $short,
                'first_name' => null, 'last_name' => null, 'mail' => null, 'department' => null,
                'manager_dn' => null, 'guid' => null, 'memberof' => [],
            ];
        } else {
            $this->serviceBind();
            $entry = $this->searchUsers($short);
            if (! $entry || ! $this->userBind($entry['dn'], $password)) {
                return null;   // unbekannt, falsches Passwort oder Konto gesperrt / unknown, wrong password or locked
            }
            $this->serviceBind();   // für die Gruppensuche wieder das Servicekonto / back to service account for groups
        }

        $entry['groups'] = $this->groupsOf($entry);
        unset($entry['memberof']);

        return $entry;
    }

    /**
     * Verbindung, Servicekonto und Suchbasis prüfen (Knopf „Verbindung testen“)
     *
     * Check connection, service account and search base (button “Verbindung testen”)
     */
    public function test(): array
    {
        if (! $this->tlsMatches()) {
            return $this->runIsolated('test', []);
        }
        $start = microtime(true);
        $c = $this->connect();
        if ($this->k['bind_dn'] === '') {
            // Ohne Servicekonto darf anonym meist nur das RootDSE gelesen werden
            // (AD lehnt anonyme Suchen ab): Verbindung, TLS und Server prüfen
            // Without a service account anonymous access may usually only read
            // the RootDSE (AD refuses anonymous searches): check connection, TLS, server
            if (! @ldap_bind($c)) {
                throw new RuntimeException($this->errorText($c));
            }
            $r = @ldap_read($c, '', '(objectClass=*)', ['namingContexts', 'defaultNamingContext']);
            if (! $r) {
                throw new RuntimeException($this->errorText($c));
            }
            if ($this->k['encryption'] === 'none') {
                // Erlaubt der Server Anmeldungen ohne Verschlüsselung? Probe mit einem
                // Konto, das es nicht gibt (sperrt niemanden): 49 = ja, 8/13 = nein
                // Does the server allow sign-ins without encryption? Probe with an
                // account that does not exist (locks nobody): 49 = yes, 8/13 = no
                $probe = 'connection-test-' . bin2hex(random_bytes(4));
                if (! @ldap_bind($c, $this->fromTemplate($probe), bin2hex(random_bytes(8))) && in_array(ldap_errno($c), [8, 13], true)) {
                    throw new RuntimeException($this->errorText($c));
                }
            }

            return ['mode' => 'template', 'entries' => null, 'milliseconds' => (int) round((microtime(true) - $start) * 1000)];
        }
        $this->serviceBind();
        $r = @ldap_search($c, $this->k['base_dn'], $this->baseFilter(), ['dn'], 1, 50);
        if (! $r) {
            throw new RuntimeException(__('Suchbasis nicht lesbar (:fehler).', ['fehler' => $this->errorText($c)]));
        }
        $n = ldap_count_entries($c, $r);
        $ms = (int) round((microtime(true) - $start) * 1000);

        return ['mode' => 'service', 'entries' => $n, 'milliseconds' => $ms];
    }

    // ---- Suchen / Search ------------------------------------------------------

    /**
     * Einen Benutzer finden: Benutzername, Anmeldename, E-Mail oder Namensbestandteil
     *
     * Find one user: user name, login name, e-mail or part of the name
     */
    public function find(string $text): ?array
    {
        if (! $this->tlsMatches()) {
            return $this->runIsolated('find', [$text]);
        }
        $this->serviceBind();
        if ($e = $this->searchUsers($text)) {
            return $e;
        }
        $t = ldap_escape(trim($text), '', LDAP_ESCAPE_FILTER);
        $hits = $this->userList("(|(displayName=*$t*)(cn=*$t*)(sn=*$t*))", 1);

        return $hits[0] ?? null;
    }

    /** Mehrere Benutzer suchen (Vorschlagsliste) / Search for several users (suggestion list) */
    public function search(string $text, int $limit = 10): array
    {
        if (! $this->tlsMatches()) {
            return $this->runIsolated('search', [$text, $limit]);
        }
        $this->serviceBind();
        $t = ldap_escape(trim($text), '', LDAP_ESCAPE_FILTER);
        $attr = $this->k['user_attribute'];

        return $this->userList("(|($attr=$t*)(displayName=*$t*)(cn=*$t*)(mail=$t*)(userPrincipalName=$t*))", $limit);
    }

    /**
     * Gruppen unterhalb der Suchbasis: [['dn' => …, 'name' => …], …]
     *
     * Groups below the search base: [['dn' => …, 'name' => …], …]
     */
    public function groups(string $namePart = ''): array
    {
        if (! $this->tlsMatches()) {
            return $this->runIsolated('groups', [$namePart]);
        }
        $this->serviceBind();
        $filter = self::GROUP_CLASSES;
        if (trim($namePart, " *") !== '') {
            $filter = '(&' . $filter . '(cn=*' . ldap_escape(trim($namePart, " *"), '', LDAP_ESCAPE_FILTER) . '*))';
        }
        $list = [];
        foreach ($this->allEntries($filter, ['cn']) as $e) {
            if (! empty($e['dn']) && ($name = $this->value($e, 'cn'))) {
                $list[] = ['dn' => $e['dn'], 'name' => $name];
            }
        }
        usort($list, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return $list;
    }

    /** Mitglieder einer Gruppe (DN oder Name): Benutzereinträge / Members of a group (DN or name): user entries */
    public function members(string $group): array
    {
        if (! $this->tlsMatches()) {
            return $this->runIsolated('members', [$group]);
        }
        $this->serviceBind();
        $dn = $this->groupDns($group);
        if (! $dn) {
            return [];
        }
        $g = ldap_escape($dn, '', LDAP_ESCAPE_FILTER);
        // Active Directory: auch verschachtelte Mitgliedschaften, seitenweise
        // (AD liefert je Suche höchstens 1000 Einträge)
        // Active Directory: nested memberships as well, page by page
        // (AD returns at most 1000 entries per search)
        try {
            $raw = $this->allEntries($this->userFilter("(memberOf:1.2.840.113556.1.4.1941:=$g)"), $this->attributes());
        } catch (RuntimeException) {
            $raw = $this->allEntries($this->userFilter("(memberOf=$g)"), $this->attributes());
        }
        $list = array_map(fn ($e) => $this->entry($e), $raw);
        // Gruppen, die ihre Mitglieder selbst führen (groupOfNames, posixGroup)
        // Groups that keep their own member list (groupOfNames, posixGroup)
        if ($list === []) {
            $r = @ldap_read($this->connect(), $dn, '(objectClass=*)', ['member', 'uniqueMember', 'memberUid']);
            $e = $r ? (ldap_get_entries($this->connect(), $r)[0] ?? []) : [];
            foreach (array_merge($this->values($e, 'member'), $this->values($e, 'uniquemember')) as $mDn) {
                $rr = @ldap_read($this->connect(), $mDn, '(objectClass=*)', $this->attributes());
                if ($rr && ($ee = ldap_get_entries($this->connect(), $rr)[0] ?? null)) {
                    $list[] = $this->entry($ee);
                }
            }
            foreach ($this->values($e, 'memberuid') as $uid) {
                if ($ee = $this->searchUsers($uid)) {
                    $list[] = $ee;
                }
            }
        }

        return $list;
    }

    // ---- Gruppen vergleichen / Comparing groups --------------------------------

    /**
     * Gehört der Benutzer (Liste seiner Gruppen-DNs) zur gesuchten Gruppe?
     * Gesucht werden darf ein vollständiger DN oder nur der Gruppenname (CN).
     *
     * Does the user (list of their group DNs) belong to the requested group?
     * The search may use a full DN or just the group name (CN).
     */
    public static function isMemberOf(array $groups, ?string $wanted): bool
    {
        $wanted = mb_strtolower(trim((string) $wanted));
        if ($wanted === '') {
            return false;
        }
        $wantedDn = self::normaliseDn($wanted);
        foreach ($groups as $dn) {
            if (self::normaliseDn((string) $dn) === $wantedDn || self::cn((string) $dn) === $wanted) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gruppenname (CN) aus einem DN, kleingeschrieben und ohne Maskierung:
     * „CN=R&D (Süd)\, Team,OU=…“ → „r&d (süd), team“
     *
     * Group name (CN) from a DN, lower case and unescaped:
     * “CN=R&D (Süd)\, Team,OU=…” → “r&d (süd), team”
     */
    public static function cn(string $dn): string
    {
        $rdns = self::rdns($dn);
        if ($rdns && strcasecmp($rdns[0][0], 'cn') === 0) {
            return mb_strtolower($rdns[0][1]);
        }

        return mb_strtolower(trim($dn));
    }

    /**
     * DN vergleichbar machen: Teile ohne Leerzeichen, Werte unmaskiert, klein
     *
     * Make a DN comparable: parts without spaces, values unescaped, lower case
     */
    public static function normaliseDn(string $dn): string
    {
        $rdns = self::rdns($dn);
        if (! $rdns) {
            return mb_strtolower(trim($dn));
        }

        return implode(',', array_map(fn ($r) => mb_strtolower($r[0] . '=' . $r[1]), $rdns));
    }

    /**
     * DN in [Attribut, Wert]-Paare zerlegen (RFC 4514: „\,“, „\+“, „\2C“ …)
     *
     * Split a DN into [attribute, value] pairs (RFC 4514: “\,”, “\+”, “\2C” …)
     */
    private static function rdns(string $dn): array
    {
        $parts = [];
        foreach (preg_split('/(?<!\\\\),/', trim($dn)) ?: [] as $part) {
            if (! str_contains($part, '=')) {
                return [];
            }
            [$attr, $value] = explode('=', $part, 2);
            $value = preg_replace_callback('/\\\\([0-9A-Fa-f]{2}|.)/',
                fn ($m) => strlen($m[1]) === 2 && ctype_xdigit($m[1]) ? chr(hexdec($m[1])) : $m[1], trim($value));
            $parts[] = [trim($attr), $value];
        }

        return $parts;
    }

    // ---- intern / internal ------------------------------------------------------

    /** @return \LDAP\Connection|resource */
    private function connect()
    {
        if ($this->connection) {
            return $this->connection;
        }
        if (! function_exists('ldap_connect')) {
            throw new RuntimeException(__('Die PHP-Erweiterung „ldap“ ist nicht installiert.'));
        }
        if ($this->k['host'] === '' || $this->k['base_dn'] === '') {
            throw new RuntimeException(__('Verzeichnis: Server und Suchbasis sind nicht eingetragen.'));
        }
        if (! $this->tlsMatches()) {
            // darf nicht vorkommen: die öffentlichen Methoden weichen vorher aus
            // must not happen: the public methods divert beforehand
            throw new RuntimeException(__('Verzeichnis: Zertifikatseinstellungen geändert – bitte erneut versuchen.'));
        }
        $scheme = $this->k['encryption'] === 'ldaps' ? 'ldaps' : 'ldap';
        $uri = implode(' ', array_map(fn ($h) => "$scheme://" . trim($h) . ':' . $this->k['port'], explode(',', $this->k['host'])));
        $c = @ldap_connect($uri);
        if (! $c) {
            throw new RuntimeException(__('Verzeichnis: Adresse „:uri“ ist ungültig.', ['uri' => $uri]));
        }
        ldap_set_option($c, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($c, LDAP_OPT_REFERRALS, 0);
        ldap_set_option($c, LDAP_OPT_NETWORK_TIMEOUT, $this->k['timeout']);
        ldap_set_option($c, LDAP_OPT_TIMELIMIT, $this->k['timeout']);
        if ($this->k['encryption'] === 'starttls' && ! @ldap_start_tls($c)) {
            throw new RuntimeException(__('Verzeichnis: STARTTLS fehlgeschlagen (:fehler).', ['fehler' => $this->errorText($c)]));
        }

        return $this->connection = $c;
    }

    /** Mit dem Servicekonto anmelden (leer = anonym) / Sign in with the service account (empty = anonymous) */
    private function serviceBind(): void
    {
        if ($this->k['bind_dn'] !== '' && ! empty($this->k['bind_password_unreadable'])) {
            throw new RuntimeException(__('Verzeichnis: Das gespeicherte Passwort des Servicekontos lässt sich nicht entschlüsseln – der APP_KEY fehlt oder hat sich geändert. Bitte das Passwort in den Einstellungen neu eingeben.'));
        }
        $c = $this->connect();
        $ok = $this->k['bind_dn'] !== ''
            ? @ldap_bind($c, $this->k['bind_dn'], $this->k['bind_password'])
            : @ldap_bind($c);
        if (! $ok) {
            throw new RuntimeException(ldap_errno($c) === 49
                ? __('Verzeichnis: Servicekonto oder Passwort falsch.')
                : $this->errorText($c));
        }
    }

    /**
     * Als Benutzer binden. Falsche Zugangsdaten (49) → false; der Grund aus
     * Active Directory (data 52e falsches Passwort, 533 deaktiviert, 701
     * abgelaufen, 773 Passwort ändern, 775 gesperrt) landet nur im Protokoll,
     * nie bei der anfragenden Person. Andere Fehler → RuntimeException.
     *
     * Bind as the user. Wrong credentials (49) → false; the reason from Active
     * Directory (data 52e wrong password, 533 disabled, 701 expired, 773 must
     * change password, 775 locked) only goes to the log, never to the person
     * asking. Other errors → RuntimeException.
     */
    private function userBind(string $dn, string $password): bool
    {
        $c = $this->connect();
        if (@ldap_bind($c, $dn, $password)) {
            return true;
        }
        if (ldap_errno($c) !== 49) {
            throw new RuntimeException($this->errorText($c));
        }
        @ldap_get_option($c, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnosis);
        if (is_string($diagnosis) && preg_match('/data ([0-9a-f]{3})/i', $diagnosis, $m) && function_exists('logger')) {
            $reasons = ['52e' => 'falsches Passwort / wrong password', '533' => 'deaktiviert / disabled',
                '701' => 'abgelaufen / expired', '773' => 'Passwort muss geändert werden / must change password',
                '775' => 'gesperrt / locked', '532' => 'Passwort abgelaufen / password expired',
                '530' => 'Anmeldezeit / logon hours', '531' => 'Arbeitsplatz / workstation'];
            logger()->notice('Verzeichnis-Anmeldung abgelehnt / directory sign-in refused: '
                . ($reasons[strtolower($m[1])] ?? $m[1]), ['dn' => $dn]);
        }

        return false;
    }

    /**
     * Verständliche Fehlermeldung samt Diagnose des Servers
     *
     * Understandable error message including the server's diagnosis
     */
    private function errorText($c): string
    {
        $nr = ldap_errno($c);
        @ldap_get_option($c, LDAP_OPT_DIAGNOSTIC_MESSAGE, $diagnosis);
        $details = trim(ldap_error($c) . (is_string($diagnosis) && $diagnosis !== '' ? ' – ' . $diagnosis : ''));

        return match (true) {
            $nr === 8, $nr === 13 => __('Das Verzeichnis verlangt eine verschlüsselte Verbindung (LDAPS oder STARTTLS) (:fehler).', ['fehler' => $details]),
            $nr === -11, str_contains(strtolower($details), 'certificate'), str_contains(strtolower($details), 'tls') =>
                __('Verzeichnis: sichere Verbindung fehlgeschlagen – Zertifikat oder TLS prüfen (:fehler).', ['fehler' => $details]),
            $nr === -1 => __('Verzeichnis nicht erreichbar – Server, Port und Verschlüsselung prüfen (:fehler).', ['fehler' => $details]),
            default => __('Verzeichnis nicht erreichbar (:fehler).', ['fehler' => $details]),
        };
    }

    /**
     * Passt die TLS-Einstellung zu der dieses Prozesses? Beim ersten TLS-Einsatz
     * werden die globalen Optionen gesetzt (vor dem Aufbau des Kontexts).
     *
     * Does the TLS setting match the one of this process? On first TLS use the
     * global options are set (before the context is built).
     */
    private function tlsMatches(): bool
    {
        if ($this->k['encryption'] === 'none') {
            return true;   // kein TLS, kein Kontext / no TLS, no context
        }
        $ca = $this->k['certificate'] ? $this->caFile() : null;
        $signature = ($this->k['certificate'] ? 'verify' : 'no-verify') . '|' . ($ca ?? 'system');
        if (self::$processTls === null) {
            ldap_set_option(null, LDAP_OPT_X_TLS_REQUIRE_CERT, $this->k['certificate'] ? LDAP_OPT_X_TLS_DEMAND : LDAP_OPT_X_TLS_NEVER);
            if ($ca) {
                ldap_set_option(null, LDAP_OPT_X_TLS_CACERTFILE, $ca);
            }
            self::$processTls = $signature;
        }

        return self::$processTls === $signature;
    }

    /**
     * Methode in einem frischen PHP-Prozess ausführen (artisan directory:isolated);
     * Ein- und Ausgabe als JSON, Fehler kommen als RuntimeException zurück.
     *
     * Run a method in a fresh PHP process (artisan directory:isolated);
     * input and output as JSON, errors come back as RuntimeException.
     */
    private function runIsolated(string $method, array $arguments)
    {
        $php = is_executable(PHP_BINDIR . '/php') ? PHP_BINDIR . '/php' : 'php';
        $p = proc_open([$php, base_path('artisan'), 'directory:isolated'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, base_path());
        if (! is_resource($p)) {
            throw new RuntimeException(__('Verzeichnis: Abfrage konnte nicht gestartet werden.'));
        }
        fwrite($pipes[0], json_encode(['settings' => $this->k, 'method' => $method, 'arguments' => $arguments]));
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($p);
        $answer = json_decode((string) $out, true);
        if (! is_array($answer) || ! array_key_exists('ok', $answer)) {
            throw new RuntimeException(__('Verzeichnis: Abfrage fehlgeschlagen (:fehler).', ['fehler' => trim(substr($error ?: (string) $out, 0, 300))]));
        }
        if (! $answer['ok']) {
            throw new RuntimeException((string) $answer['error']);
        }

        return $answer['result'];
    }

    /**
     * Gegenstück im frischen Prozess (vom Befehl directory:isolated aufgerufen)
     *
     * Counterpart in the fresh process (called by the directory:isolated command)
     */
    public static function handleIsolated(string $json): string
    {
        try {
            $d = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
            if (! in_array($d['method'] ?? '', ['authenticate', 'test', 'find', 'search', 'groups', 'members'], true)) {
                throw new RuntimeException('unbekannte Methode / unknown method');
            }
            $result = (new self((array) $d['settings']))->{$d['method']}(...array_values((array) ($d['arguments'] ?? [])));
            $answer = ['ok' => true, 'result' => $result];
        } catch (\Throwable $e) {
            $answer = ['ok' => false, 'error' => $e->getMessage()];
        }

        return json_encode($answer, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * Eigene CA (PEM aus den Einstellungen) als Datei für OpenLDAP; null = Systemspeicher
     *
     * Own CA (PEM from the settings) as a file for OpenLDAP; null = system store
     */
    private function caFile(): ?string
    {
        $pem = $this->k['ca_certificate'] ?? '';
        if ($pem === '') {
            return null;
        }
        $folder = function_exists('storage_path') ? storage_path('app') : sys_get_temp_dir();
        $file = $folder . '/ldap-ca-' . substr(hash('sha256', $pem), 0, 16) . '.pem';
        if (! is_file($file)) {
            // Erst in eine eigene Datei schreiben, dann umbenennen: parallele
            // Prozesse sehen nie eine halbe Datei
            // Write to a file of our own first, then rename: parallel processes
            // never see a half-written file
            @mkdir($folder, 0775, true);
            $tmp = $file . '.' . getmypid() . '.tmp';
            file_put_contents($tmp, $pem . "\n");
            @rename($tmp, $file);
        }

        return $file;
    }

    /**
     * Bind-Name aus der Vorlage. DN-Vorlagen (uid=%s,…) maskieren den Namen.
     * UPN-Vorlagen (%s@firma.test): ein schon vollständiger UPN bleibt, „FIRMA\name“
     * wird zu „name“. Domänen-Vorlagen (FIRMA\%s): „name@…“ und „FIRMA\name“ → „name“.
     *
     * Bind name from the template. DN templates (uid=%s,…) escape the name.
     * UPN templates (%s@firma.test): a complete UPN stays, “FIRMA\name” becomes
     * “name”. Domain templates (FIRMA\%s): “name@…” and “FIRMA\name” → “name”.
     */
    private function fromTemplate(string $username): string
    {
        $v = $this->k['bind_template'];
        $name = str_contains($username, '\\') ? substr($username, strrpos($username, '\\') + 1) : $username;
        if (str_contains($v, '=')) {
            return str_replace('%s', ldap_escape($name, '', LDAP_ESCAPE_DN), $v);
        }
        if (str_contains($name, '@')) {
            if (str_contains($v, '@')) {
                return str_replace(["\0", ',', ' '], '', $name);
            }
            $name = substr($name, 0, strpos($name, '@'));
        }

        return str_replace('%s', str_replace(["\0", ',', '@', '\\', ' '], '', $name), $v);
    }

    private function attributes(): array
    {
        $a = ['cn', 'displayName', 'givenName', 'sn', 'uid', 'sAMAccountName', 'userPrincipalName', 'mail',
            'department', 'memberOf', 'manager', 'objectGUID', 'entryUUID', 'title', 'company',
            'physicalDeliveryOfficeName', 'telephoneNumber', 'mobile', 'employeeID', 'proxyAddresses',
            $this->k['user_attribute']];

        return array_values(array_unique(array_map('strtolower', $a)));
    }

    /**
     * Eingetragener Benutzerfilter (ein „%s“ darin gilt als „alle“) oder der Standard
     *
     * Configured user filter (a “%s” in it means “all”) or the default
     */
    private function baseFilter(): string
    {
        $f = $this->k['user_filter'];

        return $f === '' ? self::DEFAULT_FILTER : str_replace('%s', '*', $f);
    }

    private function userFilter(string $part): string
    {
        return '(&' . $this->baseFilter() . $part . ')';
    }

    private function searchUsers(string $username): ?array
    {
        // „FIRMA\name“ → „name“ / “FIRMA\name” → “name”
        $username = str_contains($username, '\\') ? substr($username, strrpos($username, '\\') + 1) : $username;
        $b = ldap_escape(trim($username), '', LDAP_ESCAPE_FILTER);
        $f = $this->k['user_filter'];
        if (str_contains($f, '%s')) {
            // Filter mit Platzhalter, z. B. (&(objectCategory=person)(sAMAccountName=%s))
            // Filter with placeholder, e.g. (&(objectCategory=person)(sAMAccountName=%s))
            $list = $this->userListRaw(str_replace('%s', $b, $f), 2);
        } else {
            $attr = $this->k['user_attribute'];
            $list = $this->userList("(|($attr=$b)(userPrincipalName=$b)(mail=$b)(uid=$b))", 2);
        }

        // Mehrdeutig (z. B. gleiche Mail bei zwei Konten): lieber niemanden anmelden
        // Ambiguous (e.g. same mail on two accounts): better sign in nobody
        return count($list) === 1 ? $list[0] : null;
    }

    /**
     * Benutzer zu einem Teilfilter; null, wenn der Server den Filter ablehnt ($quiet)
     *
     * Users for a partial filter; null if the server rejects the filter ($quiet)
     */
    private function userList(string $part, int $limit, bool $quiet = false): ?array
    {
        return $this->userListRaw($this->userFilter($part), $limit, $quiet);
    }

    private function userListRaw(string $filter, int $limit, bool $quiet = false): ?array
    {
        $c = $this->connect();
        $r = @ldap_search($c, $this->k['base_dn'], $filter, $this->attributes(), 0, $limit);
        if (! $r) {
            if ($quiet) {
                return null;
            }
            throw new RuntimeException(__('Suche im Verzeichnis fehlgeschlagen (:fehler).', ['fehler' => $this->errorText($c)]));
        }
        $list = [];
        $e = ldap_get_entries($c, $r);
        for ($i = 0; $i < ($e['count'] ?? 0); $i++) {
            $list[] = $this->entry($e[$i]);
        }

        return $list;
    }

    /**
     * Alle Einträge zu einem Filter, seitenweise (AD liefert sonst höchstens 1000)
     *
     * All entries for a filter, page by page (otherwise AD returns at most 1000)
     */
    private function allEntries(string $filter, array $attributes): array
    {
        $c = $this->connect();
        $all = [];
        $cookie = '';
        do {
            $r = @ldap_search($c, $this->k['base_dn'], $filter, $attributes, 0, 0, 0, LDAP_DEREF_NEVER,
                [['oid' => LDAP_CONTROL_PAGEDRESULTS, 'value' => ['size' => 500, 'cookie' => $cookie]]]);
            if (! $r) {
                throw new RuntimeException(__('Suche im Verzeichnis fehlgeschlagen (:fehler).', ['fehler' => $this->errorText($c)]));
            }
            $e = ldap_get_entries($c, $r);
            for ($i = 0; $i < ($e['count'] ?? 0); $i++) {
                $all[] = $e[$i];
            }
            $cookie = '';
            if (ldap_parse_result($c, $r, $code, $matched, $msg, $referrals, $controls)) {
                $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';
            }
        } while ($cookie !== '' && count($all) < 20000);

        return $all;
    }

    private function groupsOf(array $entry): array
    {
        $c = $this->connect();
        $groups = array_map('strval', $entry['memberof'] ?? []);
        $dn = ldap_escape($entry['dn'], '', LDAP_ESCAPE_FILTER);
        $uid = ldap_escape($entry['username'], '', LDAP_ESCAPE_FILTER);

        // Active Directory: verschachtelte Gruppen über die Matching-Rule; andere
        // Server lehnen den Filter ab — dann die einfache Mitgliedersuche
        // Active Directory: nested groups via the matching rule; other
        // servers reject the filter — then the simple member search
        foreach (["(member:1.2.840.113556.1.4.1941:=$dn)", "(|(member=$dn)(uniqueMember=$dn)(memberUid=$uid))"] as $i => $part) {
            $r = @ldap_search($c, $this->k['base_dn'], '(&' . self::GROUP_CLASSES . $part . ')', ['dn'], 0, 2000);
            if (! $r) {
                continue;
            }
            $e = ldap_get_entries($c, $r);
            for ($j = 0; $j < ($e['count'] ?? 0); $j++) {
                $groups[] = $e[$j]['dn'];
            }
            if ($i === 0 && ($e['count'] ?? 0) > 0) {
                break;
            }
        }

        $unique = [];
        foreach ($groups as $g) {
            $unique[mb_strtolower($g)] = $g;
        }

        return array_values($unique);
    }

    private function groupDns(string $group): ?string
    {
        $group = trim($group);
        if ($group === '') {
            return null;
        }
        if (str_contains($group, '=')) {
            return $group;
        }
        $c = $this->connect();
        $r = @ldap_search($c, $this->k['base_dn'], '(&' . self::GROUP_CLASSES . '(cn=' . ldap_escape($group, '', LDAP_ESCAPE_FILTER) . '))', ['dn'], 0, 1);
        $e = $r ? ldap_get_entries($c, $r) : [];

        return $e[0]['dn'] ?? null;
    }

    /**
     * Rohen LDAP-Eintrag in ein einheitliches Feld-Array umsetzen
     *
     * Convert a raw LDAP entry into a uniform field array
     */
    private function entry(array $e): array
    {
        $attr = strtolower($this->k['user_attribute']);
        $username = $this->value($e, $attr) ?? $this->value($e, 'samaccountname') ?? $this->value($e, 'uid') ?? $this->value($e, 'userprincipalname') ?? '';
        $firstName = $this->value($e, 'givenname');
        $lastName = $this->value($e, 'sn');

        // Mail: Attribut mail, sonst die Hauptadresse aus proxyAddresses („SMTP:“ groß)
        // Mail: attribute mail, otherwise the primary address from proxyAddresses (“SMTP:” upper case)
        $mail = $this->value($e, 'mail');
        foreach ($mail === null ? $this->values($e, 'proxyaddresses') : [] as $address) {
            if (str_starts_with($address, 'SMTP:')) {
                $mail = substr($address, 5);
                break;
            }
        }

        return [
            'dn'          => $e['dn'] ?? '',
            'username'    => mb_strtolower((string) $username),
            'name'        => $this->value($e, 'displayname') ?? $this->value($e, 'cn') ?? trim($firstName . ' ' . $lastName) ?: (string) $username,
            'first_name'  => $firstName,
            'last_name'   => $lastName,
            'mail'        => $mail,
            'department'  => $this->value($e, 'department'),
            'title'       => $this->value($e, 'title'),
            'company'     => $this->value($e, 'company'),
            'office'      => $this->value($e, 'physicaldeliveryofficename'),
            'phone'       => $this->value($e, 'telephonenumber'),
            'mobile'      => $this->value($e, 'mobile'),
            'employee_id' => $this->value($e, 'employeeid'),
            'manager_dn'  => $this->value($e, 'manager'),
            'guid'        => self::guid($e),
            'memberof'    => $this->values($e, 'memberof'),
        ];
    }

    /**
     * Unveränderliche Kennung des Kontos: AD-objectGUID (16 Byte, die ersten drei
     * Gruppen in umgekehrter Bytefolge) bzw. OpenLDAP-entryUUID. Bleibt beim
     * Umbenennen und Verschieben im Verzeichnis gleich.
     *
     * Immutable account id: AD objectGUID (16 bytes, the first three groups in
     * reversed byte order) or OpenLDAP entryUUID. Stays the same when the
     * account is renamed or moved in the directory.
     */
    public static function guid(array $e): ?string
    {
        $raw = $e['objectguid'][0] ?? null;
        if (is_string($raw) && strlen($raw) === 16) {
            $h = bin2hex($raw);
            $reverse = fn (string $s) => implode('', array_reverse(str_split($s, 2)));

            return $reverse(substr($h, 0, 8)) . '-' . $reverse(substr($h, 8, 4)) . '-' . $reverse(substr($h, 12, 4))
                . '-' . substr($h, 16, 4) . '-' . substr($h, 20, 12);
        }
        $uuid = $e['entryuuid'][0] ?? null;

        return is_string($uuid) && preg_match('/^[0-9a-f-]{36}$/i', $uuid) ? strtolower($uuid) : null;
    }

    private function value(array $e, string $k): ?string
    {
        $v = $e[$k][0] ?? null;

        return ($v === null || $v === '') ? null : (string) $v;
    }

    private function values(array $e, string $k): array
    {
        $list = [];
        for ($i = 0; $i < ($e[$k]['count'] ?? 0); $i++) {
            $list[] = (string) $e[$k][$i];
        }

        return $list;
    }
}
