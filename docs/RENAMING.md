# Renaming to English identifiers (standard 10)

In October 2026 every German identifier in CrewGate was renamed to English
(classes, methods, variables, routes, views, CSS/JS names, database columns,
stored values, settings keys, commands, files). The user interface did not
change: German UI strings stay the translation keys, English lives in
`lang/en.json`, the manuals under `docs/` stay German.

This table is for people with an **old fork, old scripts or an old backup**.
Existing installations need nothing: the migration
`database/migrations/0001_01_01_000016_rename_to_english.php` converts the
database at start-up (idempotent, also for restored old backups), old page
addresses redirect permanently (`routes/legacy.php`), the old API address and
the old artisan command name keep working. The audit log (`activity_logs`) is
a SHA-256 hash chain and is **never** rewritten — its stored types stay
German, only the constants got English names.

Shared building blocks (Directory, Mfa, TwoFactor, Fox, LegalPages,
ProductInfo, ErrorPage) use the same
names in CrewGate, LinkHub and BilDaBooky.

## Files and folders

| old | new |
|---|---|
| `app/Support/Verzeichnis.php` | `app/Support/Directory.php` |
| `app/Console/Commands/VerzeichnisExtern.php` | `app/Console/Commands/DirectoryIsolated.php` |
| `app/Support/Fuchs.php` | `app/Support/Fox.php` |
| `app/Support/Rechtliches.php` | `app/Support/LegalPages.php` |
| `app/Support/Produkt.php` | `app/Support/ProductInfo.php` |
| `app/Support/Fehlerseite.php` | `app/Support/ErrorPage.php` |
| `app/Models/Concerns/ZweiFaktor.php` | `app/Models/Concerns/TwoFactor.php` |
| `app/Console/Commands/AdminPasswort.php` | `app/Console/Commands/AdminPassword.php` |
| `config/produkt.php` | `config/product.php` |
| `config/sicherung.php` | `config/backup.php` |
| — | `routes/legacy.php` (old addresses) |
| — | `database/migrations/0001_01_01_000016_rename_to_english.php` |
| `resources/views/hilfe.blade.php`, `hilfe-doc.blade.php` | `help.blade.php`, `help-doc.blade.php` |
| `resources/views/konto.blade.php` | `account.blade.php` |
| `resources/views/partials/fuchs.blade.php` | `partials/fox.blade.php` |
| `resources/views/partials/rechtliches-einstellungen.blade.php` | `partials/legal-settings.blade.php` |
| `resources/views/partials/rechtliches-links.blade.php` | `partials/legal-links.blade.php` |
| `resources/views/rechtliches/seite.blade.php` | `legal/page.blade.php` |
| `public/js/fuchs.js`, `public/js/symbole.js` | `public/js/fox.js`, `public/js/symbols.js` |
| `docs/BENUTZERHANDBUCH.md` | `docs/USER-GUIDE.md` |
| `docs/ADMIN-HANDBUCH.md` | `docs/ADMIN-GUIDE.md` |
| `docs/ARCHITEKTUR.md` | `docs/ARCHITECTURE.md` |
| `docs/SICHERHEIT.md` | `docs/SECURITY.md` |
| `docs/DOKUMENTATION.md` | `docs/DOCUMENTATION.md` |
| backup `crewgate_sicherung_<stamp>.zip` | `crewgate_backup_<stamp>.zip` |
| CSV export `protokoll-<stamp>.csv` | `audit-log-<stamp>.csv` |
| PDF `audit-bericht-<stamp>.pdf` | `audit-report-<stamp>.pdf` |
| import template `crewgate-import-vorlage.csv` | `crewgate-import-template.csv` |

## Classes, constants, methods

