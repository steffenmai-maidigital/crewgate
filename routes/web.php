<?php

use App\Http\Controllers\Admin\FieldController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OnboardingController;
use Illuminate\Support\Facades\Route;

// Alte (deutsche) Adressen leiten dauerhaft auf die neuen um (Standard 10);
// zuerst geladen, damit alte Handbuch-Kennungen vor der Anmeldung umleiten
// Old (German) addresses redirect permanently to the new ones (standard 10);
// loaded first so that old manual slugs redirect before the sign-in check
require __DIR__ . '/legacy.php';

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['de', 'en'], true), 404);
    session(['locale' => $locale]);
    return redirect()->back();
})->name('locale.switch');

// Impressum und Datenschutz: öffentlich, auch ohne Anmeldung
// Imprint (legal notice) and privacy policy: public, even without sign-in
Route::get('/imprint', [\App\Http\Controllers\LegalController::class, 'imprint'])->name('imprint');
Route::get('/privacy', [\App\Http\Controllers\LegalController::class, 'privacy'])->name('privacy');

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
// Bremse gegen Passwort-Raten: 10 Versuche je Minute und Adresse (Standard 06)
// Throttle against password guessing: 10 attempts per minute and address (Standard 06)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.post');
// Zweiter Faktor (TOTP) nach Passwort bzw. Verzeichnis
// Second factor (TOTP) after password or directory
Route::get('/login/code', [AuthController::class, 'showTwoFactor'])->name('login.code');
Route::post('/login/code', [AuthController::class, 'twoFactor'])->middleware('throttle:15,1');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
// direkt aufgerufene Abmelde-Adresse: nicht mit „Method Not Allowed“ enden
// sign-out address opened directly: do not end with "Method Not Allowed"
Route::get('/logout', fn () => redirect()->route('login'));

