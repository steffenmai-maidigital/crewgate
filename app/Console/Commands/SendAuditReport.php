<?php

namespace App\Console\Commands;

use App\Mail\AuditReport;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\LdapService;
use App\Services\ReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendAuditReport extends Command
{
    protected $signature = 'audit:report';

    protected $description = 'Erzeugt den PDF-Audit-Bericht und sendet ihn an die Auditoren (Einstellungen: monatlicher Versand).';

    public function handle(ReportService $reports, LdapService $ldap): int
    {
        if (Setting::get('audit_report_enabled', '0') !== '1') {
            $this->info('Monatlicher Audit-Bericht ist in den Einstellungen deaktiviert.');
            return self::SUCCESS;
        }

        // Empfänger: gepflegte Adresse ODER E-Mail-Adressen der Mitglieder
        // der Auditoren-Gruppe aus dem AD
        // Recipients: maintained address OR e-mail addresses of the members
        // of the auditors group from AD
        $recipients = [];
        if ($fixed = Setting::get('audit_report_email')) {
            $recipients = [$fixed];
        } else {
            try {
                $recipients = $ldap->auditorEmails();
            } catch (\Throwable $e) {
                $this->error('AD-Abfrage der Auditoren fehlgeschlagen: ' . $e->getMessage());
            }
        }

        if ($recipients === []) {
            ActivityLog::write(ActivityLog::TYPE_MAIL, 'Audit-Bericht NICHT versendet – keine Empfänger (Auditoren-Gruppe leer / keine Adresse gepflegt)', 'System');
            $this->warn('Keine Empfänger gefunden – bitte Auditoren-Gruppe oder Berichtsadresse in den Einstellungen pflegen.');
            return self::FAILURE;
        }

        try {
            [$pdf, $filename] = $reports->buildAuditPdf('System (monatlicher Versand)');
        } catch (\Throwable $e) {
            ActivityLog::write(ActivityLog::TYPE_MAIL, 'Audit-Bericht NICHT versendet – PDF-Erzeugung fehlgeschlagen', 'System', null, [
                'Fehler' => $e->getMessage(),
            ]);
            $this->error('PDF-Erzeugung fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }

        $company = Setting::get('brand_company_name') ?: 'CrewGate';

        try {
            Mail::to($recipients)->send(new AuditReport($pdf, $filename, $company));
            \App\Models\MailLog::create([
                'recipient' => implode(', ', $recipients),
                'subject'   => 'Monatlicher Audit-Bericht – ' . $company . ' (' . now()->format('m/Y') . ')',
                'body'      => 'Automatischer monatlicher Audit-Bericht mit PDF-Anhang: ' . $filename,
                'status'    => \App\Models\MailLog::STATUS_SENT,
            ]);
            ActivityLog::write(ActivityLog::TYPE_MAIL, 'Monatlicher Audit-Bericht versendet', 'System', null, [
                'Empfänger' => implode(', ', $recipients),
                'Betreff'   => 'Monatlicher Audit-Bericht – ' . $company . ' (' . now()->format('m/Y') . ')',
                'Anhang'    => $filename,
            ]);
        } catch (\Throwable $e) {
            ActivityLog::write(ActivityLog::TYPE_MAIL, 'Versand des Audit-Berichts FEHLGESCHLAGEN', 'System', null, [
                'Empfänger' => implode(', ', $recipients),
                'Fehler'    => $e->getMessage(),
            ]);
            $this->error('Versand fehlgeschlagen: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info('Audit-Bericht an ' . count($recipients) . ' Empfänger versendet.');

        return self::SUCCESS;
    }
}
