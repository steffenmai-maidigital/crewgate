<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rollen bekommen einen optionalen englischen Namen (roles.name_en) wie
 * Felder, Produkte und Prozesse. Die englische Oberfläche zeigt ihn, sonst
 * den deutschen Namen. Die feste HR-Anlage-Rolle bekommt ihren englischen
 * Namen, solange sie noch den eingebauten deutschen Namen trägt. Idempotent;
 * down() entfernt die Spalte wieder.
 *
 * Roles get an optional English name (roles.name_en) like fields, products
 * and processes. The English interface shows it, otherwise the German name.
 * The fixed HR intake role gets its English name as long as it still carries
 * the built-in German name. Idempotent; down() removes the column again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('roles')) {
            return;
        }
        if (! Schema::hasColumn('roles', 'name_en')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->string('name_en', 100)->nullable()->after('name');
            });
        }
        DB::table('roles')->where('is_intake', true)->where('name', 'HR (Anlage des Vorgangs)')
            ->whereNull('name_en')->update(['name_en' => 'HR (case creation)']);
    }

    public function down(): void
    {
        if (Schema::hasTable('roles') && Schema::hasColumn('roles', 'name_en')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropColumn('name_en');
            });
        }
    }
};
