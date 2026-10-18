# Mitmachen / Contributing

Schön, dass du helfen möchtest! *(English below.)*

## Grundsätze

1. **Need-to-know.** Jede Abteilung sieht nur, was sie braucht — in der
   Oberfläche, in E-Mails und in Exporten. Neue Daten laufen immer durch
   dieselbe Sichtbarkeitsprüfung (`MailService::dataTextForRole`,
   Rollen-Freigaben „Sieht Daten von“).
2. **Rechte serverseitig.** Jede Aktion prüft Rolle und Status im Controller
   bzw. in der Middleware; ausgeblendete Knöpfe sind kein Schutz.
3. **Alles ins Protokoll.** Was Daten ändert, verschickt oder löscht, erzeugt
   einen Eintrag im append-only-Protokoll mit Hash-Kette.
4. **Daten bleiben im Haus.** Keine externen Dienste, CDNs, Schriften,
   Analyse- oder Fehlerdienste.
5. **Kein Build-Schritt.** Kein Node und kein npm: CSS liegt direkt in
   `public/css/app.css`.
6. **Jeder Text übersetzbar.** In PHP und Blade `__('Deutscher Text')`.
   Danach `lang/en.json`
   ergänzen.
7. **Updates ohne Datenverlust.** Schemaänderungen nur als neue Migration;
   Datenumbauten als Schritt in `UpgradeService`.

## Ablauf

1. Issue anlegen oder kommentieren, was du vorhast.
2. Fork, Branch, Änderung.
3. Lokal testen:
   ```bash
   docker build -t crewgate:dev .
   docker run --rm -p 7101:80 -e DEMO_MODE=true -e APP_DEBUG=true crewgate:dev
   ```
   Im Demo-Modus mit den Konten aus der README den Ablauf einmal komplett
   durchspielen (Anlage → Vorbereitung → Quittieren → Abschluss).
4. Pull Request mit kurzer Beschreibung und, bei Oberflächenänderungen, einem
   Bildschirmfoto (hell und dunkel).

Bitte keine echten Personal- oder AD-Daten in Issues, Bildschirmfotos oder
Testdaten verwenden.

### Veröffentlichen

Versionen veröffentlichen die Betreuer. Trag deine Änderung bitte im
`CHANGELOG.md` unter „## [Unveröffentlicht]“ ein; Nummer, Git-Tag und
Docker-Abbild kommen beim Veröffentlichen dazu. Vorher prüfen die Betreuer
Übersetzungen, Doku und Sicherheit mit eigenen Werkzeugen.

Es gilt der [Verhaltenskodex](CODE_OF_CONDUCT.md).

Mit einem Beitrag stimmst du zu, dass er unter der AGPL-3.0-or-later
veröffentlicht wird.

## Englische Namen, Kommentare zweisprachig / English names, bilingual comments

**Deutsch.** Alle Bezeichner im Code sind englisch (Standard 10): Klassen,
Methoden, Variablen, Routen und Routennamen, Ansichten, CSS-Klassen,
JS-Namen, Datenbankspalten, gespeicherte Werte, Einstellungsschlüssel,
Artisan-Befehle, Dateien und Ordner. Deutsch bleiben die Oberflächentexte
(deutsche Übersetzungsschlüssel in `__('…')`, Englisch in `lang/en.json`),
die Handbücher unter `docs/` und die deutsche Hälfte der Kommentare.

- **Jeder Kommentar steht auf Deutsch und Englisch**: erst Deutsch, dann
  Englisch; in Docblocks durch eine Leerzeile getrennt, bei Zeilenkommentaren
  als zweite Zeile, am Zeilenende als `// Deutsch / English`.
- Begriffe einheitlich: Vorgang = case, Prozess(art) = process, Einstellung
  (Prozessart) = onboarding, Stellenwechsel = job change, Austritt = exit,
  Stammdatenänderung = master data change, Anlage = intake, Vorgesetzter =
  supervisor, Abteilung = department, quittieren = acknowledge, Protokoll =
  audit log, Sicherung = backup, Verzeichnis = directory, Fuchs-Helfer = fox.
- Die Einträge im Protokoll (`activity_logs`) bilden eine Hash-Kette und
  behalten ihre gespeicherten (deutschen) Typen; die Konstanten heißen
  englisch (`ActivityLog::TYPE_CASE = 'vorgang'` …).
- Wer einen alten Fork oder eine alte Sicherung hat: Die Tabelle aller
  Umbenennungen (alt → neu) steht in [docs/RENAMING.md](docs/RENAMING.md).
  Die Migration `rename_to_english` stellt bestehende Datenbanken beim Start
  automatisch um; alte Adressen leiten dauerhaft um (`routes/legacy.php`).

**English.** All identifiers in the code are English (standard 10): classes,
methods, variables, routes and route names, views, CSS classes, JS names,
database columns, stored values, settings keys, artisan commands, files and
folders. German remains for UI strings (German translation keys in
`__('…')`, English in `lang/en.json`), the manuals under `docs/` and the
German half of the comments.

- **Every comment is written in German and English**: German first, then
  English; in docblocks separated by a blank line, for line comments as a
  second line, at the end of a code line as `// Deutsch / English`.
- Consistent terms: Vorgang = case, Prozess(art) = process, Einstellung
  (process type) = onboarding, Stellenwechsel = job change, Austritt = exit,
  Stammdatenänderung = master data change, Anlage = intake, Vorgesetzter =
  supervisor, Abteilung = department, quittieren = acknowledge, Protokoll =
  audit log, Sicherung = backup, Verzeichnis = directory, Fuchs-Helfer = fox.
- Audit log entries (`activity_logs`) form a hash chain and keep their stored
  (German) types; the constants have English names
  (`ActivityLog::TYPE_CASE = 'vorgang'` …).
- If you have an old fork or an old backup: the table of all renames
  (old → new) is in [docs/RENAMING.md](docs/RENAMING.md). The migration
  `rename_to_english` converts existing databases automatically at start-up;
  old addresses redirect permanently (`routes/legacy.php`).

## English

- Need-to-know: every department only sees what it needs — in the UI, in
  e-mails and in exports.
- Enforce permissions on the server; hidden buttons are not protection.
- Everything that changes, sends or deletes data is written to the
  append-only, hash-chained audit log.
- No third-party services, CDNs or web fonts.
- No build step (no Node/npm).
- All UI strings must be translatable with `__('German text')`. German
  strings are the keys. Update
  `lang/en.json`.
- Schema changes only as new migrations; data conversions as a step in
  `UpgradeService`.
- Never put real personnel or directory data in issues, screenshots or test
  data.

The [Code of Conduct](CODE_OF_CONDUCT.md) applies.

By contributing you agree that your contribution is licensed under the
AGPL-3.0-or-later.
