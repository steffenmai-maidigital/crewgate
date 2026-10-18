<?php

namespace App\Http\Controllers\Api\V2;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\Process;
use App\Models\Role;
use App\Services\LdapService;
use App\Services\MailService;
use Illuminate\Http\Request;

/**
 * API v2: Vorgänge anlegen, Status abrufen, gepflegte Werte abrufen.
 * Authentifizierung über das API-Token (Middleware api.token).
 *
 * API v2: create cases, query status, query maintained values.
 * Authentication via the API token (middleware api.token).
 */
class OnboardingApiController extends Controller
{
    /**
     * POST /api/v2/cases – Vorgang anlegen (Status: in Vorbereitung);
     * alte Adresse /api/v2/vorgaenge und alte Prozess-Schlüssel gelten weiter
     *
     * POST /api/v2/cases – create a case (status: in preparation);
     * the old address /api/v2/vorgaenge and old process keys keep working
     */
    public function store(Request $request, LdapService $ldap)
    {
        if ($request->filled('process')) {
            $request->merge(['process' => Process::currentKey((string) $request->input('process'))]);
        }
        $data = $request->validate([
            'process' => ['required', 'exists:processes,key'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'supervisor_username' => ['required', 'string', 'max:100'],
            'supervisor_due_date' => ['nullable', 'date'],
            'roles_due_date' => ['nullable', 'date', 'after_or_equal:supervisor_due_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'fields' => ['nullable', 'array'], // Anlage-Felder: {field_id: value} / intake fields: {field_id: value}
        ]);

        $supervisor = null;
        try {
            $supervisor = $ldap->findUser($data['supervisor_username']);
        } catch (\Throwable) {
            // AD nicht erreichbar: Vorgang trotzdem anlegen
            // AD unreachable: create the case anyway
        }

        $onboarding = Onboarding::create([
            ...collect($data)->except('fields')->all(),
            'supervisor_name'  => $supervisor['name'] ?? null,
            'supervisor_email' => $supervisor['mail'] ?? null,
            'department'       => $supervisor['department'] ?? null,
            'created_by'       => 'API',
            'status'           => 'draft',
        ]);

        // Optionale Anlage-Feldwerte (nur gültige Felder des Prozesses)
        // Optional intake field values (only valid fields of the process)
        $intakeFields = Role::intake()?->fields()->where('active', true)
            ->where('fill_stage', \App\Models\CustomField::STAGE_INTAKE)->forProcess($data['process'])->get() ?? collect();

        foreach ($intakeFields as $field) {
            $raw = $data['fields'][$field->id] ?? $data['fields'][$field->label] ?? null;
            if ($raw === null || $raw === '' || $raw === []) {
                continue;
            }
            $onboarding->values()->create([
                'custom_field_id' => $field->id,
                'value' => $field->prepareValue($raw),
                'filled_by' => 'API',
            ]);
        }

        ActivityLog::write(ActivityLog::TYPE_CASE, 'Vorgang angelegt über API v2 (' . $onboarding->processLabel() . ')', 'API', $onboarding);
        MailService::notifySupervisor($onboarding);

        return response()->json($this->summary($onboarding->fresh(['steps.role'])), 201);
    }

    /**
     * GET /api/v2/cases – Statusliste (Filter: status, process)
     *
     * GET /api/v2/cases – status list (filters: status, process)
     */
    public function index(Request $request)
    {
        $cases = Onboarding::with('steps.role')
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('process'), fn ($q) => $q->where('process', Process::currentKey((string) $request->string('process'))))
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        return response()->json(['data' => $cases->map(fn ($o) => $this->summary($o))->all()]);
    }

    /**
     * GET /api/v2/cases/{id} – Status + Quittierungen + gepflegte Werte
     *
     * GET /api/v2/cases/{id} – status + acknowledgements + maintained values
     */
    public function show(Onboarding $onboarding)
    {
        $onboarding->load(['steps.role', 'values.field.role']);

        $values = $onboarding->values
            ->filter(fn ($v) => $v->field)
            ->sortBy(fn ($v) => [$v->field->role?->is_intake ? 0 : 1, $v->field->role?->sort_order ?? 999, $v->field->sort_order])
            ->values()
            ->map(fn ($v) => [
                'field_id' => $v->custom_field_id,
                'role' => $v->field->role?->name,
                'label' => $v->field->label,
                'label_en' => $v->field->label_en,
                'value' => $v->value,
                'display_value' => $v->field->displayValue($v->value),
                'filled_by' => $v->filled_by,
            ])->all();

        return response()->json($this->summary($onboarding) + ['values' => $values]);
    }

    /** Einheitliche Status-Darstellung eines Vorgangs / Uniform status representation of a case */
    private function summary(Onboarding $o): array
    {
        return [
            'id' => $o->id,
            'first_name' => $o->first_name,
            'last_name' => $o->last_name,
            'process' => $o->process,
            // Alter (deutscher) Prozess-Schlüssel, nur noch in dieser Version (Standard 10)
            // Old (German) process key, in this version only (standard 10)
            'process_legacy' => Process::legacyKey($o->process),
            'process_label' => $o->processLabel(),
            'status' => $o->status,
            'status_label' => $o->statusLabel(),
            'department' => $o->department,
            'supervisor' => $o->supervisor_username,
            'start_date' => $o->start_date?->toDateString(),
            'supervisor_due_date' => $o->supervisor_due_date?->toDateString(),
            'roles_due_date' => $o->roles_due_date?->toDateString(),
            'created_at' => $o->created_at->toIso8601String(),
            'completed_at' => $o->completed_at?->toIso8601String(),
            'acknowledged_roles' => $o->steps->map(fn ($s) => ['role' => $s->role?->name, 'by' => $s->completed_by, 'at' => $s->completed_at?->toIso8601String()])->all(),
            'pending_roles' => $o->status === 'open' ? $o->pendingRoles()->pluck('name')->all() : [],
        ];
    }
}
