<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\CustomField;
use App\Models\Onboarding;
use App\Models\Process;
use App\Models\Role;
use App\Services\LdapService;
use App\Services\MailService;
use App\Services\WorkflowService;
use Illuminate\Http\Request;

class OnboardingController extends Controller
{
    /** Formular: neuen Vorgang anlegen (HR) / Form: create a new case (HR) */
    public function create()
    {
        return view('onboarding.create', [
            'intakeFields' => Role::intake()?->fields()->where('active', true)
                ->where('fill_stage', CustomField::STAGE_INTAKE)->get() ?? collect(),
            'processes' => \App\Models\Process::activeOrdered(),
        ]);
    }

    /**
     * AD-Suche für Vorgesetzten (liefert dessen Abteilung)
     *
     * AD search for the supervisor (returns their department)
     */
    public function lookupSupervisor(Request $request, LdapService $ldap)
    {
        $request->validate(['q' => ['required', 'string', 'min:2']]);

        try {
            $result = $ldap->findUser($request->string('q'));
        } catch (\Throwable $e) {
            return response()->json(['error' => __('Verzeichnis nicht erreichbar: :fehler', ['fehler' => $e->getMessage()])], 502);
        }

        if (! $result) {
            return response()->json(['error' => __('Kein Benutzer gefunden.')], 404);
        }

        return response()->json($result);
    }

