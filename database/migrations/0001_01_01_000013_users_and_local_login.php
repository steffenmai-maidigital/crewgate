<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anmeldung mit und ohne Verzeichnis (Standard 09): Benutzerkonten in der
 * Datenbank. Eigene Konten tragen ein Passwort (bcrypt); Konten aus dem
 * Verzeichnis (auth_source = ldap) entstehen bei der ersten Anmeldung und
 * haben kein Passwort. Rollen (Abteilungen) eigener Konten liegen in
 * role_user; bei Verzeichniskonten kommen sie aus den Gruppen.
 *
 * Sign-in with and without a directory (Standard 09): user accounts in the
 * database. Own (local) accounts carry a password (bcrypt); accounts from the
 * directory (auth_source = ldap) are created on first sign-in and have no
 * password. Roles (departments) of own accounts are stored in role_user; for
 * directory accounts they come from the groups.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('department')->nullable();
            $table->string('auth_source', 10)->default('local');   // local | ldap
            $table->string('ldap_dn')->nullable();
            $table->string('password')->nullable();                 // nur eigene Konten / own accounts only
            $table->boolean('is_hr')->default(false);
            $table->boolean('is_admin')->default(false);
            $table->boolean('is_auditor')->default(false);
            $table->boolean('can_create')->default(false);
            $table->boolean('active')->default(true);
            $table->boolean('fuchs')->nullable();                   // Fuchs-Helfer: null = an / fox helper: null = on
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('users');
    }
};
