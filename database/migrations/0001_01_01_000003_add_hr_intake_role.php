<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // Kennzeichnet die feste HR-Rolle, deren Felder beim ANLEGEN
            // eines Vorgangs ausgefüllt werden (kein Workflow-Schritt).
            // Marks the fixed HR role whose fields are filled in when a case is
            // CREATED (not a workflow step).
            $table->boolean('is_intake')->default(false);
        });

        // Die HR-Anlage-Rolle einmalig anlegen
        // Create the HR intake role once
        if (! DB::table('roles')->where('is_intake', true)->exists()) {
            DB::table('roles')->insert([
                'name' => 'HR (Anlage des Vorgangs)',
                'is_intake' => true,
                'in_workflow' => false,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('roles')->where('is_intake', true)->delete();

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('is_intake');
        });
    }
};
