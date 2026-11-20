<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Startpasswort muss geändert werden (users.must_change_password): gesetzt
 * für den ersten Admin mit Zufallspasswort, für Konten mit einem Passwort vom
 * Admin und nach „Passwort zurücksetzen“. Bestehende Konten bleiben
 * unberührt (Standard false). down() entfernt die Spalte wieder.
 *
 * Initial password must be changed (users.must_change_password): set for the
 * first admin with a random password, for accounts with a password from the
 * admin and after "reset password". Existing accounts are not affected
 * (default false). down() removes the column again.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('must_change_password')->default(false)->after('password');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'must_change_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('must_change_password');
            });
        }
    }
};
