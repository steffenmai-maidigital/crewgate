<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogController extends Controller
{
    public function index(Request $request)
    {
        // „typ“ = alter Parametername (Lesezeichen) / "typ" = old parameter name (bookmarks)
        $type = $request->query('type', $request->query('typ'));

        $logs = ActivityLog::query()->with('onboarding')
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('logs', [
            'logs' => $logs,
            'type' => $type,
            'types' => ['' => 'Alle Einträge'] + array_intersect_key(ActivityLog::LABELS, array_flip([
                ActivityLog::TYPE_CASE, ActivityLog::TYPE_FIELD, ActivityLog::TYPE_STEP,
                ActivityLog::TYPE_MAIL, ActivityLog::TYPE_DELETION, ActivityLog::TYPE_SETTINGS,
            ])),
        ]);
    }

    /**
     * PDF-Audit-Bericht herunterladen (Firmendaten, Zeitstempel, Löschungen, Vorgänge)
     *
     * Download the PDF audit report (company data, timestamp, deletions, cases)
     */
    public function exportPdf(\App\Services\ReportService $reports)
    {
        $user = $this->user();

        // Häufigste Ursache für Fehler: Image wurde nach dem Update nicht neu
        // gebaut, dann fehlt die dompdf-Bibliothek.
        // Most common cause of errors: the image was not rebuilt after the
        // update, so the dompdf library is missing.
        if (! class_exists(\Dompdf\Dompdf::class)) {
            return back()->withErrors([
                'export' => 'PDF-Export nicht möglich: Die Bibliothek "dompdf" ist im Container nicht installiert. '
                    . 'Bitte das Docker-Image ohne Cache neu bauen (docker compose build --no-cache && docker compose up -d).',
            ]);
        }

        try {
            [$pdf, $filename] = $reports->buildAuditPdf($user['name'] ?? $user['username'] ?? 'unbekannt');
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('PDF-Audit-Bericht fehlgeschlagen: ' . $e->getMessage(), ['exception' => $e]);

            return back()->withErrors([
                'export' => 'PDF-Export fehlgeschlagen: ' . $e->getMessage()
                    . ' (Details in storage/logs/laravel.log)',
            ]);
        }

        ActivityLog::write(ActivityLog::TYPE_EXPORT, 'Audit-Bericht als PDF exportiert', $user['username'] ?? null);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Vollständiger CSV-Export des Protokolls (append-only, inkl. Hash-Kette
     * zur Integritätsprüfung). Der Export selbst wird protokolliert.
     *
     * Full CSV export of the log (append-only, incl. hash chain
     * for integrity checks). The export itself is logged.
     */
    public function export(Request $request): StreamedResponse
    {
        $user = $this->user();

        ActivityLog::write(ActivityLog::TYPE_EXPORT, 'Protokoll als CSV exportiert', $user['username'] ?? null);

        $filename = 'audit-log-' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8-BOM für Excel / UTF-8 BOM for Excel

            if (config('test.enabled')) {
                fputcsv($out, ['TESTSYSTEM – Export aus einem Testsystem / Export from a TEST system'], ';');
            }
            fputcsv($out, ['ID', 'Zeitpunkt / Timestamp', 'Typ / Type', 'Benutzer / User', 'Vorgang-ID / Case ID', 'Vorgang / Case', 'Prozess / Process', 'Ereignis / Event', 'Details', 'Hash (SHA-256-Kette / chain)'], ';');

            ActivityLog::orderBy('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->type,
                        $log->user ?? 'System',
                        $log->onboarding_id,
                        $log->onboarding_name,
                        $log->onboarding?->processLabel(),
                        $log->message,
                        $log->details ? json_encode($log->details, JSON_UNESCAPED_UNICODE) : '',
                        $log->hash,
                    ], ';');
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
