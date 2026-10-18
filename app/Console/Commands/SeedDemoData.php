<?php

namespace App\Console\Commands;

use App\Models\CustomField;
use App\Models\Product;
use App\Models\Onboarding;
use App\Models\Process;
use App\Models\Role;
use App\Services\LdapService;
use App\Services\WorkflowService;
use Illuminate\Console\Command;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--fresh : Beispielvorgänge löschen und neu anlegen}';

    protected $description = 'Legt Demo-Rollen, Demo-Felder und einen Beispielvorgang an (nur im Demo-Modus).';

    public function handle(LdapService $ldap, WorkflowService $workflow): int
    {
        if (! config('demo.enabled')) {
            $this->warn('DEMO_MODE ist nicht aktiv – es wird nichts angelegt.');
            return self::FAILURE;
        }

        // Rollen aus dem Demo-Verzeichnis (idempotent), danach die Demo-Konten
        // als eigene Konten mit Passwort „demo“
        // Roles from the demo directory (idempotent), then the demo accounts
        // as own accounts with password “demo”
        $ldap->syncGroupsToRoles();
        \App\Services\DemoDirectory::seedUsers();

        // Zentrale Adressen und Reihenfolge pflegen
        // Maintain central addresses and order
        foreach ([
            'IT'       => ['sort' => 1, 'mail' => 'it-onboarding@demo.local'],
            'Einkauf'  => ['sort' => 2, 'mail' => 'einkauf-onboarding@demo.local'],
            'Facility' => ['sort' => 3, 'mail' => 'facility-onboarding@demo.local'],
        ] as $name => $cfg) {
            Role::where('name', $name)->update([
                'sort_order'    => $cfg['sort'],
                'central_email' => $cfg['mail'],
                'in_workflow'   => true,
            ]);
        }

        // Beispiel-Felder für die HR-Anlage (erscheinen beim Anlegen des Vorgangs)
        // Sample fields for the HR intake (appear when the case is created)
        if ($intake = Role::intake()) {
            foreach ([
                ['label' => 'Stellenbezeichnung', 'label_en' => 'Job title', 'visible_to_all' => true, 'type' => 'text', 'regex' => null, 'hint' => null, 'required' => true, 'sort' => 1],
                ['label' => 'Beschäftigungsart', 'label_en' => 'Employment type', 'type' => 'select', 'options' => ['Vollzeit', 'Teilzeit', 'Ausbildung', 'Werkstudent'], 'required' => true, 'sort' => 2],
                ['label' => 'Personalnummer', 'label_en' => 'Personnel number', 'type' => 'text', 'regex' => '^P-\d{5}$', 'hint' => 'Format: P-12345', 'required' => false, 'sort' => 3],
                ['label' => 'Bemerkungen an HR', 'label_en' => 'Notes for HR', 'type' => 'text', 'required' => false, 'fill_stage' => CustomField::STAGE_PREPARATION, 'sort' => 90],
                ['label' => 'Neuer Name', 'label_en' => 'New name', 'type' => 'text', 'required' => true, 'visible_to_all' => true, 'sort' => 20, 'processes' => [Process::MASTER_DATA_CHANGE]],
                ['label' => 'Grund der Änderung', 'label_en' => 'Reason for change', 'type' => 'select', 'options' => ['Heirat', 'Scheidung', 'Namenskorrektur', 'Sonstiges'], 'required' => true, 'sort' => 21, 'processes' => [Process::MASTER_DATA_CHANGE]],
            ] as $f) {
                CustomField::updateOrCreate(
                    ['role_id' => $intake->id, 'label' => $f['label']],
                    [
                        'type' => $f['type'],
                        'options' => $f['options'] ?? null,
                        'label_en' => $f['label_en'] ?? null,
                        'fill_stage' => $f['fill_stage'] ?? CustomField::STAGE_INTAKE,
                        'visible_to_all' => $f['visible_to_all'] ?? false,
                        'processes' => $f['processes'] ?? [Process::ONBOARDING, Process::JOB_CHANGE, Process::EXIT],
                        'regex' => $f['regex'] ?? null,
                        'regex_hint' => $f['hint'] ?? null,
                        'required' => $f['required'],
                        'sort_order' => $f['sort'],
                        'active' => true,
                    ]
                );
            }
        }

        // Demo-Felder pro Abteilung
        // Demo fields per department
        $fields = [
            'IT' => [
                ['label' => 'Gerätetyp', 'label_en' => 'Device type', 'type' => 'select', 'options' => ['Notebook', 'Desktop', 'Thin Client'], 'required' => true, 'sort' => 1],
                ['label' => 'Benötigte Software', 'label_en' => 'Required software', 'type' => 'select', 'options' => ['Office-Standard', 'Entwickler-Paket', 'CAD-Paket'], 'required' => true, 'sort' => 2],
                ['label' => 'Gewünschter Benutzername', 'label_en' => 'Desired username', 'type' => 'text', 'regex' => '^[a-z]+\.[a-z]+$', 'hint' => 'Format: vorname.nachname (Kleinbuchstaben)', 'required' => true, 'sort' => 3, 'processes' => [Process::ONBOARDING]],
                ['label' => 'Berechtigungen anpassen', 'label_en' => 'Adjust permissions', 'type' => 'select', 'options' => ['Übernahme Bestand', 'Neu vergeben', 'Erweitern'], 'required' => true, 'sort' => 4, 'processes' => [Process::JOB_CHANGE]],
                ['label' => 'Konten deaktivieren', 'label_en' => 'Deactivate accounts', 'type' => 'checkbox', 'required' => true, 'sort' => 5, 'processes' => [Process::EXIT]],
                ['label' => 'Konto & E-Mail anpassen', 'label_en' => 'Update account & email', 'type' => 'checkbox', 'required' => true, 'sort' => 6, 'processes' => [Process::MASTER_DATA_CHANGE]],
            ],
            'Einkauf' => [
                ['label' => 'Möbelauswahl', 'label_en' => 'Furniture selection', 'type' => 'product', 'required' => true, 'sort' => 1, 'processes' => [Process::ONBOARDING, Process::JOB_CHANGE]],
                ['label' => 'Kostenstelle', 'label_en' => 'Cost centre', 'type' => 'text', 'regex' => '^\d{4}-\d{3}$', 'hint' => 'Format: 0000-000', 'required' => true, 'sort' => 2],
                ['label' => 'Lieferung bis', 'label_en' => 'Delivery by', 'type' => 'date', 'required' => false, 'sort' => 3, 'processes' => [Process::ONBOARDING, Process::JOB_CHANGE]],
            ],
            'Facility' => [
                ['label' => 'Zutrittsberechtigung', 'label_en' => 'Access authorisation', 'processes' => [Process::ONBOARDING, Process::JOB_CHANGE, 'azubi-uebernahme'], 'type' => 'select', 'options' => ['Standard', 'Erweitert (Serverraum)', 'Extern/Besucher'], 'required' => true, 'sort' => 1],
                ['label' => 'Schlüssel übergeben', 'label_en' => 'Keys handed over', 'type' => 'checkbox', 'required' => false, 'sort' => 3],
                ['label' => 'Parkplatz-Kennzeichen', 'label_en' => 'Parking licence plate', 'type' => 'text', 'regex' => '^[A-ZÄÖÜ]{1,3}-[A-Z]{1,2} ?\d{1,4}$', 'hint' => 'Kfz-Kennzeichen, z. B. S-AB 1234 (optional)', 'required' => false, 'sort' => 2],
                ['label' => 'Schlüssel zurückerhalten', 'label_en' => 'Keys returned', 'type' => 'checkbox', 'required' => true, 'sort' => 4, 'processes' => [Process::EXIT]],
                ['label' => 'Ausweis neu ausstellen', 'label_en' => 'Reissue badge', 'type' => 'checkbox', 'required' => true, 'sort' => 5, 'processes' => [Process::MASTER_DATA_CHANGE]],
            ],
        ];

        foreach ($fields as $roleName => $roleFields) {
            $role = Role::where('name', $roleName)->first();
            if (! $role) {
                continue;
            }

            foreach ($roleFields as $f) {
                CustomField::firstOrCreate(
                    ['role_id' => $role->id, 'label' => $f['label']],
                    [
                        'type' => $f['type'],
                        'options' => $f['options'] ?? null,
                        'label_en' => $f['label_en'] ?? null,
                        'fill_stage' => $f['fill_stage'] ?? CustomField::STAGE_INTAKE,
                        'visible_to_all' => $f['visible_to_all'] ?? false,
                        'processes' => $f['processes'] ?? [Process::ONBOARDING, Process::JOB_CHANGE, Process::EXIT],
                        'regex' => $f['regex'] ?? null,
                        'regex_hint' => $f['hint'] ?? null,
                        'required' => $f['required'],
                        'sort_order' => $f['sort'],
                        'active' => true,
                    ]
                );
            }
        }

        // Demo-Kategorien und -Produkte für die Produktauswahl des Einkaufs
        // Demo categories and products for the purchasing product selection
        $furniture = \App\Models\ProductCategory::firstOrCreate(['name' => 'Möbel'], ['name_en' => 'Furniture', 'color' => '#8a5a2b', 'sort_order' => 1]);
        $technology = \App\Models\ProductCategory::firstOrCreate(['name' => 'Technik'], ['name_en' => 'Technology', 'color' => '#14557b', 'sort_order' => 2]);

        $furnitureField = CustomField::where('label', 'Möbelauswahl')->first();

        foreach ([
            ['name' => 'Höhenverstellbarer Schreibtisch', 'name_en' => 'Height-adjustable desk', 'article_no' => 'A-10234', 'manufacturer_no' => 'HM-88812', 'price' => 499.00, 'category_id' => $furniture->id],
            ['name' => 'Bürostuhl ergonomisch', 'name_en' => 'Ergonomic office chair', 'article_no' => 'A-10501', 'manufacturer_no' => 'HM-77120', 'price' => 289.90, 'category_id' => $furniture->id],
            ['name' => 'Rollcontainer', 'name_en' => 'Mobile pedestal', 'article_no' => 'A-10310', 'manufacturer_no' => 'HM-55901', 'price' => 149.00, 'category_id' => $furniture->id],
            ['name' => 'Monitor 27" WQHD', 'name_en' => '27" WQHD monitor', 'article_no' => 'A-20077', 'manufacturer_no' => 'DL-U2723QE', 'price' => 329.00, 'category_id' => $technology->id],
        ] as $p) {
            Product::firstOrCreate(['name' => $p['name']], [...$p, 'active' => true, 'custom_field_id' => $furnitureField?->id]);
        }

        // Sichtbarkeit im Demo: jede Workflow-Abteilung sieht die Daten der
        // HR-Anlage (z. B. Stellenbezeichnung); IT sieht zusätzlich Einkauf.
        // Visibility in the demo: every workflow department sees the data of the
        // HR intake (e.g. job title); IT additionally sees purchasing.
        if ($intake = Role::intake()) {
            foreach (['IT', 'Einkauf', 'Facility'] as $name) {
                $role = Role::where('name', $name)->first();
                if ($role && blank($role->visible_role_ids)) {
                    $visible = [$intake->id];
                    if ($name === 'IT' && ($purchasing = Role::where('name', 'Einkauf')->first())) {
                        $visible[] = $purchasing->id;
                    }
                    $role->update(['visible_role_ids' => $visible]);
                }
            }
        }

        // Demo-Prozess mit verkürztem Workflow: Azubi-Übernahme (ohne Einkauf)
        // Demo process with a shortened workflow: apprentice takeover (without purchasing)
        if (! \App\Models\Process::where('key', 'azubi-uebernahme')->exists()) {
            $apprenticeRoles = Role::whereIn('name', ['IT', 'Facility'])->pluck('id')->map(fn ($i) => (int) $i)->values()->all();
            \App\Models\Process::create([
                'key' => 'azubi-uebernahme',
                'name' => 'Azubi-Übernahme',
                'name_en' => 'Apprentice takeover',
                'sort_order' => 5,
                'role_ids' => $apprenticeRoles,
                'active' => true,
            ]);
        }

        // Beispiel-Lizenz (Enterprise, Kundennr. DEMO0001, Support bis 31.12.2027, unlimitiert)
        // Sample licence (Enterprise, customer no. DEMO0001, support until 31.12.2027, unlimited)
        if (blank(\App\Models\Setting::get('license_key'))) {
            \App\Models\Setting::set('license_company', 'Demo GmbH');
            \App\Models\Setting::set('license_key', '2DEMO000127123199999');
        }

        // Portal-Link für die Demo-Mails
        // Portal link for the demo mails
        if (blank(\App\Models\Setting::get('portal_url'))) {
            \App\Models\Setting::set('portal_url', (string) config('app.url'));
        }

        // Beispielvorgänge in allen Status, falls noch keine existieren:
        // draft (Vorbereitung), open (Abteilungen quittieren), review
        // (wartet auf HR-Abschluss) und done (abgeschlossen).
        // Sample cases in every status, if none exist yet:
        // draft (preparation), open (departments acknowledge), review
        // (waiting for HR completion) and done (completed).
        if ($this->option('fresh')) {
            Onboarding::query()->delete();
            $this->info('Bestehende Beispielvorgänge gelöscht – lege neu an.');
        }

        if (Onboarding::count() === 0) {
            $workflowRoles = Role::workflow();
            $ackUser = ['IT' => 'tim.it', 'Einkauf' => 'eva.einkauf', 'Facility' => 'frank.facility'];

            // Beispielwert je Feldtyp (Pflichtfelder werden gefüllt)
            // Sample value per field type (mandatory fields are filled)
            $sampleValue = function (CustomField $f, Onboarding $o): ?string {
                return match (true) {
                    $f->label === 'Stellenbezeichnung' => 'Sachbearbeiter ' . ($o->department ?? 'Allgemein'),
                    $f->label === 'Bemerkungen an HR' => 'Bitte Onboarding-Termin in Woche 1 einplanen.',
                    $f->label === 'Berechtigungen anpassen' => 'Übernahme Bestand',
                    $f->label === 'Neuer Name' => $o->last_name . '-Neumann',
                    $f->label === 'Personalnummer' => 'P-1' . str_pad((string) ($o->id % 10000), 4, '0', STR_PAD_LEFT),
                    $f->label === 'Gewünschter Benutzername' => strtolower($o->first_name . '.' . $o->last_name),
                    $f->label === 'Kostenstelle' => '1000-10' . ($o->id % 10),
                    $f->label === 'Parkplatz-Kennzeichen' => 'S-AB 123' . ($o->id % 10),
                    $f->type === CustomField::TYPE_SELECT => $f->options[0] ?? 'Standard',
                    $f->type === CustomField::TYPE_MULTISELECT => json_encode(array_slice($f->options ?? [], 0, 1), JSON_UNESCAPED_UNICODE),
                    $f->type === CustomField::TYPE_CHECKBOX => CustomField::CHECKBOX_YES,
                    $f->type === CustomField::TYPE_DATE => optional($o->start_date)->toDateString() ?? now()->addMonth()->toDateString(),
                    $f->type === CustomField::TYPE_PRODUCT => json_encode(Product::where('custom_field_id', $f->id)->orderBy('id')->limit(2)->pluck('id')->all()),
                    default => 'Beispiel',
                };
            };

            $fillValues = function (Onboarding $o, bool $includeIntake = true) use ($sampleValue) {
                $roleIds = Role::workflow()->pluck('id')->all();
                if ($includeIntake && ($intake = Role::intake())) {
                    $roleIds[] = $intake->id;
                }
                foreach (CustomField::where('active', true)->whereIn('role_id', $roleIds)
                    ->forProcess($o->process ?? Process::ONBOARDING)->get() as $f) {
                    $o->values()->create([
                        'custom_field_id' => $f->id,
                        'value' => $sampleValue($f, $o),
                        'filled_by' => 'petra.chef',
                    ]);
                }
            };

            // 1) Vorbereitung: petra.chef kann Daten pflegen und absenden
            // 1) Preparation: petra.chef can maintain data and submit
            Onboarding::create([
                'first_name' => 'Lena',
                'last_name' => 'Beispiel',
                'start_date' => now()->addMonth()->startOfMonth(),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'draft',
                'supervisor_due_date' => now()->addWeek(),
                'roles_due_date' => now()->addWeeks(2),
                'notes' => 'Demo: Vorbereitung durch die Vorgesetzte.',
            ]);

            // 2) In Bearbeitung: Daten gefüllt, Abteilungen können quittieren
            // 2) In progress: data filled in, departments can acknowledge
            $open = Onboarding::create([
                'first_name' => 'Marc',
                'last_name' => 'Muster',
                'start_date' => now()->addWeeks(3)->startOfWeek(),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'open',
                'supervisor_due_date' => now()->subDays(3),
                'roles_due_date' => now()->subDay(),
                'notes' => 'Demo: alle Angaben gepflegt – Quittierung überfällig (Erinnerung testbar über reminders:send, wenn in den Einstellungen aktiviert).',
            ]);
            $fillValues($open);

            // 3) Wartet auf HR-Abschluss: alle Abteilungen haben quittiert
            // 3) Waiting for HR completion: all departments have acknowledged
            $review = Onboarding::create([
                'first_name' => 'Sara',
                'last_name' => 'Schnell',
                'start_date' => now()->addDays(10),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'review',
                'notes' => 'Demo: alle Quittierungen liegen vor – HR kann abschließen.',
            ]);
            $fillValues($review);
            foreach ($workflowRoles as $i => $r) {
                $review->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subDays(3 - $i),
                ]);
            }

            // 4) Stellenwechsel in Vorbereitung
            // 4) Job change in preparation
            Onboarding::create([
                'first_name' => 'Wanda',
                'last_name' => 'Wechsler',
                'process' => Process::JOB_CHANGE,
                'start_date' => now()->addWeeks(4)->startOfWeek(),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'draft',
                'notes' => 'Demo: Stellenwechsel – Vorbereitung durch die Vorgesetzte.',
            ]);

            // 5) Austritt in Bearbeitung: Abteilungen können quittieren
            // 5) Exit in progress: departments can acknowledge
            $exit = Onboarding::create([
                'first_name' => 'Otto',
                'last_name' => 'Abgang',
                'process' => Process::EXIT,
                'start_date' => now()->addWeeks(2)->endOfWeek(),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'open',
                'notes' => 'Demo: Austritt – Quittierung der Abteilungen steht aus.',
            ]);
            $fillValues($exit);

            // Austritt: IT hat bereits quittiert (teilweiser Fortschritt)
            // Exit: IT has already acknowledged (partial progress)
            $exit->steps()->create([
                'role_id' => $workflowRoles->first()->id,
                'completed_by' => 'tim.it',
                'completed_at' => now()->subDay(),
            ]);

            // 6) Stellenwechsel in Bearbeitung: Daten gefüllt, eine Quittierung liegt vor
            // 6) Job change in progress: data filled in, one acknowledgement present
            $change = Onboarding::create([
                'first_name' => 'Paul',
                'last_name' => 'Wandel',
                'process' => Process::JOB_CHANGE,
                'start_date' => now()->addWeeks(2)->startOfWeek(),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'open',
                'notes' => 'Demo: Stellenwechsel – IT hat bereits quittiert.',
            ]);
            $fillValues($change);
            $change->steps()->create([
                'role_id' => $workflowRoles->first()->id,
                'completed_by' => 'tim.it',
                'completed_at' => now()->subHours(6),
            ]);

            // 7) Stellenwechsel wartet auf HR-Abschluss
            // 7) Job change waiting for HR completion
            $changeReview = Onboarding::create([
                'first_name' => 'Sven',
                'last_name' => 'Stieg',
                'process' => Process::JOB_CHANGE,
                'start_date' => now()->addDays(7),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'review',
                'notes' => 'Demo: Stellenwechsel – alle Quittierungen liegen vor.',
            ]);
            $fillValues($changeReview);
            foreach ($workflowRoles as $i => $r) {
                $changeReview->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subDays(2)->addHours($i * 5),
                ]);
            }

            // 8) Austritt wartet auf HR-Abschluss
            // 8) Exit waiting for HR completion
            $exitReview = Onboarding::create([
                'first_name' => 'Egon',
                'last_name' => 'Ende',
                'process' => Process::EXIT,
                'start_date' => now()->addDays(5),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'review',
                'notes' => 'Demo: Austritt – alle Quittierungen liegen vor.',
            ]);
            $fillValues($exitReview);
            foreach ($workflowRoles as $i => $r) {
                $exitReview->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subDays(1)->addHours($i * 3),
                ]);
            }

            // 9) Abgeschlossener Stellenwechsel
            // 9) Completed job change
            $changeDone = Onboarding::create([
                'first_name' => 'Doris',
                'last_name' => 'Durch',
                'process' => Process::JOB_CHANGE,
                'start_date' => now()->subWeeks(3),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'done',
                'completed_at' => now()->subWeeks(2),
                'notes' => 'Demo: abgeschlossener Stellenwechsel.',
            ]);
            $fillValues($changeDone);
            foreach ($workflowRoles as $i => $r) {
                $changeDone->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subWeeks(2)->subDays(3 - $i),
                ]);
            }

            // 10) Abgeschlossener Austritt
            // 10) Completed exit
            $exitDone = Onboarding::create([
                'first_name' => 'Frida',
                'last_name' => 'Fort',
                'process' => Process::EXIT,
                'start_date' => now()->subMonths(1),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'done',
                'completed_at' => now()->subWeeks(3),
                'notes' => 'Demo: abgeschlossener Austritt (Aufbewahrungsfrist läuft).',
            ]);
            $fillValues($exitDone);
            foreach ($workflowRoles as $i => $r) {
                $exitDone->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subWeeks(3)->subDays(3 - $i),
                ]);
            }

            // 11) Stammdatenänderung in Bearbeitung (z. B. Namenswechsel)
            // 11) Master data change in progress (e.g. change of name)
            $master = Onboarding::create([
                'first_name' => 'Nina',
                'last_name' => 'Neumann',
                'process' => Process::MASTER_DATA_CHANGE,
                'start_date' => now()->addDays(3),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'open',
                'notes' => 'Demo: Namenswechsel nach Heirat – Quittierung der Abteilungen steht aus.',
            ]);
            $fillValues($master);

            // 12) Stammdatenänderung wartet auf HR-Abschluss
            // 12) Master data change waiting for HR completion
            $masterReview = Onboarding::create([
                'first_name' => 'Rolf',
                'last_name' => 'Richtig',
                'process' => Process::MASTER_DATA_CHANGE,
                'start_date' => now()->addDays(1),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'review',
                'notes' => 'Demo: Namenskorrektur – alle Quittierungen liegen vor.',
            ]);
            $fillValues($masterReview);
            foreach ($workflowRoles as $i => $r) {
                $masterReview->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subHours(20 - $i * 4),
                ]);
            }

            // 13) Abgeschlossene Stammdatenänderung
            // 13) Completed master data change
            $masterDone = Onboarding::create([
                'first_name' => 'Carla',
                'last_name' => 'Klar',
                'process' => Process::MASTER_DATA_CHANGE,
                'start_date' => now()->subWeeks(2),
                'supervisor_username' => 'petra.chef',
                'supervisor_name' => 'Petra Chefin',
                'supervisor_email' => 'petra.chef@demo.local',
                'department' => 'Vertrieb',
                'created_by' => 'anna.hr',
                'status' => 'done',
                'completed_at' => now()->subWeeks(1)->subDays(2),
                'notes' => 'Demo: abgeschlossene Stammdatenänderung.',
            ]);
            $fillValues($masterDone);
            foreach ($workflowRoles as $i => $r) {
                $masterDone->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subWeeks(2)->addDays($i),
                ]);
            }

            // 14) Azubi-Übernahme (verkürzter Workflow, Vorgesetzten-Frist überschritten)
            // 14) Apprentice takeover (shortened workflow, supervisor deadline exceeded)
            Onboarding::create([
                'first_name' => 'Azra',
                'last_name' => 'Azubi',
                'process' => 'azubi-uebernahme',
                'start_date' => now()->addWeeks(2)->startOfWeek(),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'draft',
                'supervisor_due_date' => now()->subDay(),
                'roles_due_date' => now()->addWeek(),
                'notes' => 'Demo: Azubi-Übernahme (nur IT und Facility) – Vorgesetzten-Frist erreicht, Erinnerung testbar über reminders:send.',
            ]);

            // 4) Abgeschlossen: fertiger Vorgang inkl. Abschluss durch HR
            // 4) Completed: finished case incl. completion by HR
            $done = Onboarding::create([
                'first_name' => 'Tom',
                'last_name' => 'Fertig',
                'start_date' => now()->subWeeks(2),
                'supervisor_username' => 'karl.chef',
                'supervisor_name' => 'Karl Chef',
                'supervisor_email' => 'karl.chef@demo.local',
                'department' => 'Produktion',
                'created_by' => 'anna.hr',
                'status' => 'done',
                'completed_at' => now()->subWeeks(1),
                'notes' => 'Demo: abgeschlossener Vorgang (Aufbewahrungsfrist läuft).',
            ]);
            $fillValues($done);
            foreach ($workflowRoles as $i => $r) {
                $done->steps()->create([
                    'role_id' => $r->id,
                    'completed_by' => $ackUser[$r->name] ?? 'tim.it',
                    'completed_at' => now()->subWeeks(1)->subDays(3 - $i),
                ]);
            }
        }

        $this->info('Demo-Daten angelegt: Rollen IT/Einkauf/Facility, Felder (DE/EN) und Beispielvorgänge in allen Status für alle Prozessarten inkl. verkürzter Azubi-Übernahme; Fristen/Erinnerungen testbar über reminders:send.');
        $this->line('Demo-Konten (Passwort jeweils "demo"):');
        $this->line('  anna.hr         – HR-Abteilung (legt Vorgänge an)');
        $this->line('  max.admin       – Admin (Backend: Rollen, Felder, Einstellungen)');
        $this->line('  petra.chef      – Vorgesetzte (Abteilung Vertrieb)');
        $this->line('  karl.chef       – Vorgesetzter (Abteilung Produktion)');
        $this->line('  tim.it          – Abteilung IT');
        $this->line('  eva.einkauf     – Abteilung Einkauf');
        $this->line('  frank.facility  – Abteilung Facility');
        $this->line('  rita.audit      – Auditorin (Protokoll & CSV-Export)');

        return self::SUCCESS;
    }
}
