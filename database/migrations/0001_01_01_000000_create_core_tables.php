<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Zentrale Einstellungen (SMTP/SMTPS, LDAP-Suchbasis, ...)
        // Central settings (SMTP/SMTPS, LDAP search base, ...)
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Rollen = Abteilungen, gemappt auf AD-Gruppen
        // Roles = departments, mapped to AD groups
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // z. B. "IT", "Einkauf" / e.g. "IT", "Purchasing"
            $table->string('ad_group_dn')->nullable();    // DN der AD-Gruppe / DN of the AD group
            $table->string('central_email')->nullable();  // zentrale Empfangsadresse / central recipient address
            $table->unsignedInteger('sort_order')->default(0); // Reihenfolge im Workflow / order in the workflow
            $table->boolean('in_workflow')->default(true);
            $table->timestamps();
        });

        // Konfigurierbare Felder pro Rolle
        // Configurable fields per role
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->string('type');                 // select | text
            $table->json('options')->nullable();    // für select / for select
            $table->string('regex')->nullable();    // für text (ohne Delimiter) / for text (without delimiters)
            $table->string('regex_hint')->nullable();
            $table->boolean('required')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Onboarding-Vorgänge
        // Onboarding cases
        Schema::create('onboardings', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->date('start_date')->nullable();
            $table->string('department')->nullable();          // aus AD gelesen / read from AD
            $table->string('supervisor_username')->nullable(); // sAMAccountName
            $table->string('supervisor_name')->nullable();
            $table->string('created_by');                      // HR-Benutzer / HR user
            $table->foreignId('current_role_id')->nullable()->constrained('roles')->nullOnDelete();
            $table->string('status')->default('open');         // open | done
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Ausgefüllte Feldwerte
        // Filled-in field values
        Schema::create('onboarding_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->text('value')->nullable();
            $table->string('filled_by')->nullable();
            $table->timestamps();
            $table->unique(['onboarding_id', 'custom_field_id']);
        });

        // Protokoll der durchlaufenen Schritte
        // Log of the completed steps
        Schema::create('onboarding_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->string('completed_by')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_steps');
        Schema::dropIfExists('onboarding_values');
        Schema::dropIfExists('onboardings');
        Schema::dropIfExists('custom_fields');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('settings');
    }
};
