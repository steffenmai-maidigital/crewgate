<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kategorien mit Farbe und Reihenfolge (bestimmt die Sortierung
        // der Produkte in der Auswahl im Workflow)
        // Categories with colour and order (determines the sorting
        // of the products in the selection in the workflow)
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#64748b');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('products', function (Blueprint $table) {
            // Zuweisung zu einem Produktauswahl-Feld einer Rolle
            // (null = Produkt erscheint in allen Produktauswahl-Feldern)
            // Assignment to a product selection field of a role
            // (null = product appears in all product selection fields)
            $table->foreignId('custom_field_id')->nullable()->constrained('custom_fields')->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('product_categories')->nullOnDelete();
            // Für die Bereinigung: seit wann deaktiviert
            // For the clean-up: deactivated since when
            $table->timestamp('deactivated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['custom_field_id', 'category_id', 'deactivated_at']);
        });
        Schema::dropIfExists('product_categories');
    }
};
