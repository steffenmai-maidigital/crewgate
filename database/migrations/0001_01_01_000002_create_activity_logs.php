<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->string('type');                    // vorgang | feld | schritt | mail | einstellungen
            $table->string('user')->nullable();        // handelnder Benutzer (sAMAccountName) / acting user
            $table->foreignId('onboarding_id')->nullable()->constrained()->nullOnDelete();
            // bleibt auch nach Löschung des Vorgangs lesbar
            // stays readable after the case is deleted
            $table->string('onboarding_name')->nullable();
            $table->string('message');                 // Kurzbeschreibung / short description
            // z. B. Feld => Wert, Empfänger, Betreff
            // e.g. field => value, recipient, subject
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