| old | new |
|---|---|
| `Verzeichnis::SCHLUESSEL`, `VERSCHLUESSELUNG`, `GRUPPEN_KLASSEN`, `STANDARD_FILTER` | `Directory::KEYS`, `ENCRYPTION`, `GROUP_CLASSES`, `DEFAULT_FILTER` |
| `einstellungen()`, `aktiv()`, `ausEinstellungen()`, `speichern()`, `regeln()` | `settings()`, `enabled()`, `fromSettings()`, `save()`, `rules()` |
| `anmelden()`, `pruefen()`, `finden()`, `suchen()`, `gruppen()`, `mitglieder()`, `gehoertZu()` | `authenticate()`, `test()`, `find()`, `search()`, `groups()`, `members()`, `isMemberOf()` |
| `normalisiereDn()`, `externAusfuehren()`, `$tlsImProzess` | `normaliseDn()`, `handleIsolated()`, `$processTls` |
| private `verbinden`, `dienstBind`, `benutzerBind`, `ausVorlage`, `basisFilter`, `benutzerFilter`, `benutzerSuchen`, `benutzerListe`, `benutzerListeRoh`, `alleEintraege`, `gruppenVon`, `gruppenDn`, `eintrag`, `wert`, `werte`, `fehlerText`, `caDatei`, `tlsPasst`, `extern`, `attribute` | `connect`, `serviceBind`, `userBind`, `fromTemplate`, `baseFilter`, `userFilter`, `searchUsers`, `userList`, `userListRaw`, `allEntries`, `groupsOf`, `groupDns`, `entry`, `value`, `values`, `errorText`, `caFile`, `tlsMatches`, `runIsolated`, `attributes` |
| directory entry keys `vorname`, `nachname`, `gruppen` | `first_name`, `last_name`, `groups` |
| `pruefen()` result `modus` (`dienst`/`vorlage`), `eintraege`, `millisekunden` | `test()` result `mode` (`service`/`template`), `entries`, `milliseconds` |
| `config('ldap.*')` `aktiv`, `verschluesselung`, `bind_passwort`, `bind_vorlage`, `benutzer_attribut`, `benutzer_filter`, `zertifikat` | `enabled`, `encryption`, `bind_password`, `bind_template`, `user_attribute`, `user_filter`, `certificate` |
| isolated-process JSON `k`, `methode`, `argumente` / `ergebnis`, `fehler` | `settings`, `method`, `arguments` / `result`, `error` |
| `Mfa::PFLICHT`, `OPTIONAL`, `AUS`, `STUFEN`, `RANG` | `Mfa::REQUIRED`, `OPTIONAL`, `OFF`, `LEVELS`, `RANK` |
| `Mfa::gruppen()`, `stufe()`, `pflichtGruppen()`, `richtlinie()`, `erforderlich()`, `erlaubt()`, `titel()` | `groups()`, `level()`, `requiredGroups()`, `policy()`, `isRequired()`, `isAllowed()`, `label()` |
| trait `ZweiFaktor` | trait `TwoFactor` |
| `User::LOKAL`, `VERZEICHNIS` | `User::LOCAL`, `DIRECTORY` (values `local`, `ldap` unchanged) |
| `istLokal()`, `herkunft()`, `zeigtFuchs()`, `rechteAusGruppen()`, `sitzungsdaten()`, `startPasswort()` | `isLocal()`, `origin()`, `showsFox()`, `permissionsFromGroups()`, `sessionData()`, `initialPassword()` |
| `Fuchs::sichtbar()`, `anzeige()`, `gast()`, `daten()`, private `tagesgruss()`, `paket()` | `Fox::visible()`, `display()`, `guest()`, `data()`, `greeting()`, `package()` |
| `Rechtliches::ARTEN`, `aktiv()`, `titel()`, `speichern()`, `vorlageDatenschutz()`, private `zeile()` | `LegalPages::TYPES`, `enabled()`, `title()`, `save()`, `privacyTemplate()`, `line()` (`text()`, `link()`, `url()`, `links()`, `html()` unchanged) |
| `LegalPages::links()` keys `titel`, `extern` | `title`, `external` |
| `Produkt::quellcode()`, `projektseite()` | `ProductInfo::sourceUrl()`, `projectUrl()` |
| `Fehlerseite::referenz()`, `grund()`, `protokolliert()`, `wartezeit()`, `uploadGrenze()`, `angemeldet()`, `unbekannteAdresse()`, `ZEICHEN` | `ErrorPage::reference()`, `reason()`, `isLogged()`, `retryAfter()`, `uploadLimit()`, `signedIn()`, `unknownAddress()`, `CHARACTERS` |
| `ErrorLog::TAGE`, result keys `zeilen`, `anzahl` | `ErrorLog::DAYS`, `lines`, `count` |
| `LdapService::aktiv()` | `LdapService::enabled()` |
| `DemoDirectory::GROUP_EINKAUF` | `DemoDirectory::GROUP_PURCHASING` |
| `UpgradeService::hatBestand()` | `hasData()` |
| `AuthController::anmelden()` (private) | `signIn()` |
| `AccountController::konto()`, `passwortOk()` | `account()`, `passwordOk()` |
| `LegalController::impressum()`, `datenschutz()`, `zeigen()` | `imprint()`, `privacy()`, `show()` |
| `HelpController::DOCS` keys `benutzer`, `architektur`, `sicherheit`, `ueberblick` | `user`, `architecture`, `security`, `overview` (+ `LEGACY_DOCS`) |
| — (literal log types) | `ActivityLog::TYPE_CASE` (`vorgang`), `TYPE_FIELD` (`feld`), `TYPE_STEP` (`schritt`), `TYPE_MAIL` (`mail`), `TYPE_DELETION` (`löschung`), `TYPE_SETTINGS` (`einstellungen`), `TYPE_USER` (`benutzer`), `TYPE_SIGN_IN` (`anmeldung`), `TYPE_EXPORT` (`export`) — **values unchanged** (hash chain), display names in `ActivityLog::LABELS` |
| — | `Process::ONBOARDING`, `JOB_CHANGE`, `EXIT`, `MASTER_DATA_CHANGE`, `LEGACY_KEYS`, `currentKey()`, `legacyKey()` |
| — | `CustomField::STAGE_INTAKE`, `STAGE_PREPARATION`, `CHECKBOX_YES`, `CHECKBOX_NO` |
| — | `MailLog::STATUS_SENT`, `STATUS_FAILED`, `statusLabel()` |
| — | `MailService::PLACEHOLDER_ALIASES` |
| local variables (`$eintrag`, `$gruppen`, `$richtlinie`, `$passwort`, `$wert`, `$datei`, `$pfad`, `$ziel`, …) | English equivalents (`$entry`, `$groups`, `$policy`, `$password`, `$value`, `$file`, `$path`, `$target`, …) |

