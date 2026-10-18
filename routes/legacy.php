<?php

use App\Http\Controllers\HelpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Alte (deutsche) Adressen vor Standard 10 — leiten dauerhaft (301) auf die
 * neuen um, damit Lesezeichen, Links in Mails und Hilfe-Links gültig bleiben.
 * Nur GET; Formulare (POST/PUT/DELETE) kommen immer von der neuen Seite.
 * Die Abfrage (?…) wird mitgenommen. Geladen von routes/web.php.
 *
 * Old (German) addresses from before standard 10 — redirect permanently (301)
 * to the new ones so that bookmarks, links in mails and help links stay
 * valid. GET only; forms (POST/PUT/DELETE) always come from the new page.
 * The query (?…) is kept. Loaded by routes/web.php.
 */

/**
 * Ziel mit derselben Abfrage / Target with the same query
 */
$legacyRedirect = function (Request $request, string $target) {
    // unverändert (getQueryString() würde die Parameter umsortieren) / unchanged (getQueryString() would reorder)
    $query = (string) $request->server('QUERY_STRING', '');

    return redirect()->to($target . ($query !== '' ? '?' . $query : ''), 301);
};

// Alte Adresse => neue Adresse; {…} wird aus der alten Adresse übernommen
// Old address => new address; {…} is taken over from the old address
$legacyPaths = [
    '/sprache/{locale}'            => '/locale/{locale}',
    '/impressum'                   => '/imprint',
    '/datenschutz'                 => '/privacy',
    '/hilfe'                       => '/help',
    '/konto'                       => '/account',
    '/onboardings-neu'             => '/onboardings-new',
    '/onboardings-import/vorlage'  => '/onboardings-import/template',
    '/prozesse'                    => '/processes',
    '/protokoll'                   => '/audit-log',
    '/protokoll/export'            => '/audit-log/export',
    '/protokoll/export-pdf'        => '/audit-log/export-pdf',
    '/verwaltung/felder'           => '/manage/fields',
    '/verwaltung/produkte'         => '/manage/products',
    '/admin/rollen'                => '/admin/roles',
    '/admin/benutzer'              => '/admin/users',
    '/admin/einstellungen'         => '/admin/settings',
];

foreach ($legacyPaths as $old => $new) {
    Route::get($old, function (Request $request) use ($legacyRedirect, $new) {
        $target = preg_replace_callback('/\{(\w+)\}/', fn ($m) => rawurlencode((string) $request->route($m[1])), $new);

        return $legacyRedirect($request, $target);
    });
}

// Handbücher: Adresse und Kennung (benutzer → user …) / Manuals: address and slug (benutzer → user …)
Route::get('/hilfe/handbuch/{doc}', function (Request $request, string $doc) use ($legacyRedirect) {
    $doc = HelpController::LEGACY_DOCS[$doc] ?? $doc;

    return $legacyRedirect($request, '/help/manual/' . rawurlencode($doc));
});
// eigener Platzhaltername: dieselbe Adresse wie help.doc würde die Route ersetzen
// own placeholder name: the same URI as help.doc would replace the route
Route::get('/help/manual/{legacyDoc}', fn (Request $request, string $legacyDoc) => $legacyRedirect($request, '/help/manual/' . HelpController::LEGACY_DOCS[$legacyDoc]))
    ->where('legacyDoc', implode('|', array_keys(HelpController::LEGACY_DOCS)));
