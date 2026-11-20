<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\MailLog;
use App\Models\Setting;
use App\Services\MailerService;
use App\Support\Directory;
use App\Support\LegalPages;
use App\Support\Mfa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SettingController extends Controller
{
    private const KEYS = [
        'smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username',
        'smtp_password', 'smtp_from_address', 'smtp_from_name',
        'ldap_group_filter',
        'ad_group_hr', 'ad_group_admin', 'ad_group_create', 'ad_group_auditor',
        'retention_enabled', 'retention_months',
        'audit_report_enabled', 'audit_report_email',
        'brand_company_name', 'brand_header_color', 'brand_accent_color',
        'brand_footer_color', 'brand_footer_text',
        'license_company', 'license_key',
        'portal_url',
        'mail_tpl_supervisor_de', 'mail_tpl_supervisor_en',
        'mail_tpl_role_de', 'mail_tpl_role_en',
        'mail_tpl_cancel_de', 'mail_tpl_cancel_en',
        'mail_tpl_reminder_supervisor_de', 'mail_tpl_reminder_supervisor_en',
        'mail_tpl_reminder_role_de', 'mail_tpl_reminder_role_en',
        'reminder_roles_enabled',
        'api_token',
        'mfa_admin', 'mfa_hr', 'mfa_auditor', 'mfa_create', 'mfa_department', 'mfa_other', 'mfa_required_groups',
    ];

    /**
     * Gruppen im Verzeichnis (DN oder Name, mfa_required_groups eine je Zeile):
     * nach Directory::save() eindeutig zum DN aufgelöst gespeichert
     *
     * Directory groups (DN or name, mfa_required_groups one per line):
     * stored after Directory::save(), resolved unambiguously to their DN
     */
    private const GROUP_KEYS = ['ad_group_hr', 'ad_group_create', 'ad_group_admin', 'ad_group_auditor', 'mfa_required_groups'];

    public function index()
    {
        $settings = [];
        foreach ([...self::KEYS, 'brand_logo_path'] as $key) {
            $settings[$key] = Setting::get($key);
        }

        return view('admin.settings', [
            'settings'    => $settings,
            'directory'   => Directory::settings(),
            'demo'        => (bool) config('demo.enabled'),
        ]);
    }

    public function update(Request $request)
    {
        $hex = 'regex:/^#[0-9a-fA-F]{6}$/';
        $levels = 'in:' . implode(',', array_keys(Mfa::LEVELS));

        $data = $request->validate([
            'smtp_host' => ['nullable', 'string', 'max:255'],
            'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['required', 'in:none,tls,smtps'],
            'smtp_username' => ['nullable', 'string', 'max:255'],
            'smtp_password' => ['nullable', 'string', 'max:255'],
            'smtp_from_address' => ['nullable', 'email', 'max:255'],
            'smtp_from_name' => ['nullable', 'string', 'max:255'],
            'ldap_group_filter' => ['nullable', 'string', 'max:255'],
            'ad_group_hr' => ['nullable', 'string', 'max:500'],
            'ad_group_admin' => ['nullable', 'string', 'max:500'],
            'ad_group_create' => ['nullable', 'string', 'max:500'],
            'ad_group_auditor' => ['nullable', 'string', 'max:500'],
            'retention_months' => ['required', 'integer', 'between:1,120'],
            'audit_report_email' => ['nullable', 'email', 'max:255'],
            'brand_company_name' => ['nullable', 'string', 'max:100'],
            'brand_header_color' => ['nullable', $hex],
            'brand_accent_color' => ['nullable', $hex],
            'brand_footer_color' => ['nullable', $hex],
            'brand_footer_text' => ['nullable', 'string', 'max:255'],
            // SVG ausdrücklich erlauben (Laravel lässt es sonst nicht als Bild gelten)
            // allow SVG explicitly (otherwise Laravel does not accept it as an image)
            'logo' => ['nullable', 'image:allow_svg', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
            'license_company' => ['nullable', 'string', 'max:150'],
            'license_key' => ['nullable', 'string', 'max:20'],
            'portal_url' => ['nullable', 'url', 'max:255'],
            'mail_tpl_supervisor_de' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_supervisor_en' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_role_de' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_role_en' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_cancel_de' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_cancel_en' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_reminder_supervisor_de' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_reminder_supervisor_en' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_reminder_role_de' => ['nullable', 'string', 'max:8000'],
            'mail_tpl_reminder_role_en' => ['nullable', 'string', 'max:8000'],
            'api_token' => ['nullable', 'string', 'max:100'],
            'mfa_admin' => ['required', $levels],
            'mfa_hr' => ['required', $levels],
            'mfa_auditor' => ['required', $levels],
            'mfa_create' => ['required', $levels],
            'mfa_department' => ['required', $levels],
            'mfa_other' => ['required', $levels],
            'mfa_required_groups' => ['nullable', 'string', 'max:4000'],
        ] + Directory::rules(), [
            'ldap_user_filter.regex' => __('Der Benutzerfilter muss ein vollständiger LDAP-Filter in Klammern sein, z. B. (objectClass=person).'),
        ], [
            'logo' => __('Firmenlogo'),
        ]);

        // SVG-Logo nur ohne Skripte, Ereignisse und eingebettete Inhalte
        // SVG logo only without scripts, event handlers and embedded content
        if ($request->hasFile('logo') && $request->file('logo')->guessExtension() === 'svg'
            && ! self::svgIsPlain((string) file_get_contents($request->file('logo')->getRealPath()))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'logo' => __('Das SVG-Logo enthält Skripte, Verweise oder eingebettete Inhalte. Bitte ein einfaches SVG oder ein PNG verwenden.'),
            ]);
        }

        // Lizenzschlüssel nur nach Schema akzeptieren (1|2 + 8x Kundennr. +
        // JJMMTT Supportende + 5-stellige Anzahl)
        // Only accept licence keys matching the schema (1|2 + 8x customer no. +
        // YYMMDD end of support + 5-digit count)
        if (filled($data['license_key'] ?? null) && ! \App\Services\LicenseService::parse($data['license_key'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'license_key' => __('Der Lizenzschlüssel entspricht nicht dem Schema: Stelle 1 = 1 (Standard) oder 2 (Enterprise), Stellen 2–9 Kundennummer (Buchstaben/Zahlen), Stellen 10–15 Support-Ende als JJMMTT, Stellen 16–20 Lizenzanzahl.'),
            ]);
        }

        // Verzeichnis einschalten geht nur mit Server und Suchbasis
        // Switching the directory on only works with server and search base
        if ($request->boolean('ldap_enabled') && (blank($data['ldap_host'] ?? null) || blank($data['ldap_base_dn'] ?? null))) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'ldap_host' => __('Zum Einschalten der Verzeichnis-Anmeldung bitte Server und Suchbasis eintragen.'),
            ]);
        }

        // Impressum und Datenschutz (Standard 03)
        // Imprint and privacy policy (Standard 03)
        LegalPages::save($request);

        $data['retention_enabled'] = $request->boolean('retention_enabled') ? '1' : '0';
        $data['reminder_roles_enabled'] = $request->boolean('reminder_roles_enabled') ? '1' : '0';
        $data['audit_report_enabled'] = $request->boolean('audit_report_enabled') ? '1' : '0';

        // Ändern sich Server, Port, Verschlüsselung oder Benutzer des Mailservers und kommt
        // kein neues Passwort, gilt das alte nicht mehr – sonst ginge es beim Testmail an
        // einen womöglich fremden Server (wie Directory::save)
        // If host, port, encryption or user of the mail server change and no new password
        // comes, the old one no longer applies – otherwise the test mail would send it to a
        // possibly foreign server (like Directory::save)
        $smtpMoved = false;
        foreach (['smtp_host', 'smtp_port', 'smtp_encryption', 'smtp_username'] as $key) {
            if (trim((string) ($data[$key] ?? '')) !== trim((string) Setting::get($key, ''))) {
                $smtpMoved = true;
            }
        }

        foreach (self::KEYS as $key) {
            if (in_array($key, self::GROUP_KEYS, true)) {
                continue;   // unten nach dem Verzeichnis / below, after the directory
            }
            // Leeres Passwortfeld = altes Passwort behalten (außer der Server hat sich geändert)
            // Empty password field = keep the old password (unless the server changed)
            if ($key === 'smtp_password' && blank($data[$key] ?? null)) {
                if ($smtpMoved) {
                    Setting::set($key, null);
                }
                continue;
            }
            Setting::set($key, $data[$key] ?? null);
        }

        // Anmeldung über das Verzeichnis (Standard 09)
        // Sign-in via the directory (Standard 09)
        Directory::save($request->only(Directory::KEYS) + ['ldap_enabled' => $request->boolean('ldap_enabled'), 'ldap_certificate' => $request->boolean('ldap_certificate')]);

        // Gruppennamen mit den neuen Verbindungsdaten zum DN auflösen; ein Name,
        // den es mehrfach gibt, wird nicht gespeichert (alter Wert bleibt, Fehler am Feld)
        // Resolve group names to their DN with the new connection settings; a name
        // that exists more than once is not stored (the old value stays, error on the field)
        $groupErrors = [];
        foreach (self::GROUP_KEYS as $key) {
            $group = Directory::resolveGroups($data[$key] ?? null);
            if ($group['error']) {
                $groupErrors[$key] = $group['error'];
                continue;
            }
            Setting::set($key, $group['value'] !== '' ? $group['value'] : null);
        }

        // Firmenlogo hochladen bzw. entfernen
        // Upload or remove the company logo
        if ($request->boolean('remove_logo')) {
            $this->deleteLogo();
            Setting::set('brand_logo_path', null);
        } elseif ($request->hasFile('logo')) {
            $this->deleteLogo();
            $file = $request->file('logo');
            // Endung aus dem Inhalt, nie aus dem Dateinamen des Absenders (sonst z. B. „.html“)
            // extension from the content, never from the sender's file name (otherwise e.g. ".html")
            $name = 'uploads/logo-' . time() . '.' . self::imageExtension($file);
            $file->move(public_path('uploads'), basename($name));
            Setting::set('brand_logo_path', $name);
        }

        ActivityLog::write(
            ActivityLog::TYPE_SETTINGS,
            'Einstellungen geändert',
            $this->user()['username'] ?? null,
        );

        if ($groupErrors) {
            return back()->withInput($request->except(['smtp_password', 'ldap_bind_password', 'logo']))->withErrors($groupErrors)
                ->with('ok', __('Die übrigen Einstellungen sind gespeichert.'));
        }

        return back()->with('ok', __('Einstellungen gespeichert.'));
    }

    /**
     * Verbindung zum Verzeichnis mit den gespeicherten Einstellungen prüfen
     *
     * Test the connection to the directory with the saved settings
     */
    public function testLdap()
    {
        $k = Directory::settings();
        if ($k['host'] === '' || $k['base_dn'] === '') {
            return back()->withErrors(['ldap_host' => __('Bitte zuerst Server und Suchbasis speichern.')]);
        }
        try {
            $result = (new Directory($k))->test();
        } catch (\Throwable $e) {
            return back()->withErrors(['ldap_host' => __('Verbindung fehlgeschlagen: :fehler', ['fehler' => $e->getMessage()])]);
        }

        return back()->with('ok', $result['mode'] === 'template'
            ? __('Verbindung zum Verzeichnis in Ordnung: Server erreichbar, Verschlüsselung und Zertifikat passen (:ms ms). Ohne Servicekonto lässt sich die Suchbasis erst bei einer Anmeldung prüfen – bitte einmal mit einem Verzeichniskonto anmelden.', ['ms' => $result['milliseconds']])
            : __('Verbindung zum Verzeichnis in Ordnung: Servicekonto angemeldet, Suchbasis lesbar (:n Einträge geprüft, :ms ms).', [
            'n' => $result['entries'], 'ms' => $result['milliseconds'],
        ]));
    }

    public function testMail(Request $request)
    {
        $request->validate(['to' => ['required', 'email']]);

        MailerService::applyDatabaseConfig();

        try {
            Mail::raw('Testnachricht des CrewGate-Portals.', function ($m) use ($request) {
                $m->to($request->string('to'))->subject('SMTP-Test');
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['to' => __('Versand fehlgeschlagen: :fehler', ['fehler' => $e->getMessage()])]);
        }

        MailLog::create([
            'recipient' => (string) $request->string('to'),
            'subject'   => 'SMTP-Test',
            'body'      => 'Testnachricht des CrewGate-Portals.',
            'status'    => MailLog::STATUS_SENT,
        ]);

        ActivityLog::write(ActivityLog::TYPE_MAIL, 'Testmail versendet an ' . $request->string('to'), $this->user()['username'] ?? null, null, [
            'Empfänger' => (string) $request->string('to'),
            'Betreff'   => 'SMTP-Test',
        ]);

        return back()->with('ok', __('Testmail versendet.'));
    }

    /**
     * Neues API-Token erzeugen (48 Zeichen) — das bisherige wird sofort ungültig
     *
     * Generate a new API token (48 characters) — the previous one becomes invalid immediately
     */
    public function regenerateApiToken()
    {
        $token = \Illuminate\Support\Str::random(48);
        Setting::set('api_token', $token);
        ActivityLog::write(ActivityLog::TYPE_SETTINGS, 'API-Token neu erzeugt', $this->user()['username'] ?? null);

        return back()->with('ok', __('Neues API-Token erzeugt und gespeichert.'));
    }

    /**
     * Dateiendung eines geprüften Bildes aus seinem Inhalt (png, jpg, svg, webp)
     *
     * File extension of a validated image from its content (png, jpg, svg, webp)
     */
    public static function imageExtension(\Illuminate\Http\UploadedFile $file): string
    {
        $ext = strtolower((string) $file->guessExtension());
        $ext = $ext === 'jpeg' ? 'jpg' : $ext;
        abort_unless(in_array($ext, ['png', 'jpg', 'svg', 'webp'], true), 422);

        return $ext;
    }

    /**
     * Ist das SVG ein reines Bild? Abgelehnt werden Skripte, Ereignis-Attribute
     * (onload …), javascript:-Adressen, Verweise nach außen (erlaubt: #id und
     * eingebettete Rasterbilder data:image/…), eingebettetes HTML und
     * XML-Entitäten.
     *
     * Is the SVG a plain image? Refused are scripts, event attributes
     * (onload …), javascript: addresses, outside references (allowed: #id and
     * embedded raster images data:image/…), embedded HTML and XML entities.
     */
    public static function svgIsPlain(string $svg): bool
    {
        return ! preg_match('/<\s*script|<\s*foreignObject|<!ENTITY|\son[a-z]+\s*=|javascript:'
            . '|href\s*=\s*["\']\s*(?!#|data:image\/(?:png|jpe?g|gif|webp)[;,])|<\s*(?:iframe|embed|object)\b/i', $svg);
    }

    private function deleteLogo(): void
    {
        $old = Setting::get('brand_logo_path');
        if ($old && file_exists(public_path($old))) {
            @unlink(public_path($old));
        }
    }
}
