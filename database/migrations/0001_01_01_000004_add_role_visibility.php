<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Welche Rollen-Daten dürfen die AD-Mitglieder dieser Rolle in
            // Vorgängen sehen? (Liste von Rollen-IDs; eigene Felder sieht
            // eine Rolle immer.)
            // Which roles' data may the AD members of this role see in
            // cases? (List of role IDs; a role always sees its own
            // fields.)
            $table->json('visible_role_ids')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('visible_role_ids');
        });
    }
};