## Database: columns and stored values

| old | new |
|---|---|
| column `users.fuchs` | `users.fox` |
| `users.mfa_policy` `pflicht` / `aus` | `required` / `off` (`optional`, NULL unchanged) |
| `processes.key`, `onboardings.process`, `custom_fields.processes` (JSON) `wechsel`, `austritt`, `stammdaten` | `job_change`, `exit`, `master_data_change` (`onboarding` and custom keys unchanged) |
| `custom_fields.fill_stage` `anlage` / `vorbereitung` | `intake` / `preparation` |
| `onboarding_values.value` of checkbox fields `ja` / `nein` | `yes` / `no` (shown as „ja“/„nein“) |
| `mail_logs.status` `gesendet` / `fehlgeschlagen` | `sent` / `failed` |
| `activity_logs` (type, message, details) | **unchanged** (hash chain) |

The column defaults of the old migrations (`fill_stage` default `anlage`,
`processes` default with old keys) are not altered (that would rebuild the
SQLite table); the code always writes these columns explicitly.

## Settings keys (table `settings`)

| old | new |
|---|---|
| `fuchs_aktiv` | `fox_enabled` |
| `ldap_aktiv`, `ldap_verschluesselung`, `ldap_bind_passwort`, `ldap_bind_vorlage` | `ldap_enabled`, `ldap_encryption`, `ldap_bind_password`, `ldap_bind_template` |
| `ldap_benutzer_attribut`, `ldap_benutzer_filter`, `ldap_zertifikat` | `ldap_user_attribute`, `ldap_user_filter`, `ldap_certificate` |
| `mfa_gruppen_pflicht`, `mfa_abteilung`, `mfa_sonst` | `mfa_required_groups`, `mfa_department`, `mfa_other` |
| values of `mfa_<group>` `pflicht` / `aus` | `required` / `off` |
| `impressum_aktiv`, `_art`, `_text`, `_link` | `imprint_enabled`, `_type`, `_text`, `_url` |
| `datenschutz_aktiv`, `_art`, `_text`, `_link` | `privacy_enabled`, `_type`, `_text`, `_url` |
| placeholders in `mail_tpl_*` `{vorgesetzter}`, `{prozess}`, `{abteilung}`, `{eintritt}`, `{rolle}`, `{daten}`, `{frist}`, `{aufgaben}`, `{vorname}`, `{nachname}` | `{supervisor}`, `{process}`, `{department}`, `{date}`, `{role}`, `{data}`, `{due_date}`, `{tasks}`, `{first_name}`, `{last_name}` (old placeholders keep working) |

Form fields of the settings page use the same new names.

## Session, cache, browser storage