Route::middleware('ldap.auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    // Hilfe (Standard 03): Kurzanleitung, System, Lizenz, Handbücher, Changelog, Fehler, Support
    // Help (Standard 03): quick guide, system, licence, manuals, changelog, errors, support
    Route::get('/help', [\App\Http\Controllers\HelpController::class, 'show'])->name('help');
    Route::get('/help/manual/{doc}', [\App\Http\Controllers\HelpController::class, 'doc'])->name('help.doc');
    Route::delete('/help/log', [\App\Http\Controllers\HelpController::class, 'clearLog'])->name('help.clear-log');
    Route::get('/support', fn () => redirect()->route('help'));   // alte Adresse / old address
    // Eigenes Konto: Passwort, Zwei-Faktor, Fuchs-Helfer
    // Own account: password, two-factor, fox helper
    Route::get('/account', [\App\Http\Controllers\AccountController::class, 'show'])->name('account');
    Route::put('/account/password', [\App\Http\Controllers\AccountController::class, 'password'])->name('account.password');
    Route::post('/account/fox', [\App\Http\Controllers\AccountController::class, 'fox'])->name('account.fox');
    Route::post('/account/2fa', [\App\Http\Controllers\AccountController::class, 'startTwoFactor'])->name('account.2fa.start');
    Route::post('/account/2fa/confirm', [\App\Http\Controllers\AccountController::class, 'confirmTwoFactor'])->name('account.2fa.confirm');
    Route::post('/account/2fa/codes', [\App\Http\Controllers\AccountController::class, 'recoveryCodes'])->name('account.2fa.codes');
    Route::delete('/account/2fa', [\App\Http\Controllers\AccountController::class, 'disableTwoFactor'])->name('account.2fa.disable');

    // Vorgänge
    // Cases
    Route::get('/onboardings/{onboarding}', [OnboardingController::class, 'show'])->name('onboardings.show');
    Route::post('/onboardings/{onboarding}/roles/{role}/complete', [OnboardingController::class, 'completeStep'])->name('onboardings.complete');
    Route::post('/onboardings/{onboarding}/draft', [OnboardingController::class, 'saveDraft'])->name('onboardings.draft');
    Route::post('/onboardings/{onboarding}/submit', [OnboardingController::class, 'submit'])->name('onboardings.submit');
    Route::post('/onboardings/{onboarding}/roles/{role}/acknowledge', [OnboardingController::class, 'acknowledge'])->name('onboardings.acknowledge');
    Route::post('/onboardings/{onboarding}/cancel', [OnboardingController::class, 'cancel'])->name('onboardings.cancel');
    Route::delete('/onboardings/{onboarding}', [OnboardingController::class, 'destroy'])->name('onboardings.destroy');
    Route::post('/onboardings/{onboarding}/close', [OnboardingController::class, 'close'])->name('onboardings.close');

    // Vorgänge anlegen: HR sowie Konten mit dem Recht „Vorgänge anlegen“
    // Create cases: HR and accounts with the "Create cases" right
    Route::middleware('role.create')->group(function () {
        Route::get('/onboardings-new', [OnboardingController::class, 'create'])->name('onboardings.create');
        Route::post('/onboardings', [OnboardingController::class, 'store'])->name('onboardings.store');
        Route::get('/onboardings-import', [OnboardingController::class, 'importWizard'])->name('onboardings.import');
        Route::post('/onboardings-import/upload', [OnboardingController::class, 'importParse'])->name('onboardings.import.upload');
        Route::post('/onboardings-import/row', [OnboardingController::class, 'importCreateRow'])->name('onboardings.import.row');
        Route::post('/onboardings-import/discard-row', [OnboardingController::class, 'importDiscardRow'])->name('onboardings.import.discard');
        Route::post('/onboardings-import/reset', [OnboardingController::class, 'importReset'])->name('onboardings.import.reset');
        Route::get('/onboardings-import/template', [OnboardingController::class, 'importTemplate'])->name('onboardings.import.template');
        Route::get('/ad/supervisor', [OnboardingController::class, 'lookupSupervisor'])->name('ad.supervisor');
    });

    // Protokoll: sichtbar für HR und Administratoren
    // Log: visible to HR and administrators
    Route::middleware('role.hr-or-admin')->group(function () {
        Route::get('/processes', [\App\Http\Controllers\ProcessController::class, 'index'])->name('processes.index');
        Route::post('/processes', [\App\Http\Controllers\ProcessController::class, 'store'])->name('processes.store');
        Route::post('/processes/{process}', [\App\Http\Controllers\ProcessController::class, 'update'])->name('processes.update');
        Route::delete('/processes/{process}', [\App\Http\Controllers\ProcessController::class, 'destroy'])->name('processes.destroy');

        Route::get('/audit-log', [\App\Http\Controllers\LogController::class, 'index'])->name('logs');
        Route::get('/audit-log/export', [\App\Http\Controllers\LogController::class, 'export'])->name('logs.export');
        Route::get('/audit-log/export-pdf', [\App\Http\Controllers\LogController::class, 'exportPdf'])->name('logs.export_pdf');
    });

    // Felder- und Produktpflege: Admins, HR (Anlage-Felder) und
    // Rollen-Mitglieder (jeweils nur für die eigenen Rollen)
    // Field and product maintenance: admins, HR (intake fields) and
    // role members (each only for their own roles)
    Route::middleware('role.manage')->prefix('manage')->name('admin.')->group(function () {
        Route::get('/fields', [FieldController::class, 'index'])->name('fields');
        Route::post('/fields', [FieldController::class, 'store'])->name('fields.store');
        Route::put('/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
        Route::delete('/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');

        Route::get('/products', [\App\Http\Controllers\Admin\ProductController::class, 'index'])->name('products');
        Route::post('/products', [\App\Http\Controllers\Admin\ProductController::class, 'store'])->name('products.store');
        Route::post('/products/purge', [\App\Http\Controllers\Admin\ProductController::class, 'purge'])->name('products.purge');
        Route::put('/products/{product}', [\App\Http\Controllers\Admin\ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [\App\Http\Controllers\Admin\ProductController::class, 'destroy'])->name('products.destroy');

        Route::post('/categories', [\App\Http\Controllers\Admin\ProductController::class, 'storeCategory'])->name('categories.store');
        Route::put('/categories/{category}', [\App\Http\Controllers\Admin\ProductController::class, 'updateCategory'])->name('categories.update');
        Route::delete('/categories/{category}', [\App\Http\Controllers\Admin\ProductController::class, 'destroyCategory'])->name('categories.destroy');
    });

    // Backend (Admin-Reiter)
    // Backend (admin tab)
    Route::middleware('role.admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles');
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
        Route::post('/roles/sync', [RoleController::class, 'sync'])->name('roles.sync');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

        // Benutzer: eigene Konten anlegen und pflegen, Verzeichniskonten ansehen und sperren
        // Users: create and maintain own accounts, view and lock directory accounts
        Route::get('/users', [UserController::class, 'index'])->name('users');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/password', [UserController::class, 'resetPassword'])->name('users.password');
        Route::delete('/users/{user}/2fa', [UserController::class, 'resetTwoFactor'])->name('users.2fa');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

        Route::get('/mail-log', [\App\Http\Controllers\Admin\MailLogController::class, 'index'])->name('maillog');

        Route::get('/settings', [SettingController::class, 'index'])->name('settings');
        Route::post('/settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/settings/testmail', [SettingController::class, 'testMail'])->name('settings.testmail');
        Route::post('/settings/test-directory', [SettingController::class, 'testLdap'])->name('settings.testldap');
        Route::post('/settings/api-token', [SettingController::class, 'regenerateApiToken'])->name('settings.apitoken');
        Route::post('/backup/now', [\App\Http\Controllers\Admin\BackupController::class, 'backupNow'])->name('backup.now');
    });
});

// Unbekannte Adressen über die web-Middleware (Sitzung, Sprache) leiten, damit
// die 404-Seite in der gewählten Sprache erscheint
// Route unknown addresses through the web middleware (session, locale) so
// that the 404 page appears in the chosen language
Route::fallback(fn (\Illuminate\Http\Request $request) => \App\Support\ErrorPage::unknownAddress($request));