    /**
     * Vorgang anlegen: Vorbereitung durch den Vorgesetzten beginnt
     *
     * Create a case: preparation by the supervisor begins
     */
    public function store(Request $request, LdapService $ldap)
    {
        $rules = [
            'process' => ['required', \Illuminate\Validation\Rule::exists('processes', 'key')->where('active', true)],
            'supervisor_due_date' => ['nullable', 'date'],
            'roles_due_date' => array_merge(
                ['nullable', 'date'],
                $request->filled('supervisor_due_date') ? ['after_or_equal:supervisor_due_date'] : []
            ),
            'first_name' => ['required', 'string', 'max:100'],
            'last_name'  => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'supervisor_username' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        $process = (string) $request->input('process', Process::ONBOARDING);
        $intakeFields = Role::intake()?->fields()->where('active', true)
            ->where('fill_stage', CustomField::STAGE_INTAKE)->forProcess($process)->get() ?? collect();
        $messages = [];
        $attributes = [
            'process' => __('Prozess'),
            'first_name' => __('Vorname'),
            'last_name' => __('Nachname'),
            'start_date' => __('Datum'),
            'supervisor_username' => __('Vorgesetzter'),
            'supervisor_due_date' => __('Erledigen bis: Vorgesetzter (optional)'),
            'roles_due_date' => __('Erledigen bis: Abteilungen (optional)'),
            'notes' => __('Notizen'),
        ];
        foreach ($intakeFields as $field) {
            $rules = array_merge($rules, $field->rulesFor('fields.' . $field->id));
            $attributes['fields.' . $field->id] = $field->displayLabel();
            $attributes['fields.' . $field->id . '.*'] = $field->displayLabel();
            if ($field->regex_hint) {
                $messages['fields.' . $field->id . '.regex'] = $field->label . ': ' . $field->regex_hint;
            }
        }

        $validated = $request->validate($rules, $messages, $attributes);
        $data = collect($validated)->except('fields')->all();

        // Abteilung des Vorgesetzten aus dem Verzeichnis oder den eigenen
        // Konten lesen – dort wird der neue Mitarbeiter arbeiten.
        // Read the supervisor's department from the directory or the own
        // accounts – that is where the new employee will work.
        $supervisor = null;
        try {
            $supervisor = $ldap->findUser($data['supervisor_username']);
        } catch (\Throwable) {
            // AD nicht erreichbar: Vorgang trotzdem anlegen
            // AD unreachable: create the case anyway
        }

        $onboarding = Onboarding::create([
            ...$data,
            'supervisor_name'  => $supervisor['name'] ?? null,
            'supervisor_email' => $supervisor['mail'] ?? null,
            'department'       => $supervisor['department'] ?? null,
            'created_by'       => $this->user()['username'] ?? 'unbekannt',
            'status'           => 'draft',
        ]);

        $filled = [];
        foreach ($intakeFields as $field) {
            $value = $field->prepareValue($validated['fields'][$field->id] ?? null);
            $onboarding->values()->create([
                'custom_field_id' => $field->id,
                'value'     => $value,
                'filled_by' => $this->user()['username'] ?? null,
            ]);
            $filled[$field->label] = $field->displayValue($value) ?? '–';
        }

        if ($filled !== []) {
            ActivityLog::write(ActivityLog::TYPE_FIELD, 'Daten bei der Anlage (HR) ausgefüllt', $this->user()['username'] ?? null, $onboarding, $filled);
        }

        ActivityLog::write(ActivityLog::TYPE_CASE, 'Vorgang angelegt (' . $onboarding->processLabel() . ' – Vorbereitung durch den Vorgesetzten)', $this->user()['username'] ?? null, $onboarding);

        MailService::notifySupervisor($onboarding);

        return redirect()->route('onboardings.show', $onboarding)
            ->with('ok', __('Vorgang angelegt. Der/die Vorgesetzte wurde per E-Mail informiert und kann die Daten jetzt pflegen.'));
    }

    /**
     * Import-Assistent (Schritt 1–3): Vorlage, Upload, Zeilen abarbeiten
     *
     * Import wizard (steps 1–3): template, upload, work through the rows
     */
    public function importWizard()
    {
        return view('onboarding.import', [
            'rows' => session('import_rows', []),
            'total' => (int) session('import_total', 0),
            'created' => (int) session('import_created', 0),
            'processes' => Process::activeOrdered(),
        ]);
    }

    /**
     * Schritt 2: CSV hochladen und einlesen (es wird noch NICHTS angelegt)
     *
     * Step 2: upload and read the CSV (NOTHING is created yet)
     */
    public function importParse(Request $request)
    {
        $request->validate([
            'csv' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ], [], ['csv' => __('CSV-Datei')]);

        $raw = file_get_contents($request->file('csv')->getRealPath());
        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        $delimiter = substr_count($raw, ';') >= substr_count($raw, ',') ? ';' : ',';

        $lines = array_values(array_filter(array_map(
            fn ($line) => str_getcsv($line, $delimiter),
            preg_split('/\r\n|\r|\n/', trim($raw))
        ), fn ($r) => implode('', array_map('strval', $r)) !== ''));

        if (count($lines) < 2) {
            return back()->withErrors(['csv' => __('Die Datei enthält keine Datenzeilen. Bitte die Vorlage verwenden.')]);
        }

        $header = array_map(fn ($h) => mb_strtolower(trim((string) $h)), array_shift($lines));
        $col = function (string ...$names) use ($header) {
            foreach ($names as $n) {
                $i = array_search($n, $header, true);
                if ($i !== false) {
                    return $i;
                }
            }

            return null;
        };
        // Spaltennamen der Vorlage sind deutsch (Datenformat für die Anwender);
        // die englischen Namen werden ebenso angenommen
        // Column names of the template are German (data format for the users);
        // the English names are accepted as well
        $cFirst = $col('vorname', 'first_name'); $cLast = $col('nachname', 'name', 'last_name');
        $cProcess = $col('prozess', 'prozessart', 'process'); $cDate = $col('eintrittsdatum', 'datum', 'eintritt', 'start_date');
        $cSupervisor = $col('vorgesetzter', 'vorgesetzte:r', 'vorgesetzte', 'supervisor', 'supervisor_username');
        $cDueSup = $col('frist_vorgesetzter', 'supervisor_due_date');
        $cDueRoles = $col('frist_abteilungen', 'roles_due_date'); $cNotes = $col('notizen', 'notiz', 'notes');

        if ($cFirst === null || $cLast === null) {
            return back()->withErrors(['csv' => __('Kopfzeile unvollständig – mindestens die Spalten vorname und nachname werden benötigt. Bitte die aktuelle Vorlage verwenden (Schritt 1).')]);
        }

        $normDate = function (?string $val): array {
            if ($val === null || trim($val) === '') {
                return [null, null];
            }
            $val = trim($val);
            foreach (['d.m.Y', 'Y-m-d', 'd.m.y'] as $format) {
                try {
                    return [\Illuminate\Support\Carbon::createFromFormat($format, $val)->toDateString(), null];
                } catch (\Throwable) {
                    // nächstes Format
                    // next format
                }
            }

            return [null, $val]; // nicht lesbar -> Original als Hinweis zurück / unreadable -> original as hint
        };

        $rows = [];
        foreach ($lines as $i => $row) {
            $get = fn ($c) => $c === null ? '' : trim((string) ($row[$c] ?? ''));
            $hints = [];

            // Prozess: Schlüssel oder Name (DE/EN); unbekannt -> Hinweis, Auswahl in Schritt 3
            // Process: key or name (DE/EN); unknown -> hint, selection in step 3
            $procInput = $get($cProcess);
            $process = $procInput === '' ? Process::findByKey(Process::ONBOARDING)
                : Process::where('key', Process::currentKey($procInput))->orWhere('name', $procInput)->orWhere('name_en', $procInput)->first();
            if ($procInput !== '' && (! $process || ! $process->active)) {
                $hints[] = __('Prozess ":p" unbekannt oder inaktiv – bitte unten auswählen.', ['p' => $procInput]);
                $process = null;
            }

            [$startDate, $badStart] = $normDate($get($cDate));
            [$dueSup, $badSup] = $normDate($get($cDueSup));
            [$dueRoles, $badRoles] = $normDate($get($cDueRoles));
            foreach ([[$badStart, 'Eintrittsdatum'], [$badSup, 'frist_vorgesetzter'], [$badRoles, 'frist_abteilungen']] as [$bad, $label]) {
                if ($bad !== null) {
                    $hints[] = __('Datum ":d" (:f) nicht lesbar – bitte unten korrigieren.', ['d' => $bad, 'f' => $label]);
                }
            }

            if ($get($cFirst) === '' || $get($cLast) === '') {
                $hints[] = __('Vor- oder Nachname fehlt.');
            }
            if ($get($cSupervisor) === '') {
                $hints[] = __('Vorgesetzter fehlt – bitte unten über die AD-Suche wählen.');
            }

            $rows[] = [
                'line' => $i + 2,
                'first_name' => $get($cFirst),
                'last_name' => $get($cLast),
                'process' => $process?->key,
                'start_date' => $startDate,
                'supervisor_username' => $get($cSupervisor),
                'supervisor_due_date' => $dueSup,
                'roles_due_date' => $dueRoles,
                'notes' => $get($cNotes),
                'hints' => $hints,
            ];
        }

        session([
            'import_rows' => $rows,
            'import_total' => count($rows),
            'import_created' => 0,
        ]);

        return redirect()->route('onboardings.import')
            ->with('ok', __(':n Zeilen eingelesen. Bitte prüfen Sie jede Zeile, wählen Sie den Vorgesetzten aus dem AD und legen Sie die Vorgänge einzeln an.', ['n' => count($rows)]));
    }

    /** Schritt 3: eine geprüfte Zeile als Vorgang anlegen / Step 3: create one checked row as a case */
    public function importCreateRow(Request $request, LdapService $ldap)
    {
        $data = $request->validate([
            'row_key' => ['required', 'integer'],
            'process' => ['required', \Illuminate\Validation\Rule::exists('processes', 'key')->where('active', true)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'start_date' => ['nullable', 'date'],
            'supervisor_username' => ['required', 'string', 'max:100'],
            'supervisor_due_date' => ['nullable', 'date'],
            'roles_due_date' => array_merge(['nullable', 'date'], $request->filled('supervisor_due_date') ? ['after_or_equal:supervisor_due_date'] : []),
            'notes' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'process' => __('Prozess'),
            'first_name' => __('Vorname'),
            'last_name' => __('Nachname'),
            'supervisor_username' => __('Vorgesetzter'),
        ]);

        $supervisor = null;
        try {
            $supervisor = $ldap->findUser($data['supervisor_username']);
        } catch (\Throwable) {
            // AD nicht erreichbar: Vorgang trotzdem anlegen
            // AD unreachable: create the case anyway
        }

        $onboarding = Onboarding::create([
            ...collect($data)->except('row_key')->all(),
            'supervisor_name' => $supervisor['name'] ?? null,
            'supervisor_email' => $supervisor['mail'] ?? null,
            'department' => $supervisor['department'] ?? null,
            'created_by' => $this->user()['username'] ?? null,
            'status' => 'draft',
        ]);

        ActivityLog::write(ActivityLog::TYPE_CASE, 'Vorgang angelegt per CSV-Import-Assistent (' . $onboarding->processLabel() . ')', $this->user()['username'] ?? null, $onboarding);
        MailService::notifySupervisor($onboarding);

        $this->forgetImportRow((int) $data['row_key'], created: true);

        return redirect()->route('onboardings.import')
            ->with('ok', __('Vorgang für :name angelegt – Vorgesetzter wurde benachrichtigt.', ['name' => $onboarding->fullName()]));
    }

    /** Schritt 3: eine Zeile verwerfen / Step 3: discard a row */
    public function importDiscardRow(Request $request)
    {
        $request->validate(['row_key' => ['required', 'integer']]);
        $this->forgetImportRow((int) $request->input('row_key'), created: false);

        return redirect()->route('onboardings.import')->with('ok', __('Zeile verworfen.'));
    }

    /** Assistent zurücksetzen (neue Datei beginnen) / Reset the wizard (start a new file) */
    public function importReset()
    {
        session()->forget(['import_rows', 'import_total', 'import_created']);

        return redirect()->route('onboardings.import');
    }

    private function forgetImportRow(int $key, bool $created): void
    {
        $rows = session('import_rows', []);
        unset($rows[$key]);
        session(['import_rows' => $rows]);
        if ($created) {
            session(['import_created' => (int) session('import_created', 0) + 1]);
        }
    }

    /** Beispiel-CSV für den Massenimport / Sample CSV for the bulk import */
    public function importTemplate()
    {
        // Beispielzeilen mit den tatsächlich verfügbaren Prozessarten
        // Sample rows with the process types actually available
        $keys = \App\Models\Process::activeOrdered()->pluck('key');
        $csv = "vorname;nachname;prozess;eintrittsdatum;vorgesetzter;frist_vorgesetzter;frist_abteilungen;notizen\n"
            . 'Max;Mustermann;' . ($keys->first() ?? Process::ONBOARDING) . ";01.09.2026;m.chef;15.08.2026;25.08.2026;Neuer Kollege Vertrieb\n"
            . 'Erika;Beispiel;' . ($keys->skip(1)->first() ?? Process::JOB_CHANGE) . ";01.10.2026;;;;Vorgesetzter wird im Assistenten gewählt\n";

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="crewgate-import-template.csv"',
        ]);
    }

