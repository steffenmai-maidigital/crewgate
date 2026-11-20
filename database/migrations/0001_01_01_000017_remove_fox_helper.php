<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Der Fuchs-Helfer ist entfernt: Spalte users.fox (Wahl je Person) und die
 * Einstellung fox_enabled (zentraler Schalter) fallen weg. Die Migration ist
 * idempotent; auch der alte Name fuchs_aktiv wird gelöscht, falls eine alte
 * Sicherung eingespielt wird. down() stellt nur die Spalte wieder her
 * (null = Fuchs an), die frühere Wahl lässt sich nicht zurückholen.
 *
 * The fox helper has been removed: the column users.fox (per-person choice)
 * and the setting fox_enabled (central switch) are dropped. The migration is
 * idempotent; the old name fuchs_aktiv is deleted as well in case an old
 * backup is restored. down() only restores the column (null = fox on); the
 * earlier choice cannot be brought back.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'fox')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('fox');
            });
        }
        if (Schema::hasTable('settings')) {
            DB::table('settings')->whereIn('key', ['fox_enabled', 'fuchs_aktiv'])->delete();
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'fox')) {
            Schema::table('users', function (Blueprint $table) {
                // Fuchs-Helfer: null = an / fox helper: null = on
                $table->boolean('fox')->nullable();
            });
        }
    }
};
