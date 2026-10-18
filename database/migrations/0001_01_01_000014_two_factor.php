<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Zwei-Faktor-Anmeldung (TOTP) und Ausnahme je Benutzer (Standard 09)
 *
 * Two-factor sign-in (TOTP) and per-user exception (Standard 09)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->unsignedBigInteger('two_factor_last_step')->nullable();
            $table->string('mfa_policy', 10)->nullable();   // null = nach Gruppe / null = by group
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn([
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at', 'two_factor_last_step', 'mfa_policy',
        ]));
    }
};