    /**
     * Berechtigung: darf die Vorbereitung pflegen (Vorgesetzter, HR, Ersteller)
     *
     * Permission: may maintain the preparation (supervisor, HR, creator)
     */
    private function mayPrepare(Onboarding $onboarding): bool
    {
        $user = $this->user();

        return ($user['is_hr'] ?? false)
            || $onboarding->created_by === ($user['username'] ?? null)
            || (! empty($user['department']) && $user['department'] === $onboarding->department);
    }

    /**
     * Vorbereitung: Daten aller Workflow-Rollen zwischenspeichern
     *
     * Preparation: temporarily save the data of all workflow roles
     */
    public function saveDraft(Request $request, Onboarding $onboarding)
    {
        abort_unless($onboarding->status === 'draft', 404, 'Der Vorgang ist bereits abgesendet.');
        abort_unless($this->mayPrepare($onboarding), 403, 'Nur Vorgesetzter, HR oder Ersteller dürfen die Vorbereitung pflegen.');

        $this->saveDraftValues($request, $onboarding, forceOptional: true);

        return redirect()->route('onboardings.show', $onboarding)
            ->with('ok', __('Zwischengespeichert. Sie können die Daten weiter pflegen und zum Schluss absenden.'));
    }

    /**
     * Vorbereitung abschließen: Pflichtfelder prüfen, alle Abteilungen benachrichtigen
     *
     * Finish preparation: check mandatory fields, notify all departments
     */
    public function submit(Request $request, Onboarding $onboarding, WorkflowService $workflow)
    {
        abort_unless($onboarding->status === 'draft', 404, 'Der Vorgang ist bereits abgesendet.');
        abort_unless($this->mayPrepare($onboarding), 403, 'Nur Vorgesetzter, HR oder Ersteller dürfen absenden.');

        // Beim Absenden gelten die Pflichtfelder – bei Lücken erscheint
        // eine Fehlermeldung und der Vorgang wird NICHT gestartet.
        // On submit the mandatory fields apply – if anything is missing an
        // error message appears and the case is NOT started.
        $this->saveDraftValues($request, $onboarding, forceOptional: false);

        $workflow->start($onboarding);

        return redirect()->route('onboardings.show', $onboarding)
            ->with('ok', __('Vorgang abgesendet – alle Abteilungen wurden per E-Mail benachrichtigt und quittieren jetzt den Erhalt.'));
    }