| old | new |
|---|---|
| `session('ldap_user')` keys `quelle`, `mfa_offen` | `source`, `mfa_pending` (key `ldap_user` unchanged; old sessions are signed out once) |
| `session('login.2fa')` keys `bis`, `gruppen`, `richtlinie` | `until`, `groups`, `policy` |
| flash `zugang` | `credentials` |
| rate limiter `anmeldung:<name>` | `sign-in:<name>` |
| localStorage `cg-fuchs-tiefschlaf`, `cg-fuchs-zuletzt`, `cg-fuchs-lage` | `cg-fox-sleep`, `cg-fox-last`, `cg-fox-position` (old values are taken over once and deleted) |
| log context `referenz` | `reference` |
| schedule name `sicherung-nachts` | `nightly-backup` |

## Addresses (GET redirects 301, query kept)

| old | new | route name old → new |
|---|---|---|
| `/sprache/{locale}` | `/locale/{locale}` | `locale.switch` |
| `/impressum`, `/datenschutz` | `/imprint`, `/privacy` | `impressum`, `datenschutz` → `imprint`, `privacy` |
| `/hilfe` | `/help` | `hilfe` → `help` |
| `/hilfe/handbuch/{doc}` (`benutzer`, `architektur`, `sicherheit`, `ueberblick`) | `/help/manual/{doc}` (`user`, `architecture`, `security`, `overview`) | `hilfe.doc` → `help.doc` |
| `/hilfe/protokoll` (DELETE) | `/help/log` | `hilfe.protokoll` → `help.clear-log` |
| `/konto` | `/account` | `konto` → `account` |
| `/konto/passwort`, `/konto/fuchs` | `/account/password`, `/account/fox` | `konto.passwort`, `konto.fuchs` → `account.password`, `account.fox` |
| `/konto/2fa`, `/konto/2fa/bestaetigen`, `/konto/2fa/codes` | `/account/2fa`, `/account/2fa/confirm`, `/account/2fa/codes` | `konto.2fa.start/bestaetigen/codes/aus` → `account.2fa.start/confirm/codes/disable` |
| `/onboardings-neu` | `/onboardings-new` | |
| `/onboardings/{id}/rollen/{role}/complete`, `…/rollen/{role}/quittieren` | `…/roles/{role}/complete`, `…/roles/{role}/acknowledge` | |
| `/onboardings/{id}/entwurf`, `absenden`, `abbrechen`, `abschliessen` | `…/draft`, `submit`, `cancel`, `close` | |
| `/onboardings-import/zeile`, `zeile-verwerfen`, `zuruecksetzen`, `vorlage` | `…/row`, `discard-row`, `reset`, `template` | |
| `/prozesse`, `/prozesse/{id}` | `/processes`, `/processes/{id}` | |
| `/protokoll`, `/protokoll/export`, `/protokoll/export-pdf` | `/audit-log`, `/audit-log/export`, `/audit-log/export-pdf` | |
| `/verwaltung/felder`, `/verwaltung/produkte`, `/verwaltung/kategorien`, `…/produkte/bereinigen` | `/manage/fields`, `/manage/products`, `/manage/categories`, `…/products/purge` | |
| `/admin/rollen`, `/admin/benutzer`, `/admin/benutzer/{id}/passwort` | `/admin/roles`, `/admin/users`, `/admin/users/{id}/password` | |
| `/admin/einstellungen`, `…/verzeichnis-testen`, `/admin/sicherung/jetzt` | `/admin/settings`, `…/test-directory`, `/admin/backup/now` | |
| API `/api/v2/vorgaenge`, `/api/v2/vorgaenge/{id}` | `/api/v2/cases`, `/api/v2/cases/{id}` — **old address stays as an equal alias** (no redirect, POST works) | |

Query parameters (old names are still read): `?typ=` → `?type=` (log filter),
`?suche=` → `?search=`, `?bearbeiten=` → `?edit=` (user list).

## API v2 values

| old | new |
|---|---|
| `process` values `wechsel`, `austritt`, `stammdaten` | `job_change`, `exit`, `master_data_change`; old values are still accepted in `POST` and `?process=` |
| — | `process_legacy` (old key, this version only) |

## Views, sections, CSS, JS

