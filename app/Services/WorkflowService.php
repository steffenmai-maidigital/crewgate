<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\Role;

class WorkflowService
{
    /**
     * Vorgang absenden (durch Vorgesetzten/HR nach der Datenpflege):
     * ALLE Workflow-Rollen werden gleichzeitig mit ihren Daten benachrichtigt
     * und müssen den Erhalt einzeln quittieren.
     *
     * Submit a case (by supervisor/HR after maintaining the data):
     * ALL workflow roles are notified with their data at the same time
     * and must each acknowledge receipt individually.
     */
    public function start(Onboarding $onboarding): void
    {
        $roles = $onboarding->workflowRoles();

        $onboarding->update([
            'status' => 'open',
            'current_role_id' => null,
        ]);

        ActivityLog::write(
            ActivityLog::TYPE_CASE,
            'Vorgang abgesendet – ' . $roles->count() . ' Abteilung(en) benachrichtigt, Quittierung ausstehend',
            $onboarding->created_by,
            $onboarding,
        );

        foreach ($roles as $role) {
            MailService::notifyRole($onboarding, $role);
        }
    }

    /**
     * Eine Rolle quittiert den Erhalt ihrer Informationen. Haben alle
     * Workflow-Rollen quittiert, wartet der Vorgang auf den HR-Abschluss.
     *
     * A role acknowledges receipt of its information. Once all
     * workflow roles have acknowledged, the case waits for HR completion.
     */
    public function acknowledge(Onboarding $onboarding, Role $role, string $by): void
    {
        if ($onboarding->steps()->where('role_id', $role->id)->exists()) {
            return; // bereits quittiert / already acknowledged
        }

        $onboarding->steps()->create([
            'role_id'      => $role->id,
            'completed_by' => $by,
            'completed_at' => now(),
        ]);

        ActivityLog::write(ActivityLog::TYPE_STEP, 'Erhalt quittiert durch "' . $role->name . '"', $by, $onboarding);

        $onboarding->load('steps');

        if ($onboarding->pendingRoles()->isEmpty()) {
            $onboarding->update(['status' => 'review']);
            ActivityLog::write(ActivityLog::TYPE_CASE, 'Alle Abteilungen haben quittiert – wartet auf HR-Abschluss', $by, $onboarding);
        }
    }
}
