<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unveränderliche Kennung der Verzeichniskonten (AD-objectGUID bzw.
 * OpenLDAP-entryUUID): Die Anmeldung findet das Konto darüber auch nach einer
 * Umbenennung im Verzeichnis. Eigene Konten haben keine Kennung (null).
 *
 * Immutable id of directory accounts (AD objectGUID or OpenLDAP entryUUID):
 * sign-in finds the account by it even after a rename in the directory.
 * Own accounts have no id (null).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'guid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('guid', 64)->nullable()->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'guid')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['guid']);
                $table->dropColumn('guid');
            });
        }
    }
};