| old | new |
|---|---|
| Blade sections of error pages `bild`, `titel`, `tipps`, `neu_laden` | `symbol`, `title`, `tips`, `reload` |
| `@include` parameters `angemeldet`, `nummer`, `fuchsPrefix` | `signedIn`, `number`, `foxPrefix` |
| CSS `.fuchs`, `.fuchs-blase`, `-blase-knoepfe`, `-figur`, `-text`, `-link`, `-weiter`, `-zu`, `-schlafen` | `.fox`, `.fox-bubble`, `-bubble-buttons`, `-figure`, `-text`, `-link`, `-next`, `-close`, `-sleep` |
| fox states `.da`, `.spricht`, `.winkt`, `.huepft`, `.schlaeft`, `.tiefschlaf`, `.im-anmeldung`, `.im-seite` | `.shown`, `.speaking`, `.waving`, `.hopping`, `.asleep`, `.deep-sleep`, `.on-login`, `.on-page` |
| fox drawing `.f-schwanz`, `-koerper`, `-pfote`, `-kopf`, `-ohr-l/-r`, `-augen`, `-schlafaugen`, `-mund`, `-umhang` | `.f-tail`, `-body`, `-paw`, `-head`, `-ear-l/-r`, `-eyes`, `-sleep-eyes`, `-mouth`, `-cloak` |
| keyframes `f-wedeln`, `f-atmen`, `f-kopf`, `f-blinzeln`, `f-ohr`, `f-winken`, `f-huepf`, `f-schlafatmen`, `blase-rein` | `f-wag`, `f-breathe`, `f-head`, `f-blink`, `f-ear`, `f-wave`, `f-hop`, `f-sleep-breathe`, `bubble-in` |
| `id="fuchs"`, `data-fuchs` | `id="fox"`, `data-fox` |
| fox data keys `gruss`, `kontext`, `tipps`, `sprueche`, `lob`, `buch`, `texte` (`schliessen`, `schlafen`, `wecken`, `aufgewacht`, `weiter`, `fuchs`) | `greeting`, `context`, `tips`, `sayings`, `praise`, `book`, `texts` (`close`, `sleep`, `wake`, `woken`, `next`, `fox`) |
| `data-ohne-fuchs` (form) | `data-no-fox` |
| `.fehlerseite`, `.fehler-bild`, `-code`, `-grund`, `-logo`, `-referenz`, `-text`, `-tipps` | `.error-page`, `.error-symbol`, `-code`, `-reason`, `-logo`, `-reference`, `-text`, `-tips` |
| `.rechtliches`, `.rechtliches-einstellung`, `fieldset.rechtlich`, `.rechtlich-wahl`, `-text`, `-link`, `.login-rechtliches` | `.legal-page`, `.legal-settings`, `fieldset.legal`, `.legal-choice`, `-text`, `-url`, `.login-legal` |
| `.macher`, `.btn.zweit`, `.hilfe-liste`, `.logzeile`, `form.suchleiste` | `.maker`, `.btn.secondary`, `.help-list`, `.log-line`, `form.search-bar` |
| symbols `.bw-emoji`, `.bw-symbol`, `.bw-geraet-emoji`, `.keine-symbole`, `.luft-links`, `.luft-rechts` | `.emoji-image`, `.emoji-symbol`, `.device-emoji`, `.no-symbols`, `.space-left`, `.space-right` |
| `<meta name="symbol-basis">`, `window.BWSymbole.tauschen()` | `<meta name="symbol-base">` (old names still read), `window.Symbols.swap()` |
| `data-wechsel`, `data-austritt`, `data-stammdaten` (date label) | `data-job-change`, `data-exit`, `data-master-data-change` |
| JS names in `fox.js`/`symbols.js` (`speicher`, `sagen`, `schweigen`, `wecken`, `hinlegen`, `tauschen`, …) | `store`, `say`, `hush`, `wake`, `putToSleep`, `swap`, … |

## Commands and tools

| old | new |
|---|---|
| `php artisan crewgate:admin-passwort {benutzername}` | `crewgate:admin-password {username}` (old name is an alias) |
| `php artisan verzeichnis:extern` | `directory:isolated` (internal, no alias) |
| GitHub workflow input `veroeffentlichen`, job `abbild` | `publish`, `image` |
| entrypoint function `rechte` | `permissions` |

## What stays German on purpose

- UI strings (`__('…')` keys, `lang/en.json` keys, placeholders like
  `:fehler` inside them), manuals in `docs/`, CHANGELOG entries, the German
  half of comments.
- Audit log types and texts (`vorgang`, `löschung`, …) — hash chain.
- Demo and test data (department names, user names, field labels, the German
  OU/group names of the AD test fixture).
- Column names of the CSV import template (`vorname;nachname;prozess;…`) —
  a data format for German HR staff; the English names (`first_name`,
  `last_name`, `process`, `start_date`, `supervisor`, `supervisor_due_date`,
  `roles_due_date`, `notes`) are accepted as well.
- Keys of custom process types created by HR (slug of their German name,
  e.g. `azubi-uebernahme`).
- Legacy aliases listed above (old URLs, API path, command name, placeholders,
  old keys in the migration).
