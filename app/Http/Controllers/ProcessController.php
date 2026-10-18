<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Onboarding;
use App\Models\Process;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Prozessarten pflegen (HR und Administratoren) / Maintain process types (HR and administrators) */
class ProcessController extends Controller
{
    /**
     * Auditoren haben Lesezugriff auf das Protokoll, pflegen aber keine Prozesse
     *
     * Auditors have read access to the log but do not maintain processes
     */
    private function guard(): void
    {
        $u = $this->user();
        abort_unless(($u['is_hr'] ?? false) || ($u['is_admin'] ?? false), 403, 'Prozesse pflegen nur HR und Administratoren.');
    }

    public function index()
    {
        $this->guard();

        return view('processes', [
            'processes' => Process::orderBy('sort_order')->orderBy('name')->get(),
            'roles' => Role::workflow(),
        ]);
    }

    public function store(Request $request)
    {
        $this->guard();

        $data = $this->validated($request);
        $data['key'] = Str::slug($data['name']);

        if ($data['key'] === '' || Process::where('key', $data['key'])->exists()) {
            return back()->withErrors(['name' => __('Es gibt bereits einen Prozess mit diesem Namen.')])->withInput();
        }

        $process = Process::create($data);
        ActivityLog::write(ActivityLog::TYPE_SETTINGS, 'Prozess "' . $process->name . '" angelegt', $this->user()['username'] ?? null);

        return back()->with('ok', __('Prozess angelegt.'));
    }

    public function update(Request $request, Process $process)
    {
        $this->guard();

        $data = $this->validated($request);
        unset($data['key']); // der Schlüssel bleibt stabil (Feld-Zuordnungen!) / key stays stable (field mappings!)

        $process->update($data);
        ActivityLog::write(ActivityLog::TYPE_SETTINGS, 'Prozess "' . $process->name . '" geändert', $this->user()['username'] ?? null);

        return back()->with('ok', __('Prozess gespeichert.'));
    }

    public function destroy(Process $process)
    {
        $this->guard();

        abort_if($process->is_system, 403, 'Eingebaute Prozesse können nicht gelöscht, nur deaktiviert werden.');

        if (Onboarding::where('process', $process->key)->exists()) {
            return back()->withErrors(['process' => __('Der Prozess wird von Vorgängen verwendet und kann nur deaktiviert werden.')]);
        }

        ActivityLog::write(ActivityLog::TYPE_SETTINGS, 'Prozess "' . $process->name . '" gelöscht', $this->user()['username'] ?? null);
        $process->delete();

        return back()->with('ok', __('Prozess gelöscht.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'name_en' => ['nullable', 'string', 'max:100'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'role_ids' => ['nullable', 'array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        return [
            'name' => $data['name'],
            'name_en' => $data['name_en'] ?? null,
            'sort_order' => $data['sort_order'],
            // leer = alle Workflow-Rollen beteiligt
            // empty = all workflow roles involved
            'role_ids' => array_map('intval', $data['role_ids'] ?? []),
            'active' => $request->boolean('active', true),
        ];
    }
}
