<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\Setting;
use Dompdf\Dompdf;
use Dompdf\Options;

class ReportService
{
    /**
     * Audit-Bericht als PDF erzeugen: Firmendaten aus den Einstellungen,
     * Export-Zeitstempel, alle Protokolleinträge (inkl. Hash-Kette),
     * Löschungen und die Vorgänge der zuletzt eingestellten Mitarbeiter.
     *
     * Generate the audit report as PDF: company data from the settings,
     * export timestamp, all log entries (incl. hash chain),
     * deletions and the cases of the most recently hired employees.
     *
     * @return array{0: string, 1: string}  [PDF-Binärdaten, Dateiname] / [PDF binary data, file name]
     */
    public function buildAuditPdf(?string $exportedBy = null): array
    {
        $generatedAt = now();

        // Firmenlogo als Data-URI einbetten (dompdf-sicher)
        // Embed the company logo as a data URI (dompdf-safe)
        $logoDataUri = null;
        $logoPath = Setting::get('brand_logo_path');
        if ($logoPath && is_file(public_path($logoPath))) {
            $mime = mime_content_type(public_path($logoPath)) ?: 'image/png';
            $logoDataUri = 'data:' . $mime . ';base64,' . base64_encode(file_get_contents(public_path($logoPath)));
        }

        $html = view('report.audit', [
            'generatedAt' => $generatedAt,
            'exportedBy'  => $exportedBy ?? 'System',
            'company'     => Setting::get('brand_company_name') ?: 'CrewGate',
            'footerText'  => Setting::get('brand_footer_text'),
            'headerColor' => Setting::get('brand_header_color') ?: '#14557b',
            'logo'        => $logoDataUri,
            'retentionEnabled' => Setting::get('retention_enabled', '1') === '1',
            'retentionMonths'  => (int) Setting::get('retention_months', '6'),
            'logs'      => ActivityLog::orderBy('id')->get(),
            'deletions' => ActivityLog::where('type', ActivityLog::TYPE_DELETION)->orderByDesc('created_at')->get(),
            'recent'    => Onboarding::with('steps.role')
                ->orderByDesc('created_at')->limit(25)->get(),
        ])->render();

        // dompdf braucht beschreibbare Verzeichnisse (Font-Cache, Temp) –
        // vendor/ gehört im Container root, daher nach storage/ umleiten
        // dompdf needs writable directories (font cache, temp) –
        // vendor/ belongs to root in the container, so redirect to storage/
        $workDir = storage_path('app/dompdf');
        if (! is_dir($workDir)) {
            @mkdir($workDir, 0775, true);
        }

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans'); // Umlaute / umlauts
        $options->set('fontDir', $workDir);
        $options->set('fontCache', $workDir);
        $options->set('tempDir', $workDir);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return [$dompdf->output(), 'audit-report-' . $generatedAt->format('Y-m-d_His') . '.pdf'];
    }
}
