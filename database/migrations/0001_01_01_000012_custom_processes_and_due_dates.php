<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Konfigurierbare Prozessarten (durch HR pflegbar). role_ids = Liste
        // der beteiligten Workflow-Rollen (leer/null = alle) -> damit sind
        // verkürzte Workflows wie "Azubi-Übernahme" möglich.
        // Configurable process types (maintainable by HR). role_ids = list
        // of the workflow roles involved (empty/null = all) -> this allows
        // shortened workflows such as "apprentice takeover".
        Schema::create('processes', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->json('role_ids')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
        });

        // Die vier eingebauten Prozesse übernehmen
        // Take over the four built-in processes
        $now = now();
        DB::table('processes')->insert([
            ['key' => 'onboarding', 'name' => 'Einstellung', 'name_en' => 'Onboarding', 'sort_order' => 1, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'wechsel', 'name' => 'Stellenwechsel', 'name_en' => 'Position change', 'sort_order' => 2, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'austritt', 'name' => 'Austritt', 'name_en' => 'Offboarding', 'sort_order' => 3, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'stammdaten', 'name' => 'Stammdatenänderung', 'name_en' => 'Master data change', 'sort_order' => 4, 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Fristen + Merker für einmalige Erinnerungsmails
        // Due dates + flags for one-off reminder mails
        Schema::table('onboardings', function (Blueprint $table) {
            $table->date('supervisor_due_date')->nullable();
            $table->date('roles_due_date')->nullable();
            $table->timestamp('supervisor_reminder_sent_at')->nullable();
            $table->timestamp('roles_reminder_sent_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('onboardings', function (Blueprint $table) {
            $table->dropColumn(['supervisor_due_date', 'roles_due_date', 'supervisor_reminder_sent_at', 'roles_reminder_sent_at']);
        });
        Schema::dropIfExists('processes');
    }
};
