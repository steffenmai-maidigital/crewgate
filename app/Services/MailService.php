<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\MailLog;
use App\Models\Onboarding;
use App\Models\Role;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MailService
{

    /**
     * Platzhalter in einer Vorlage ersetzen ({name}, {link}, …)
     *
     * Replace placeholders in a template ({name}, {link}, …)
     */
    private static function render(string $template, array $vars): string
    {
        $map = [];
        foreach ($vars as $k => $v) {
            $map['{' . $k . '}'] = (string) $v;
        }
        // Alte (deutsche) Platzhalter in gespeicherten Vorlagen gelten weiter
        // Old (German) placeholders in stored templates keep working
        foreach (self::PLACEHOLDER_ALIASES as $old => $new) {
            if (array_key_exists($new, $vars)) {
                $map['{' . $old . '}'] = (string) $vars[$new];
            }
        }

        return strtr($template, $map);
    }

    /**
     * Alte Platzhalter (vor Standard 10) → neue; die Migration
     * rename_to_english stellt gespeicherte Vorlagen damit um
     *
     * Old placeholders (before standard 10) → new ones; the migration
     * rename_to_english converts stored templates with it
     */
    public const PLACEHOLDER_ALIASES = [
        'vorgesetzter' => 'supervisor',
        'prozess'      => 'process',
        'abteilung'    => 'department',
        'eintritt'     => 'date',
        'rolle'        => 'role',
        'daten'        => 'data',
        'frist'        => 'due_date',
        'aufgaben'     => 'tasks',
        'vorname'      => 'first_name',
        'nachname'     => 'last_name',
    ];

    public const ROLE_TEMPLATE_DE = "Guten Tag,\n\nim Prozess \"{process}\" für {name} (Abteilung: {department}, Datum: {date}) liegt jetzt eine Aufgabe bei \"{role}\".\n\nFolgende Angaben liegen bereits vor (vom Vorgesetzten bzw. aus der Anlage durch HR):\n\n{data}\n\nBitte loggen Sie sich im CrewGate-Portal ein, prüfen bzw. ergänzen Sie die Felder Ihrer Abteilung und quittieren Sie dort den Erhalt und die Umsetzung:\n{link}\n\nDiese Nachricht wurde automatisch an die zentrale Adresse der Rolle gesendet.";

    public const ROLE_TEMPLATE_EN = "Hello,\n\na task for \"{role}\" is now pending in the \"{process}\" process for {name} (department: {department}, date: {date}).\n\nThe following details have already been provided (by the supervisor or by HR at creation):\n\n{data}\n\nPlease log in to the CrewGate portal, review or complete your department's fields and acknowledge receipt and implementation there:\n{link}\n\nThis message was sent automatically to the role's central address.";

    public const SUPERVISOR_TEMPLATE_DE = "Guten Tag {supervisor},\n\nfür Ihren Mitarbeiter {name} (Datum: {date}) wurde von der Personalabteilung ein Vorgang der Art \"{process}\" angelegt.\n\nWas Sie im CrewGate-Portal tun:\n  - Sie melden sich mit Ihrem Windows-Konto an.\n  - Sie finden den Vorgang in der Übersicht unter Ihrer Abteilung.\n  - Sie pflegen die Angaben für die beteiligten Abteilungen. Zwischenspeichern ist jederzeit möglich.\n  - Zum Schluss senden Sie den Vorgang ab – erst dann werden alle Abteilungen benachrichtigt und quittieren den Erhalt.\n\nCrewGate-Portal:\n{link}\n\nDiese Nachricht wurde automatisch versendet.";

    public const CANCEL_TEMPLATE_DE = "Guten Tag {supervisor},\n\nder Vorgang \"{process}\" für {name} wurde von der Personalabteilung abgebrochen.\n\nEs sind keine weiteren Schritte von Ihnen erforderlich. Bereits erfasste Angaben bleiben im Portal einsehbar, bis der Vorgang gelöscht wird.\n\nCrewGate-Portal:\n{link}\n\nDiese Nachricht wurde automatisch versendet.";

    public const CANCEL_TEMPLATE_EN = "Hello {supervisor},\n\nthe \"{process}\" case for {name} has been cancelled by HR.\n\nNo further action is required from you. Details already entered remain visible in the portal until the case is deleted.\n\nCrewGate portal:\n{link}\n\nThis message was sent automatically.";

    public const REMINDER_SUPERVISOR_TEMPLATE_DE = "Guten Tag {supervisor},\n\ndie Frist ({due_date}) für die Vorbereitung des Vorgangs \"{process}\" für {name} ist erreicht.\n\nZusammenfassung der offenen Aufgaben:\n{tasks}\n\nBitte melden Sie sich im CrewGate-Portal an, vervollständigen Sie die Angaben und senden Sie den Vorgang ab:\n{link}\n\nDiese Nachricht wurde automatisch versendet.";

    public const REMINDER_SUPERVISOR_TEMPLATE_EN = "Hello {supervisor},\n\nthe due date ({due_date}) for preparing the \"{process}\" case for {name} has been reached.\n\nSummary of open tasks:\n{tasks}\n\nPlease log in to the CrewGate portal, complete the details and submit the case:\n{link}\n\nThis message was sent automatically.";

    public const REMINDER_ROLE_TEMPLATE_DE = "Guten Tag,\n\ndie Frist ({due_date}) für den Vorgang \"{process}\" für {name} ist erreicht – die Quittierung von \"{role}\" steht noch aus.\n\nVorliegende Angaben:\n\n{data}\n\nBitte loggen Sie sich im CrewGate-Portal ein und quittieren Sie den Erhalt und die Umsetzung:\n{link}\n\nDiese Nachricht wurde automatisch versendet.";

    public const REMINDER_ROLE_TEMPLATE_EN = "Hello,\n\nthe due date ({due_date}) for the \"{process}\" case for {name} has been reached – the acknowledgement of \"{role}\" is still pending.\n\nAvailable details:\n\n{data}\n\nPlease log in to the CrewGate portal and acknowledge receipt and implementation:\n{link}\n\nThis message was sent automatically.";

    public const SUPERVISOR_TEMPLATE_EN = "Hello {supervisor},\n\na \"{process}\" case has been created by HR for your employee {name} (date: {date}).\n\nWhat you do in the CrewGate portal:\n  - Sign in with your Windows account.\n  - Find the case in the overview under your department.\n  - Maintain the details for the departments involved. You can save a draft at any time.\n  - Finally, submit the case – only then are all departments notified and acknowledge receipt.\n\nCrewGate portal:\n{link}\n\nThis message was sent automatically.";

    /**
     * Pauschaler Portal-Link für alle E-Mails (Einstellungen), kein Vorgangs-Link
     *
     * General portal link for all e-mails (settings), not a case link
     */
    public static function portalUrl(): string
    {
        return Setting::get('portal_url') ?: (string) config('app.url');
    }

    /**
     * Text-Mail versenden und vollständig protokollieren
     * (Mail-Log mit Text + Aktivitätsprotokoll).
     *
     * Send a text mail and log it completely
     * (mail log with text + activity log).
     */
    public static function sendText(string|array $to, string $subject, string $body, ?Onboarding $onboarding = null): bool
    {
        $recipients = (array) $to;
        $ok = true;
        $error = null;

        try {
            Mail::raw($body, function ($m) use ($recipients, $subject) {
                $m->to($recipients)->subject($subject);
            });
        } catch (\Throwable $e) {
            $ok = false;
            $error = $e->getMessage();
            Log::warning('Mailversand fehlgeschlagen: ' . $error);
        }

        try {
            MailLog::create([
                'recipient' => implode(', ', $recipients),
                'subject'   => $subject,
                'body'      => $body,
                'status'    => $ok ? MailLog::STATUS_SENT : MailLog::STATUS_FAILED,
                'error'     => $error,
                'onboarding_id'   => $onboarding?->id,
                'onboarding_name' => $onboarding?->fullName(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Mail-Log konnte nicht geschrieben werden: ' . $e->getMessage());
        }

        ActivityLog::write(ActivityLog::TYPE_MAIL, ($ok ? 'E-Mail versendet an ' : 'E-Mail-Versand FEHLGESCHLAGEN an ') . implode(', ', $recipients), null, $onboarding, array_filter([
            'Empfänger' => implode(', ', $recipients),
            'Betreff'   => $subject,
            'Fehler'    => $error,
        ]));

        return $ok;
    }

    /**
     * Ausgefüllte Daten als sortierten Text für eine Rolle aufbereiten:
     * eigene Felder plus die per "Sieht Daten von" freigegebenen Rollen.
     * Sortierung: HR-Anlage zuerst, dann Rollen nach Workflow-Reihenfolge,
     * innerhalb der Rolle nach Feld-Reihenfolge.
     *
     * Prepare filled-in data as sorted text for a role:
     * own fields plus the roles shared via "Sieht Daten von" (sees data of).
     * Sorting: HR intake first, then roles in workflow order,
     * within a role by field order.
     */
    public static function dataTextForRole(Onboarding $onboarding, Role $role): string
    {
        $allowed = array_unique(array_merge(
            [$role->id],
            array_map('intval', $role->visible_role_ids ?? []),
        ));

        $values = $onboarding->values()->with('field.role')->get()
            ->filter(fn ($v) => $v->field && (($v->field->visible_to_all ?? false)
                || in_array((int) $v->field->role_id, $allowed, true)))
            ->filter(fn ($v) => $v->field->displayValue($v->value) !== null)
            ->sortBy(fn ($v) => [
                $v->field->role?->is_intake ? 0 : 1,
                $v->field->role?->sort_order ?? 999,
                $v->field->sort_order,
            ]);

        if ($values->isEmpty()) {
            return 'Es liegen noch keine Angaben vor.';
        }

        $lines = [];
        $currentRole = null;

        foreach ($values as $v) {
            $roleName = $v->field->role?->name ?? 'Allgemein';
            if ($roleName !== $currentRole) {
                $lines[] = ($currentRole !== null ? "\n" : '') . $roleName . ':';
                $currentRole = $roleName;
            }
            $lines[] = '  - ' . $v->field->bilingualLabel() . ': ' . $v->field->displayValue($v->value);
        }

        return implode("\n", $lines);
    }

    /**
     * Benachrichtigung an die zentrale Adresse einer Rolle (Text mit Daten)
     *
     * Notification to the central address of a role (text with data)
     */
    public static function notifyRole(Onboarding $onboarding, Role $role): void
    {
        if (! $role->central_email) {
            ActivityLog::write(ActivityLog::TYPE_MAIL, 'Keine Benachrichtigung an "' . $role->name . '" – keine zentrale E-Mail-Adresse gepflegt', null, $onboarding);
            return;
        }

        $subject = $onboarding->processLabel() . ': ' . $onboarding->fullName() . ' – Aufgabe für / Task for ' . $role->name;

        $vars = [
            'name'      => $onboarding->fullName(),
            'department' => $onboarding->department ?? 'nicht gepflegt / not set',
            'date'      => $onboarding->start_date?->format('d.m.Y') ?? 'offen / open',
            'role'      => $role->name,
            'data'      => static::dataTextForRole($onboarding, $role),
            'link'      => static::portalUrl(),
            'supervisor' => $onboarding->supervisor_name ?? '',
            'process'   => $onboarding->processLabel(),
        ];

        // Vorlagen aus den Einstellungen (leer = Standardtext);
        // erst deutscher, dann englischer Text
        // Templates from the settings (empty = default text);
        // German text first, then English
        $de = Setting::get('mail_tpl_role_de') ?: static::ROLE_TEMPLATE_DE;
        $en = Setting::get('mail_tpl_role_en') ?: static::ROLE_TEMPLATE_EN;

        $body = static::render($de, $vars)
            . "\n\n----------------------------------------\n\n"
            . static::render($en, $vars);

        static::sendText($role->central_email, $subject, $body, $onboarding);
    }

    /** Vorlage aus den Einstellungen oder Standard / Template from the settings or default */
    public static function template(string $settingKey, string $default): string
    {
        return Setting::get($settingKey) ?: $default;
    }

    /**
     * Vorlage (Einstellung oder Standard) mit Platzhaltern füllen
     *
     * Fill the template (setting or default) with placeholders
     */
    public static function renderTemplate(string $settingKey, string $default, array $vars): string
    {
        return static::render(static::template($settingKey, $default), $vars);
    }

    /**
     * Abbruch: Vorgesetzten informieren (zweisprachig, Vorlage anpassbar)
     *
     * Cancellation: inform the supervisor (bilingual, template customisable)
     */
    public static function notifyCancelled(Onboarding $onboarding): void
    {
        if (! $onboarding->supervisor_email) {
            return;
        }

        $vars = [
            'supervisor' => $onboarding->supervisor_name ?? $onboarding->supervisor_username,
            'name' => $onboarding->fullName(),
            'first_name' => $onboarding->first_name,
            'last_name' => $onboarding->last_name,
            'process'   => $onboarding->processLabel(),
            'department' => $onboarding->department ?? '–',
            'date'      => $onboarding->start_date?->format('d.m.Y') ?? '–',
            'link' => static::portalUrl(),
        ];

        $de = static::render(static::template('mail_tpl_cancel_de', static::CANCEL_TEMPLATE_DE), $vars);
        $en = static::render(static::template('mail_tpl_cancel_en', static::CANCEL_TEMPLATE_EN), $vars);

        static::sendText(
            $onboarding->supervisor_email,
            'Vorgang abgebrochen / Case cancelled: ' . $onboarding->processLabel() . ' – ' . $onboarding->fullName(),
            $de . "\n\n----------------------------------------\n\n" . $en,
            $onboarding
        );
    }

    /**
     * Info-Mail an den Vorgesetzten nach Anlage des Vorgangs (nur Portal-Link)
     *
     * Info mail to the supervisor after the case is created (portal link only)
     */
    public static function notifySupervisor(Onboarding $onboarding): void
    {
        if (! $onboarding->supervisor_email) {
            ActivityLog::write(ActivityLog::TYPE_MAIL, 'Keine Vorgesetzten-Mail – keine E-Mail-Adresse im AD gefunden', null, $onboarding);
            return;
        }

        $subject = $onboarding->processLabel() . ' – Vorgang angelegt / case created: ' . $onboarding->fullName() . ' – Ihre Angaben werden benötigt / your input is needed';

        $vars = [
            'name'      => $onboarding->fullName(),
            'department' => $onboarding->department ?? 'nicht gepflegt / not set',
            'date'      => $onboarding->start_date?->format('d.m.Y') ?? 'offen / open',
            'role'      => '',
            'data'      => '',
            'link'      => static::portalUrl(),
            'supervisor' => $onboarding->supervisor_name ?? '',
            'process'   => $onboarding->processLabel(),
        ];

        $de = Setting::get('mail_tpl_supervisor_de') ?: static::SUPERVISOR_TEMPLATE_DE;
        $en = Setting::get('mail_tpl_supervisor_en') ?: static::SUPERVISOR_TEMPLATE_EN;

        $body = static::render($de, $vars)
            . "\n\n----------------------------------------\n\n"
            . static::render($en, $vars);

        static::sendText($onboarding->supervisor_email, $subject, $body, $onboarding);
    }
}