    /**
     * Gemeinsames Speichern der Vorbereitungs-Daten (alle Workflow-Rollen)
     *
     * Shared saving of the preparation data (all workflow roles)
     */
    private function saveDraftValues(Request $request, Onboarding $onboarding, bool $forceOptional): void
    {
        $intakeId = Role::intake()?->id;
        $fields = CustomField::with('role')
            ->where('active', true)
            ->forProcess($onboarding->process ?? Process::ONBOARDING)
            ->where(function ($q) use ($intakeId, $onboarding) {
                $q->whereIn('role_id', $onboarding->workflowRoles()->pluck('id'));
                if ($intakeId) {
                    $q->orWhere(fn ($w) => $w->where('role_id', $intakeId)->where('fill_stage', CustomField::STAGE_PREPARATION));
                }
            })
            ->get();

        $rules = [];
        $messages = [];
        $attributes = [
            'process' => __('Prozess'),
            'first_name' => __('Vorname'),
            'last_name' => __('Nachname'),
            'start_date' => __('Datum'),
            'supervisor_username' => __('Vorgesetzter'),
            'supervisor_due_date' => __('Erledigen bis: Vorgesetzter (optional)'),
            'roles_due_date' => __('Erledigen bis: Abteilungen (optional)'),
            'notes' => __('Notizen'),
        ];
        foreach ($fields as $field) {
            $rules = array_merge($rules, $field->rulesFor('fields.' . $field->id, $forceOptional));
            $attributes['fields.' . $field->id] = ($field->role?->name ? $field->role->name . ' – ' : '') . $field->displayLabel();
            $attributes['fields.' . $field->id . '.*'] = ($field->role?->name ? $field->role->name . ' – ' : '') . $field->displayLabel();
            if ($field->regex_hint) {
                $messages['fields.' . $field->id . '.regex'] = $field->label . ': ' . $field->regex_hint;
            }
        }

        $validated = $request->validate($rules, $messages, $attributes);

        $filled = [];
        foreach ($fields as $field) {
            $raw = $validated['fields'][$field->id] ?? null;
            if ($raw === null || $raw === '' || $raw === []) {
                continue; // leere Angaben nicht überschreiben/anlegen / do not overwrite/create empty entries
            }

            $value = $field->prepareValue($raw);
            $onboarding->values()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $value, 'filled_by' => $this->user()['username'] ?? null],
            );
            $filled[$field->role?->name . ' – ' . $field->label] = $field->displayValue($value) ?? '–';
        }

        if ($filled !== []) {
            ActivityLog::write(ActivityLog::TYPE_FIELD, 'Vorbereitungs-Daten gepflegt', $this->user()['username'] ?? null, $onboarding, $filled);
        }
    }

    /** Detailansicht / Detail view */
    public function show(Onboarding $onboarding)
    {
        $user = $this->user();
        $onboarding->load(['values.field.role', 'steps.role']);

        // Der Vorgesetzte (gleiche AD-Abteilung) pflegt die Vorbereitung.
        // The supervisor (same AD department) maintains the preparation.
        $isSupervisor = ! empty($user['department'])
            && $user['department'] === $onboarding->department;

        // Sichtbarkeit der erfassten Daten: HR, Admin, Auditor, Vorgesetzte
        // und der anlegende Benutzer sehen alles. Abteilungs-Benutzer sehen
        // die Felder ihrer Rollen plus die freigegebenen Rollen.
        // Visibility of the captured data: HR, admin, auditor, supervisors
        // and the creating user see everything. Department users see
        // the fields of their roles plus the shared roles.
        $seesAll = ($user['is_hr'] ?? false)
            || ($user['is_admin'] ?? false)
            || ($user['is_auditor'] ?? false)
            || $isSupervisor
            || $onboarding->created_by === ($user['username'] ?? null);

        $visibleValues = $onboarding->values;
        if (! $seesAll) {
            $own = $user['role_ids'] ?? [];
            $shared = Role::whereIn('id', $own)->get()
                ->flatMap(fn ($r) => $r->visible_role_ids ?? [])
                ->map(fn ($id) => (int) $id)
                ->all();
            $allowed = array_unique([...$own, ...$shared]);

            $visibleValues = $onboarding->values
                ->filter(fn ($v) => ($v->field?->visible_to_all ?? false)
                    || in_array((int) $v->field?->role_id, $allowed, true))
                ->values();
        }

        // Vorbelegung: bereits gespeicherte Werte je Feld
        // Pre-fill: values already saved per field
        $prefill = $onboarding->values->pluck('value', 'custom_field_id')->all();

        // Vorbereitung: alle Workflow-Rollen mit ihren Feldern
        // Preparation: all workflow roles with their fields
        $draftRoles = collect();
        if ($onboarding->status === 'draft' && $this->mayPrepare($onboarding)) {
            $process = $onboarding->process ?? Process::ONBOARDING;
            $draftRoles = $onboarding->workflowRoles()->load(['fields' => fn ($q) => $q->where('active', true)->forProcess($process)]);

            // Felder, die der Vorgesetzte für HR ausfüllen soll
            // Fields the supervisor should fill in for HR
            if (($intake = Role::intake()) !== null) {
                $intakeDraftFields = $intake->fields()->where('active', true)
                    ->where('fill_stage', CustomField::STAGE_PREPARATION)->forProcess($process)->get();
                if ($intakeDraftFields->isNotEmpty()) {
                    $intake->setRelation('fields', $intakeDraftFields);
                    $draftRoles = collect([$intake])->concat($draftRoles);
                }
            }
        }

        // Ausstehende Rollen, für die der Benutzer quittieren darf
        // Pending roles the user may acknowledge for
        $actionRoles = collect();
        if ($onboarding->status === 'open') {
            $actionRoles = $onboarding->pendingRoles()
                ->filter(fn ($r) => in_array($r->id, $user['role_ids'] ?? [], true) || ($user['is_admin'] ?? false))
                ->values();
            $actionRoles->each->load(['fields' => fn ($q) => $q->where('active', true)->forProcess($onboarding->process ?? Process::ONBOARDING)]);
        }

        return view('onboarding.show', [
            'onboarding'    => $onboarding,
            'user'          => $user,
            'visibleValues' => $visibleValues,
            'prefill'       => $prefill,
            'draftRoles'    => $draftRoles,
            'actionRoles'   => $actionRoles,
        ]);
    }

    /**
     * Daten der eigenen Rolle speichern und den Erhalt quittieren
     *
     * Save the data of one's own role and acknowledge receipt
     */
    public function completeStep(Request $request, Onboarding $onboarding, Role $role, WorkflowService $workflow)
    {
        $user = $this->user();

        abort_unless($onboarding->status === 'open', 404, 'Der Vorgang ist nicht in Bearbeitung.');
        abort_unless($onboarding->pendingRoles()->contains('id', $role->id), 404, 'Diese Rolle hat bereits quittiert.');

        // Quittieren dürfen nur die AD-Mitglieder der Rolle selbst
        // (Fachabteilung) – Admins als Ausnahme.
        // Only the AD members of the role itself may acknowledge
        // (specialist department) – admins as the exception.
        $allowed = in_array($role->id, $user['role_ids'] ?? [], true)
            || ($user['is_admin'] ?? false);
        abort_unless($allowed, 403, 'Nur die Abteilung selbst darf ihren Erhalt quittieren.');

        $fields = $role->fields()->where('active', true)
            ->forProcess($onboarding->process ?? Process::ONBOARDING)->get();
        $rules = [];
        $messages = [];
        $attributes = [
            'process' => __('Prozess'),
            'first_name' => __('Vorname'),
            'last_name' => __('Nachname'),
            'start_date' => __('Datum'),
            'supervisor_username' => __('Vorgesetzter'),
            'supervisor_due_date' => __('Erledigen bis: Vorgesetzter (optional)'),
            'roles_due_date' => __('Erledigen bis: Abteilungen (optional)'),
            'notes' => __('Notizen'),
        ];

        foreach ($fields as $field) {
            $rules = array_merge($rules, $field->rulesFor('fields.' . $field->id));
            $attributes['fields.' . $field->id] = $field->displayLabel();
            $attributes['fields.' . $field->id . '.*'] = $field->displayLabel();
            if ($field->regex_hint) {
                $messages['fields.' . $field->id . '.regex'] = $field->label . ': ' . $field->regex_hint;
            }
        }

        $validated = $request->validate($rules, $messages, $attributes);

        $filled = [];
        foreach ($fields as $field) {
            $value = $field->prepareValue($validated['fields'][$field->id] ?? null);

            $onboarding->values()->updateOrCreate(
                ['custom_field_id' => $field->id],
                ['value' => $value, 'filled_by' => $user['username'] ?? null],
            );

            $filled[$field->label] = $field->displayValue($value) ?? '–';
        }

        if ($filled !== []) {
            ActivityLog::write(ActivityLog::TYPE_FIELD, 'Daten für "' . $role->name . '" gespeichert', $user['username'] ?? null, $onboarding, $filled);
        }

        $workflow->acknowledge($onboarding, $role, $user['username'] ?? 'unbekannt');

        return redirect()->route('onboardings.show', $onboarding)
            ->with('ok', __('Erhalt quittiert – vielen Dank.'));
    }

    /**
     * Erhalt quittieren (aus der Übersicht, mit den vorliegenden Daten)
     *
     * Acknowledge receipt (from the overview, with the data provided)
     */
    public function acknowledge(Onboarding $onboarding, Role $role, WorkflowService $workflow)
    {
        $user = $this->user();

        abort_unless($onboarding->status === 'open', 404, 'Der Vorgang ist nicht in Bearbeitung.');
        abort_unless($onboarding->pendingRoles()->contains('id', $role->id), 404, 'Diese Rolle hat bereits quittiert.');

        $allowed = in_array($role->id, $user['role_ids'] ?? [], true)
            || ($user['is_admin'] ?? false);
        abort_unless($allowed, 403, 'Nur die Abteilung selbst darf ihren Erhalt quittieren.');

        $stored = $onboarding->values->pluck('value', 'custom_field_id');
        $missing = $role->fields()->where('active', true)->where('required', true)
            ->forProcess($onboarding->process ?? Process::ONBOARDING)->get()
            ->filter(fn ($f) => blank($stored[$f->id] ?? null))
            ->map->displayLabel();

        if ($missing->isNotEmpty()) {
            return redirect()->route('onboardings.show', $onboarding)
                ->withErrors(['ack' => __('Quittieren nicht möglich – Pflichtfelder fehlen:') . ' ' . $missing->implode(', ')]);
        }

        $workflow->acknowledge($onboarding, $role, $user['username'] ?? 'unbekannt');

        return back()->with('ok', __('Erhalt quittiert – vielen Dank.'));
    }

    /** HR quittiert abschließend: Vorgang schließen / HR gives the final acknowledgement: close the case */
    public function close(Onboarding $onboarding)
    {
        abort_unless($this->user()['is_hr'] ?? false, 403, 'Nur HR darf Vorgänge abschließen.');
        abort_unless($onboarding->status === 'review', 404, 'Der Vorgang wartet nicht auf den HR-Abschluss.');

        $onboarding->update(['status' => 'done', 'completed_at' => now()]);

        ActivityLog::write(ActivityLog::TYPE_CASE, 'Vorgang durch HR abschließend quittiert', $this->user()['username'] ?? null, $onboarding);

        return back()->with('ok', __('Vorgang abgeschlossen.'));
    }

    /**
     * HR bricht einen Vorgang ab (bleibt sichtbar, kein weiterer Workflow)
     *
     * HR cancels a case (stays visible, no further workflow)
     */
    public function cancel(Onboarding $onboarding)
    {
        abort_unless($this->user()['is_hr'] ?? false, 403, 'Nur HR darf Vorgänge abbrechen.');
        abort_if($onboarding->status === 'done', 404, 'Abgeschlossene Vorgänge können nicht abgebrochen werden.');

        $onboarding->update(['status' => 'cancelled', 'current_role_id' => null]);

        ActivityLog::write(ActivityLog::TYPE_CASE, 'Vorgang durch HR abgebrochen', $this->user()['username'] ?? null, $onboarding);
        MailService::notifyCancelled($onboarding);

        return back()->with('ok', __('Vorgang abgebrochen.'));
    }

    /**
     * HR löscht einen Vorgang endgültig (revisionssicher protokolliert)
     *
     * HR deletes a case permanently (logged in an audit-proof way)
     */
    public function destroy(Onboarding $onboarding)
    {
        abort_unless($this->user()['is_hr'] ?? false, 403, 'Nur HR darf Vorgänge löschen.');

        ActivityLog::write(ActivityLog::TYPE_DELETION, 'Vorgang manuell durch HR gelöscht', $this->user()['username'] ?? null, $onboarding, [
            'Mitarbeiter'         => $onboarding->fullName(),
            'Prozess'             => $onboarding->processLabel(),
            'Abteilung'           => $onboarding->department ?? '–',
            'Status'              => $onboarding->statusLabel(),
            'Angelegt am'         => $onboarding->created_at->format('d.m.Y H:i'),
            'Gelöschte Feldwerte' => $onboarding->values()->count(),
            'Quittierungen'       => $onboarding->steps()->count(),
        ]);

        $onboarding->delete();

        return redirect()->route('dashboard')->with('ok', __('Vorgang gelöscht.'));
    }
}
