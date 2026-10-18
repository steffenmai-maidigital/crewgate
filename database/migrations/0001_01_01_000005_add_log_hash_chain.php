<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            // Hash-Kette für Revisionssicherheit: jeder Eintrag enthält den
            // SHA-256 über seinen Inhalt + den Hash des Vorgängers. Nachträgliche
            // Änderungen oder Lücken sind damit erkennbar (Manipulationsschutz
            // im Sinne von ISO/IEC 27001, A.8.15 Protokollierung).
            // Hash chain for audit-proof logging: each entry contains the
            // SHA-256 of its content + the hash of its predecessor. Later
            // changes or gaps can thus be detected (tamper protection
            // in the sense of ISO/IEC 27001, A.8.15 Logging).
            $table->string('hash', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('hash');
        });
    }
};
