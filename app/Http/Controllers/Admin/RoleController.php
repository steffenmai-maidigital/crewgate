<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Services\LdapService;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        return view('admin.roles', [
            'roles'       => Role::orderBy('sort_order')->orderBy('id')->get(),
            'directory'   => config('demo.enabled') || \App\Support\Directory::enabled(),
        ]);
    }

    /** AD-Gruppen einlesen und als Rollen anlegen / Read AD groups and create them as roles */
    public function sync(LdapService $ldap)
    {
        try {
            $count = $ldap->syncGroupsToRoles();
        } catch (\Throwable $e) {
            return back()->withErrors(['sync' => __('Gruppenabgleich fehlgeschlagen: :fehler', ['fehler' => $e->getMessage()])]);
        }

        return back()->with('ok', __(':n neue Rolle(n) aus den Gruppen des Verzeichnisses angelegt.', ['n' => $count]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ad_group_dn' => ['nullable', 'string', 'max:255'],
            'central_email' => ['nullable', 'email', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'visible_role_ids' => ['nullable', 'array'],
            'visible_role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        Role::create([...$data, 'in_workflow' => $request->boolean('in_workflow')]);

        return back()->with('ok', __('Rolle angelegt.'));
    }

    public function update(Request $request, Role $role)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'ad_group_dn' => ['nullable', 'string', 'max:255'],
            'central_email' => ['nullable', 'email', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'visible_role_ids' => ['nullable', 'array'],
            'visible_role_ids.*' => ['integer', 'exists:roles,id'],
        ]);

        $role->update([
            ...$data,
            'in_workflow' => $role->is_intake ? false : $request->boolean('in_workflow'),
        ]);

        return back()->with('ok', __('Rolle gespeichert.'));
    }

    public function destroy(Role $role)
    {
        abort_if($role->is_intake, 403, 'Die HR-Anlage-Rolle kann nicht gelöscht werden.');

        $role->delete();

        return back()->with('ok', __('Rolle gelöscht.'));
    }
}
