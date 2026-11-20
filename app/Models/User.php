<?php

namespace App\Models;

use App\Models\Concerns\TwoFactor;
use App\Support\Directory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Benutzerkonto. Zwei Herkünfte (auth_source):
 *  - local: eigenes Konto mit Passwort, Rechte und Rollen pflegt der Admin
 *  - ldap:  entsteht bei der ersten Anmeldung über das Verzeichnis; Rechte
 *           und Rollen werden bei jeder Anmeldung aus den Gruppen abgeleitet
 *
 * User account. Two origins (auth_source):
 *  - local: own account with password, rights and roles maintained by the admin
 *  - ldap:  created at the first sign-in via the directory; rights
 *           and roles are derived from the groups at every sign-in
 */
class User extends Authenticatable
{
    use TwoFactor;

    public const LOCAL = 'local';
    public const DIRECTORY = 'ldap';

    protected $fillable = [
        'username', 'name', 'email', 'department', 'auth_source', 'ldap_dn', 'guid', 'password',
        'must_change_password',
        'is_hr', 'is_admin', 'is_auditor', 'can_create', 'active', 'last_login_at', 'mfa_policy',
    ];

    protected $hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];

    protected $casts = [
        'password'      => 'hashed',
        'must_change_password' => 'boolean',
        'is_hr'         => 'boolean',
        'is_admin'      => 'boolean',
        'is_auditor'    => 'boolean',
        'can_create'    => 'boolean',
        'active'        => 'boolean',
        'last_login_at' => 'datetime',
        'two_factor_secret'         => 'encrypted',
        'two_factor_recovery_codes' => 'encrypted:array',
        'two_factor_confirmed_at'   => 'datetime',
    ];

    /** Abteilungen (Rollen), denen das Konto angehört / Departments (roles) the account belongs to */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function isLocal(): bool
    {
        return $this->auth_source !== self::DIRECTORY;
    }

    public function origin(): string
    {
        return $this->isLocal() ? __('eigenes Konto') : __('Verzeichnis');
    }

    /**
     * Rechte und Rollen aus den Verzeichnisgruppen übernehmen (nur
     * Verzeichniskonten). Die Gruppen stehen in den Einstellungen als DN
     * oder Gruppenname.
     *
     * Take over rights and roles from the directory groups (directory
     * accounts only). The groups are stored in the settings as a DN
     * or group name.
     */
    public function permissionsFromGroups(array $groups): void
    {
        $hr      = Setting::get('ad_group_hr', (string) env('AD_GROUP_HR'));
        $admin   = Setting::get('ad_group_admin', (string) env('AD_GROUP_ADMIN'));
        $create  = Setting::get('ad_group_create');
        $auditor = Setting::get('ad_group_auditor');

        $this->is_hr      = Directory::isMemberOf($groups, $hr);
        $this->is_admin   = Directory::isMemberOf($groups, $admin);
        $this->is_auditor = Directory::isMemberOf($groups, $auditor);
        $this->can_create = $this->is_hr || Directory::isMemberOf($groups, $create);
        $this->save();

        $roles = Role::query()->whereNotNull('ad_group_dn')->get()
            ->filter(fn (Role $r) => Directory::isMemberOf($groups, $r->ad_group_dn))
            ->pluck('id')->all();
        $this->roles()->sync($roles);
    }

    /**
     * Daten für die Sitzung — das Format, mit dem die ganze Anwendung
     * arbeitet (session('ldap_user')). Der Name des Schlüssels stammt aus
     * der Zeit, als es nur die AD-Anmeldung gab.
     *
     * Data for the session — the format the whole application works
     * with (session('ldap_user')). The name of the key dates from the
     * time when there was only the AD sign-in.
     */
    public function sessionData(array $groups = []): array
    {
        return [
            'id'         => $this->id,
            'dn'         => $this->ldap_dn,
            'username'   => $this->username,
            'name'       => $this->name,
            'mail'       => $this->email,
            'department' => $this->department,
            'groups'     => $groups,
            'source'     => $this->auth_source,
            'is_hr'      => (bool) $this->is_hr,
            'is_admin'   => (bool) $this->is_admin,
            'is_auditor' => (bool) $this->is_auditor,
            'can_create' => (bool) ($this->can_create || $this->is_hr),
            'role_ids'   => $this->roles()->pluck('roles.id')->map(fn ($i) => (int) $i)->all(),
        ];
    }

    /** Zufälliges Startpasswort für eigene Konten / Random initial password for own accounts */
    public static function initialPassword(): string
    {
        return \Illuminate\Support\Str::password(12, symbols: false);
    }

    /**
     * Fingerabdruck des Passwort-Hashes für die Sitzung: Ändert sich das
     * Passwort (selbst oder durch einen Admin), passt er nicht mehr, und
     * EnsureLdapAuthenticated beendet jede andere Sitzung. HMAC mit dem
     * APP_KEY, damit in der Sitzungsdatei nichts vom Hash selbst steht.
     * Verzeichniskonten haben kein Passwort in der App (leerer Hash).
     *
     * Fingerprint of the password hash for the session: if the password
     * changes (by the person or an admin) it no longer matches, and
     * EnsureLdapAuthenticated ends every other session. HMAC with the
     * APP_KEY so that nothing of the hash itself is in the session file.
     * Directory accounts have no password in the app (empty hash).
     */
    public function passwordFingerprint(): string
    {
        return substr(hash_hmac('sha256', 'password|' . (string) ($this->attributes['password'] ?? ''), (string) config('app.key')), 0, 32);
    }

    /**
     * Muss das Konto erst ein eigenes Passwort vergeben? Nur eigene Konten,
     * nie im Demo-Modus (dort gilt das bekannte Passwort „demo“).
     *
     * Must the account set its own password first? Own accounts only,
     * never in demo mode (the well-known password "demo" applies there).
     */
    public function mustChangePassword(): bool
    {
        return (bool) $this->must_change_password && $this->isLocal() && ! config('demo.enabled');
    }

    /**
     * Ein Admin (oder der Notzugang) vergibt ein Passwort: Die Person muss es
     * bei der nächsten Anmeldung ändern – außer im Demo-Modus.
     *
     * An admin (or the emergency access) assigns a password: the person must
     * change it at the next sign-in – except in demo mode.
     */
    public function assignInitialPassword(#[\SensitiveParameter] string $password): void
    {
        $this->password = $password;
        $this->must_change_password = ! config('demo.enabled');
    }
}
