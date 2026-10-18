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
        'fox_enabled',
        'mfa_admin', 'mfa_hr', 'mfa_auditor', 'mfa_create', 'mfa_department', 'mfa_other', 'mfa_required_groups',
    ];

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
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,svg,webp', 'max:2048'],
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
        ]);

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
        $data['fox_enabled'] = $request->boolean('fox_enabled') ? '1' : '0';

        foreach (self::KEYS as $key) {
            // Leeres Passwortfeld = altes Passwort behalten
            // Empty password field = keep the old password
            if ($key === 'smtp_password' && blank($data[$key] ?? null)) {
                continue;
            }
            Setting::set($key, $data[$key] ?? null);
        }

        // Anmeldung über das Verzeichnis (Standard 09)
        // Sign-in via the directory (Standard 09)
        Directory::save($request->only(Directory::KEYS) + ['ldap_enabled' => $request->boolean('ldap_enabled'), 'ldap_certificate' => $request->boolean('ldap_certificate')]);

        // Firmenlogo hochladen bzw. entfernen
        // Upload or remove the company logo
        if ($request->boolean('remove_logo')) {
            $this->deleteLogo();
            Setting::set('brand_logo_path', null);
        } elseif ($request->hasFile('logo')) {
            $this->deleteLogo();
            $file = $request->file('logo');
            $name = 'uploads/logo-' . time() . '.' . strtolower($file->getClientOriginalExtension());
            $file->move(public_path('uploads'), basename($name));
            Setting::set('brand_logo_path', $name);
        }

        ActivityLog::write(
            ActivityLog::TYPE_SETTINGS,
            'Einstellungen geändert',
            $this->user()['username'] ?? null,
        );

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

    private function deleteLogo(): void
    {
        $old = Setting::get('brand_logo_path');
        if ($old && file_exists(public_path($old))) {
            @unlink(public_path($old));
        }
    }
}
