<?php

namespace App\Http\Controllers;

use App\Models\Onboarding;
use App\Models\Role;

class DashboardController extends Controller
{
    public function index()
    {
        $user = $this->user();
        $roleIds = $user['role_ids'] ?? [];

        // Offene Vorgänge, bei denen mindestens eine Rolle des Benutzers den
        // Erhalt noch nicht quittiert hat – inkl. Kennzeichen je Rolle, ob die
        // Pflichtdaten (z. B. vom Vorgesetzten) schon ausgefüllt sind.
        // Open cases where at least one of the user's roles has not yet
        // acknowledged receipt – incl. a flag per role whether the
        // mandatory data (e.g. from the supervisor) has already been filled in.
        $myTasks = collect();
        if ($roleIds !== []) {
            $userRoles = Role::workflow()->whereIn('id', $roleIds)->values();
            $userRoles->each->load(['fields' => fn ($q) => $q->where('active', true)]);

            $myTasks = Onboarding::with(['steps', 'values'])
                ->where('status', 'open')
                ->orderBy('created_at')
                ->get()
                ->map(function ($o) use ($userRoles) {
                    $done = $o->steps->pluck('role_id')->all();
                    $stored = $o->values->pluck('value', 'custom_field_id');

                    $procIds = $o->workflowRoles()->pluck('id')->all();
                    $o->my_pending = $userRoles
                        ->filter(fn ($r) => in_array($r->id, $procIds, true))
                        ->reject(fn ($r) => in_array($r->id, $done, true))
                        ->map(fn ($r) => [
                            'role' => $r,
                            'data_ready' => $r->fields->where('required', true)
                                ->filter(fn ($f) => $f->appliesTo($o->process ?? 'onboarding'))
                                ->every(fn ($f) => filled($stored[$f->id] ?? null)),
                        ])->values();

                    return $o;
                })
                ->filter(fn ($o) => $o->my_pending->isNotEmpty())
                ->values();
        }

        // Vorgänge in der Abteilung des Benutzers (Sicht des Vorgesetzten)
        // Cases in the user's department (supervisor's view)
        $departmentOnboardings = collect();
        if (! empty($user['department'])) {
            $departmentOnboardings = Onboarding::with('steps.role')
                ->where('department', $user['department'])
                ->orderByDesc('created_at')
                ->get();
        }

        // HR: alle Vorgänge, getrennt nach offen und abgeschlossen,
        // jeweils nach Datum sortiert – inkl. ausstehender Quittierungen
        // HR: all cases, split into open and completed,
        // each sorted by date – incl. outstanding acknowledgements
        $openCases = collect();
        $closedCases = collect();
        if ($user['is_hr'] ?? false) {
            $all = Onboarding::with('steps.role')->get();
            $openCases = $all->where('status', '!=', 'done')
                ->sortByDesc('created_at')->values();
            $closedCases = $all->where('status', 'done')
                ->sortByDesc('completed_at')->values();
        }

        // Workflow-Beteiligte können alle Vorgänge öffnen (eingeschränkte Sicht)
        // Workflow participants can open all cases (restricted view)
        $participating = collect();
        if (! ($user['is_hr'] ?? false) && $roleIds !== []) {
            $participating = Onboarding::with('steps.role')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get();
        }

        // Kennzahlen: sichtbarer Bestand des Benutzers
        // Key figures: the user's visible cases
        $scope = Onboarding::query();
        if (! (($user['is_hr'] ?? false) || ($user['is_admin'] ?? false) || $roleIds !== [])) {
            if (! empty($user['department'])) {
                $scope->where('department', $user['department']);
            } else {
                $scope->whereRaw('1 = 0');
            }
        }
        $counts = $scope->get()->countBy('status');
        $stats = [
            'draft'  => (int) ($counts['draft'] ?? 0),
            'active' => (int) ($counts['open'] ?? 0) + (int) ($counts['review'] ?? 0),
            'done'   => (int) ($counts['done'] ?? 0),
        ];

        return view('dashboard', [
            'user' => $user,
            'myTasks' => $myTasks,
            'departmentOnboardings' => $departmentOnboardings,
            'openCases' => $openCases,
            'closedCases' => $closedCases,
            'stats' => $stats,
            'participating' => $participating,
            'workflow' => Role::workflow(),
        ]);
    }
}
