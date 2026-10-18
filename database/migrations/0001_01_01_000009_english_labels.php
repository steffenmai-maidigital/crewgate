<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optionale englische Bezeichnungen – werden in der Oberfläche
        // verwendet, wenn Englisch als Sprache gewählt ist.
        // Optional English labels – used in the interface
        // when English is selected as the language.
        Schema::table('custom_fields', function (Blueprint $table) {
            $table->string('label_en')->nullable()->after('label');
        });
        Schema::table('products', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
        Schema::table('product_categories', function (Blueprint $table) {
            $table->string('name_en')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('custom_fields', fn (Blueprint $t) => $t->dropColumn('label_en'));
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn('name_en'));
        Schema::table('product_categories', fn (Blueprint $t) => $t->dropColumn('name_en'));
    }
};
