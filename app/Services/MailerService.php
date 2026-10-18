<?php

namespace App\Services;

use App\Models\Setting;

class MailerService
{
    /**
     * SMTP-/SMTPS-Einstellungen aus der Datenbank auf die
     * Laufzeitkonfiguration anwenden (DB hat Vorrang vor .env).
     *
     * Apply the SMTP/SMTPS settings from the database to the
     * runtime configuration (DB takes precedence over .env).
     */
    public static function applyDatabaseConfig(): void
    {
        // Demo-Modus: keine echten Mails versenden, alles ins Laravel-Log
        // Demo mode: send no real mails, everything goes into the Laravel log
        if (config('demo.enabled')) {
            config(['mail.default' => 'log']);
            return;
        }

        try {
            $host = Setting::get('smtp_host');
        } catch (\Throwable) {
            return; // z. B. vor der ersten Migration / e.g. before the first migration
        }

        if (! $host) {
            return;
        }

        $scheme = Setting::get('smtp_encryption', 'tls'); // tls | smtps | none

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => $host,
            'mail.mailers.smtp.port' => (int) Setting::get('smtp_port', '587'),
            'mail.mailers.smtp.username' => Setting::get('smtp_username') ?: null,
            'mail.mailers.smtp.password' => Setting::get('smtp_password') ?: null,
            // 'smtps' = implizites TLS (Port 465), sonst STARTTLS/Klartext
            // 'smtps' = implicit TLS (port 465), otherwise STARTTLS/plain text
            'mail.mailers.smtp.scheme' => $scheme === 'smtps' ? 'smtps' : 'smtp',
            'mail.mailers.smtp.encryption' => $scheme === 'tls' ? 'tls' : null,
            'mail.from.address' => Setting::get('smtp_from_address', config('mail.from.address')),
            'mail.from.name' => Setting::get('smtp_from_name', config('mail.from.name')),
        ]);
    }
}
