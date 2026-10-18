<?php

namespace App\Console\Commands;

use App\Models\Onboarding;
use App\Models\Setting;
use App\Services\MailService;
use Illuminate\Console\Command;

/**
 * Fristen prüfen und Erinnerungen versenden (einmalig je Vorgang):
 * - Vorgesetzten-Frist erreicht + Vorgang noch in Vorbereitung
 *   -> Zusammenfassung der offenen Aufgaben an den Vorgesetzten
 * - Abteilungs-Frist erreicht + Quittierungen offen
 *   -> Erinnerung an die ausstehenden Rollen (nur wenn in den
 *      Einstellungen aktiviert: reminder_roles_enabled)
 *
 * Check deadlines and send reminders (once per case):
 * - supervisor deadline reached + case still in preparation
 *   -> summary of the open tasks to the supervisor
 * - department deadline reached + acknowledgements outstanding
 *   -> reminder to the pending roles (only if enabled in the
 *      settings: reminder_roles_enabled)
 */
class SendDueReminders extends Command
{
    protected $signature = 'reminders:send';

    protected $description = 'Fristen prüfen und Erinnerungs-E-Mails versenden';

    public function handle(): int
    {
        $today = now()->startOfDay();
        $link = MailService::portalUrl();

        // 1) Vorgesetzten-Erinnerung: Vorbereitung nicht abgesendet
        // 1) Supervisor reminder: preparation not submitted
        $due = Onboarding::where('status', 'draft')
            ->whereNotNull('supervisor_due_date')
            ->whereDate('supervisor_due_date', '<=', $today)
            ->whereNull('supervisor_reminder_sent_at')
            ->get();

        foreach ($due as $o) {
            if (! $o->supervisor_email) {
                $o->update(['supervisor_reminder_sent_at' => now()]);
                continue;
            }

            // Offene Aufgaben zusammenfassen: fehlende Pflichtangaben je Abteilung
            // Summarise open tasks: missing mandatory details per department
            $stored = $o->values->pluck('value', 'custom_field_id');
            $lines = [];
            foreach ($o->workflowRoles() as $role) {
                $missing = $role->fields()->where('active', true)->where('required', true)
                    ->forProcess($o->process ?? \App\Models\Process::ONBOARDING)->get()
                    ->filter(fn ($f) => blank($stored[$f->id] ?? null))
                    ->map(fn ($f) => $f->bilingualLabel());
                if ($missing->isNotEmpty()) {
                    $lines[] = $role->name . ': ' . $missing->implode(', ');
                }
            }
            $openText = $lines === []
                ? 'Alle Pflichtangaben sind erfasst – es fehlt nur noch das Absenden. / All required details are filled in – only submission is missing.'
                : implode("\n", array_map(fn ($l) => '  - ' . $l, $lines));

            $subject = 'Erinnerung / Reminder: ' . $o->processLabel() . ' – ' . $o->fullName() . ' wartet auf Ihre Angaben';

            $vars = [
                'supervisor' => $o->supervisor_name ?? $o->supervisor_username,
                'name' => $o->fullName(),
                'first_name' => $o->first_name,
                'last_name' => $o->last_name,
                'process' => $o->processLabel(),
                'due_date' => $o->supervisor_due_date->format('d.m.Y'),
                'tasks' => $openText,
                'link' => $link,
            ];
            $body = MailService::renderTemplate('mail_tpl_reminder_supervisor_de', MailService::REMINDER_SUPERVISOR_TEMPLATE_DE, $vars)
                . "\n\n----------------------------------------\n\n"
                . MailService::renderTemplate('mail_tpl_reminder_supervisor_en', MailService::REMINDER_SUPERVISOR_TEMPLATE_EN, $vars);

            MailService::sendText($o->supervisor_email, $subject, $body, $o);
            $o->update(['supervisor_reminder_sent_at' => now()]);
            $this->info('Vorgesetzten-Erinnerung: ' . $o->fullName());
        }

        // 2) Abteilungs-Erinnerung (nur wenn eingeschaltet)
        // 2) Department reminder (only if switched on)
        if (Setting::get('reminder_roles_enabled') === '1') {
            $due = Onboarding::where('status', 'open')
                ->whereNotNull('roles_due_date')
                ->whereDate('roles_due_date', '<=', $today)
                ->whereNull('roles_reminder_sent_at')
                ->get();

            foreach ($due as $o) {
                foreach ($o->pendingRoles() as $role) {
                    if (! $role->central_email) {
                        continue;
                    }

                    $subject = 'Erinnerung / Reminder: ' . $o->processLabel() . ' – ' . $o->fullName() . ' – Quittierung ausstehend für ' . $role->name;

                    $vars = [
                        'role' => $role->name,
                        'name' => $o->fullName(),
                        'first_name' => $o->first_name,
                        'last_name' => $o->last_name,
                        'process' => $o->processLabel(),
                        'due_date' => $o->roles_due_date->format('d.m.Y'),
                        'data' => MailService::dataTextForRole($o, $role),
                        'link' => $link,
                    ];
                    $body = MailService::renderTemplate('mail_tpl_reminder_role_de', MailService::REMINDER_ROLE_TEMPLATE_DE, $vars)
                        . "\n\n----------------------------------------\n\n"
                        . MailService::renderTemplate('mail_tpl_reminder_role_en', MailService::REMINDER_ROLE_TEMPLATE_EN, $vars);

                    MailService::sendText($role->central_email, $subject, $body, $o);
                }
                $o->update(['roles_reminder_sent_at' => now()]);
                $this->info('Abteilungs-Erinnerung: ' . $o->fullName());
            }
        }

        return self::SUCCESS;
    }
}
