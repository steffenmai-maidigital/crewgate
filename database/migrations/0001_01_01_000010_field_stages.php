<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            // Für HR-Felder: wann wird das Feld ausgefüllt?
            //   anlage       = durch HR beim Anlegen des Vorgangs
            //   vorbereitung = durch den Vorgesetzten in der Vorbereitung
            // For HR fields: when is the field filled in?
            //   anlage       = by HR when creating the case
            //   vorbereitung = by the supervisor during preparation
            $table->string('fill_stage')->default('anlage');

            // Feldwert ist für ALLE Abteilungen sichtbar (Ansicht + Mails),
            // unabhängig von der "Sieht Daten von"-Freigabe.
            // Field value is visible to ALL departments (view + mails),
            // regardless of the "Sees data of" sharing.
            $table->boolean('visible_to_all')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->dropColumn(['fill_stage', 'visible_to_all']);
        });
    }
};
