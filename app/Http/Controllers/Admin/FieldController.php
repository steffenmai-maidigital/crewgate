<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\Role;
use Illuminate\Http\Request;

class FieldController extends Controller
{
    public function index(Request $request)
    {
        $roles = Role::whereIn('id', $this->manageableRoleIds())->orderBy('sort_order')->get();
        $role = $roles->firstWhere('id', (int) $request->query('role')) ?? $roles->first();

        return view('admin.fields', [
            'roles'  => $roles,
            'role'   => $role,
            'fields' => $role?->fields ?? collect(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        abort_unless(in_array((int) $data['role_id'], $this->manageableRoleIds(), true), 403, 'Felder dieser Rolle dürfen Sie nicht pflegen.');

        CustomField::create($data);

        return back()->with('ok', __('Feld angelegt.'));
    }

    public function update(Request $request, CustomField $field)
    {
        abort_unless(in_array($field->role_id, $this->manageableRoleIds(), true), 403, 'Felder dieser Rolle dürfen Sie nicht pflegen.');

        $field->update($this->validated($request));

        return back()->with('ok', __('Feld gespeichert.'));
    }

    public function destroy(CustomField $field)
    {
        abort_unless(in_array($field->role_id, $this->manageableRoleIds(), true), 403, 'Felder dieser Rolle dürfen Sie nicht pflegen.');

        $field->delete();

        return back()->with('ok', __('Feld gelöscht.'));
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
            'label' => ['required', 'string', 'max:150'],
            'label_en' => ['nullable', 'string', 'max:150'],
            'type' => ['required', 'in:select,multiselect,checkbox,date,product,text'],
            'options_raw' => ['nullable', 'string', 'required_if:type,select', 'required_if:type,multiselect'],
            'regex' => ['nullable', 'string', 'max:500'],
            'regex_hint' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'fill_stage' => ['nullable', 'in:' . CustomField::STAGE_INTAKE . ',' . CustomField::STAGE_PREPARATION],
            'processes' => ['required', 'array', 'min:1'],
            'processes.*' => ['exists:processes,key'],
        ]);

        // Regex validieren, bevor sie gespeichert wird
        // Validate the regex before it is saved
        if (! empty($data['regex']) && @preg_match('~' . str_replace('~', '\~', $data['regex']) . '~u', '') === false) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'regex' => 'Der reguläre Ausdruck ist ungültig.',
            ]);
        }

        $options = null;
        if (in_array($data['type'], ['select', 'multiselect'], true)) {
            $options = collect(preg_split('/\r?\n/', $data['options_raw'] ?? ''))
                ->map(fn ($o) => trim($o))
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        return [
            'role_id' => $data['role_id'],
            'label' => $data['label'],
            'label_en' => $data['label_en'] ?? null,
            'type' => $data['type'],
            'options' => $options,
            'regex' => $data['type'] === 'text' ? ($data['regex'] ?: null) : null,
            'regex_hint' => $data['regex_hint'] ?? null,
            'sort_order' => $data['sort_order'],
            'required' => $request->boolean('required'),
            'active' => $request->boolean('active', true),
            'fill_stage' => $data['fill_stage'] ?? CustomField::STAGE_INTAKE,
            'visible_to_all' => $request->boolean('visible_to_all'),
            'processes' => array_values($data['processes']),
        ];
    }
}
