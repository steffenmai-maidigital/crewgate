<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vollständiges Log aller ausgehenden E-Mails (inkl. Nachrichtentext)
        // Complete log of all outgoing e-mails (incl. message body)
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();
            $table->string('recipient');
            $table->string('subject');
            $table->text('body');
            $table->string('status');            // gesendet | fehlgeschlagen
            $table->text('error')->nullable();
            $table->foreignId('onboarding_id')->nullable()->constrained()->nullOnDelete();
            $table->string('onboarding_name')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        Schema::table('onboardings', function (Blueprint $table) {
            // E-Mail des Vorgesetzten (aus dem AD) für die Portal-Benachrichtigung
            // Supervisor's e-mail address (from AD) for the portal notification
            $table->string('supervisor_email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('onboardings', function (Blueprint $table) {
            $table->dropColumn('supervisor_email');
        });
        Schema::dropIfExists('mail_logs');
    }
};
