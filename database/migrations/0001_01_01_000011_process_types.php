<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Prozessart des Vorgangs: onboarding (Einstellung), wechsel
        // (Stellenwechsel) oder austritt
        // Process type of the case: onboarding, wechsel
        // (job change) or austritt (exit)
        Schema::table('onboardings', function (Blueprint $table) {
            $table->string('process')->default('onboarding');
        });

        // Zuordnung der Felder zu den Prozessen (JSON-Liste). Bestehende
        // Felder gelten zunächst für alle Prozesse und können je Prozess
        // abgewählt werden.
        // Assignment of the fields to the processes (JSON list). Existing
        // fields initially apply to all processes and can be deselected
        // per process.
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->json('processes')->default('["onboarding","wechsel","austritt"]');
        });
    }

    public function down(): void
    {
        Schema::table('onboardings', fn (Blueprint $t) => $t->dropColumn('process'));
        Schema::table('custom_fields', fn (Blueprint $t) => $t->dropColumn('processes'));
    }
};
