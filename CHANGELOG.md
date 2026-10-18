# CrewGate – Changelog

Format angelehnt an „Keep a Changelog“. Versionsnummern im Schema
**JJMMTTNN**: Datum plus laufende Nummer des Tages (26101701 = erste
Version am 17.10.2026). Neue Einträge kommen unter „Unveröffentlicht“;
`tools/release.sh` macht daraus die nächste Version. Ältere Stände
tragen die Version 1.0 mit Build-Zeitstempel (JJMMTTHHMM).

## [Unveröffentlicht]

## [26101801] — 2026-10-18

### Neu

- Rua lässt sich verschieben: mit Maus oder Finger an jede Stelle ziehen, der Browser merkt sich die Lage (auch über Seitenwechsel und Größenänderungen). Ein kurzer Tipp befragt ihn weiter; die Sprechblase klappt zur freien Seite.
- Rua lobt passend zum Anlass: nach Löschen, Passwort, Zwei-Faktor, Token, Sicherung, Abbrechen oder Abmelden nur schlicht „Erledigt.“, sonst wie bisher mit Bild. Erkannt wird der Anlass von einer neuen Middleware (`FoxOccasion`) an Methode und Route der abgeschickten Aktion.
- Rua hilft bei Fehlern und beim Warten: Kommt eine Seite mit einer Fehlermeldung zurück, sagt er „Gegenwind. Versuch es nochmal, ich bleib an Deck.“ oder „Da fehlt noch etwas. Schau dir das markierte Feld an.“ (auf Anmelde-, Konto-, Passwort- und Zwei-Faktor-Seiten ohne Bild); dauert das Absenden eines Formulars länger als 0,7 s, sagt er „Die Ruder sind im Wasser …“. Formulare mit `data-no-fox` lässt er in Ruhe.

### Geändert

- Repository nach den Gepflogenheiten offener Projekte: Verhaltenskodex (`CODE_OF_CONDUCT.md`), Vorlagen für Fehlermeldungen, Vorschläge und Pull Requests; Sicherheitslücken lassen sich direkt vertraulich melden. Die Werkzeuge der Betreuer (Release, Tests, Prüfungen) liegen nicht mehr im öffentlichen Repo.
- Architektur, Sicherheit, Admin-Handbuch (Installation „Docker mit Compose“ / „Container-Oberfläche“) plattformneutral: Docker mit Compose als Grundlage, Container-Oberflächen (z. B. auf NAS-Systemen) nur noch als Beispiel; Versionsangaben auf PHP 8.4 / Laravel 13 korrigiert, veralteter Vermerk „Version 1.0“ entfernt.
- **Englische Namen im Code (Standard 10):** Klassen, Methoden, Variablen, Routen, Ansichten, CSS-/JS-Namen, Datenbankspalten, gespeicherte Werte, Einstellungsschlüssel, Befehle und Dateien heißen jetzt englisch; die Oberfläche bleibt unverändert deutsch und englisch. Die vollständige Liste alt → neu steht in `docs/RENAMING.md`.
  - **Automatische Umstellung:** Die neue Migration `rename_to_english` stellt beim ersten Start die Datenbank um (Spalte `users.fuchs` → `users.fox`, Einstellungsschlüssel wie `ldap_aktiv` → `ldap_enabled`, Zwei-Faktor-Stufen `pflicht`/`aus` → `required`/`off`, Prozessarten `wechsel`/`austritt`/`stammdaten` → `job_change`/`exit`/`master_data_change`, Feldstufen `anlage`/`vorbereitung` → `intake`/`preparation`, Checkbox-Werte `ja`/`nein` → `yes`/`no`, Mail-Status, Platzhalter in eigenen Mail-Vorlagen wie `{vorgesetzter}` → `{supervisor}`). Sie ist idempotent: auch eine alte Sicherung wird beim nächsten Start nachgezogen. `app:upgrade` legt vorher wie immer eine Sicherung an. Das Protokoll mit seiner Hash-Kette bleibt unverändert.
  - **Neue Adressen, alte leiten um:** z. B. `/hilfe` → `/help`, `/konto` → `/account`, `/admin/einstellungen` → `/admin/settings`, `/protokoll` → `/audit-log`, `/verwaltung/felder` → `/manage/fields`, `/impressum` → `/imprint`, `/datenschutz` → `/privacy`, Handbücher `/hilfe/handbuch/benutzer` → `/help/manual/user`. Alle alten Seitenadressen leiten dauerhaft (301) um, die Abfrage bleibt erhalten — Lesezeichen und Links in Mails gelten weiter.
  - **API v2:** neue Adresse `/api/v2/cases`; die alte Adresse `/api/v2/vorgaenge` bleibt gleichwertig erhalten (auch `POST`). Prozess-Schlüssel sind englisch (`job_change`, `exit`, `master_data_change`), alte Schlüssel werden weiter angenommen; die Antwort enthält in dieser Version zusätzlich `process_legacy` mit dem alten Schlüssel.
  - **Einmal neu anmelden:** Die Daten der Sitzung haben neue Namen; nach dem Update meldet sich jeder einmal neu an.
  - **Befehle:** `crewgate:admin-passwort` heißt jetzt `crewgate:admin-password` (der alte Name gilt weiter). Sicherungsdateien heißen `crewgate_backup_…zip` statt `crewgate_sicherung_…zip`, der CSV-Export `audit-log-…csv`, der PDF-Bericht `audit-report-…pdf`.
  - Der Fuchs merkt sich „schlafen“ und seine Lage jetzt unter `cg-fox-sleep` und `cg-fox-position` (alte Werte im Browser werden übernommen).
- Anleitungen: Abschnitt zu einem Tunnel-Dienst entfernt; die Hilfe bei „Zu viele Weiterleitungen“ ist jetzt allgemein für vorgeschaltete Proxys formuliert.
- **Der Fuchs-Helfer heißt jetzt Rua**, der Wikinger-Fuchs aus einem norwegischen Fjorddorf (den Namen gaben ihm die Iren: „der Rote“). Alle seine Texte sind neu geschrieben und duzen jetzt (bisher „Sie“; die übrige Oberfläche bleibt beim „Sie“), deutsch und englisch. Neue Zeichnung mit Umhang, Fibel, Runen-Amulett und schiefem Ohr.

### Behoben

- Rua hat keinen grauen Kasten mehr hinter sich: allgemeine Knopf-Regeln (z. B. im Dunkelmodus) gaben der Figur eine Fläche; jetzt hat sie ausdrücklich keine.
- **Fuchs reagiert wieder auf Klicks und Fingertipps:** `.fuchs-figur { all: unset }` setzte auch `pointer-events` zurück, die Figur erbte `none` vom Rahmen – echte Klicks gingen durch den Fuchs hindurch (Skript-Klicks per `.click()` zeigten den Fehler nicht). `pointer-events: auto` steht jetzt direkt dahinter.

## [26101702] — 2026-10-17

### Neu
- **Zwei-Faktor-Anmeldung (TOTP)** mit Authenticator-App — je Gruppe als Pflicht, wählbar oder aus (Einstellungen → 3 · Anmeldung): Administratoren, HR, Auditoren, Vorgänge anlegen, Abteilungen, alle übrigen; zusätzlich Verzeichnisgruppen mit Pflicht und Ausnahmen je Benutzer (Benutzer → Bearbeiten). Einrichtung mit QR-Code und Wiederherstellungscodes unter **Mein Konto**; Administratoren setzen den zweiten Faktor bei verlorenem Handy zurück.
- Neue Seite **Mein Konto** (Name oben rechts): Passwort ändern (eigene Konten), Zwei-Faktor, Fuchs-Helfer.
- **Hilfe** nach dem gemeinsamen Standard (`/hilfe`, alte Adresse `/support` leitet um): Kurzanleitung je Rolle, System mit Anmeldeart und eigenem Konto, Lizenz, Handbücher als formatierte Seiten, Änderungshistorie, letzte Fehler mit „Protokoll leeren“, Support.
- **Anmeldung mit und ohne Active Directory** (Standard 09): CrewGate läuft jetzt vollständig mit eigenen Konten. Neuer Menüpunkt **Benutzer** für Administratoren: eigene Konten anlegen, Rechte (HR, Vorgänge anlegen, Admin, Auditor) und Abteilungen vergeben, Passwort zurücksetzen (Startpasswort wird einmal angezeigt), Konten sperren. Verzeichniskonten erscheinen in derselben Liste mit Herkunft „Verzeichnis“.
- **Einstellungen → 3 · Anmeldung**: Verzeichnis (Active Directory, Samba AD, OpenLDAP, Univention) in der Oberfläche einrichten — Server, Port, LDAPS/STARTTLS, Zertifikatsprüfung, Suchbasis, Servicekonto (Passwort verschlüsselt gespeichert), Benutzerattribut und -filter, Vorlage ohne Servicekonto — mit Knopf **„Verbindung testen“**. Gruppen dürfen als DN oder nur als Gruppenname eingetragen werden; verschachtelte AD-Gruppen werden aufgelöst.
- Erster Administrator als eigenes Konto beim ersten Start (`ADMIN_USERNAME`, `ADMIN_PASSWORD`; leer = zufälliges Startpasswort im Container-Protokoll). Notzugang: `php artisan crewgate:admin-passwort`.
- **Fuchs-Helfer** wie in BilDaBooky: begrüßt, gibt Tipps passend zu Rolle und Seite, wirbt auf der Anmeldeseite für freie Software. Zentral abschaltbar (Einstellungen → 10), je Person unter **Hilfe**.
- Einheitliche Symbole: Emojis der Oberfläche werden als Twemoji-Grafiken gezeichnet und sehen auf jedem Gerät gleich aus (`werkzeuge/symbole.py` sammelt die benötigten Grafiken ein).
- Handbücher unter **Hilfe** werden als formatierte Seiten angezeigt (Markdown) statt als Rohtext; Hilfe-Seite mit Kurzanleitung, PHP/Laravel-Version, Anmeldeart und Links auf Quellcode und Projektseite.
- Nächtliche Sicherung um 02:30 Uhr: ZIP mit Datenbank und Uploads samt SHA-256-Prüfsumme im Ordner `./backups`, Aufbewahrung `BACKUP_KEEP_DAYS` (Standard 14, mindestens 4 Tage). „Jetzt sichern“ unter **Einstellungen** legt zusätzlich eine Sicherung in `./backups/manual` ab, die nie automatisch gelöscht wird.
- Impressum und Datenschutz: unter **Einstellungen → 10** einzeln einschaltbar, als eigener Text oder Link; ohne Anmeldung erreichbar (`/impressum`, `/datenschutz`), verlinkt auf der Anmeldeseite und im Footer. Mit Vorlage für die Datenschutzhinweise.
- Eigene Fehlerseiten (403, 404, 419, 429, 500, 503) im Erscheinungsbild der Anwendung.
- Footer nach dem gemeinsamen Standard: Produkt, Edition, Version, „freie Software (AGPL-3.0)“, Hilfe, Impressum, Datenschutz und die Zeile „Steffen Mai · www.mai-digital.de“.

### Geändert
- Die Demo-Konten (anna.hr, max.admin, … / Passwort `demo`) sind jetzt eigene Konten in der Datenbank; das fest verdrahtete Demo-Verzeichnis entfällt.
- LdapRecord wird nicht mehr benötigt; die Anbindung läuft über die PHP-Erweiterung `ldap` (`App\Support\Verzeichnis`). Bestehende Installationen mit `LDAP_*`-Variablen in der Compose-Datei laufen unverändert weiter; die Werte lassen sich danach in den Einstellungen pflegen.
- `.env.example` und `docker-compose.yml`: LDAP-Werte sind nur noch auskommentierte Beispiele; neu `ADMIN_USERNAME`/`ADMIN_PASSWORD`.
- Einstellungen vollständig übersetzt (Englisch); Kopfzeile markiert den aktiven Menüpunkt, Marke verlinkt auf die Übersicht; Knöpfe und Menüpunkte mindestens 44 px hoch (Standard 03).
- Nummerierung der Einstellungen: 3 Anmeldung, 4 Berechtigungen, 10 Fuchs-Helfer, 11 Impressum und Datenschutz.
- **Vor dem Update im Projektordner den Ordner `backups` anlegen** (`mkdir -p backups`, Synology: File Station). Er ist neu als Bind-Mount eingebunden; ohne ihn startet das Projekt nicht.
- `APP_KEY` wird beim ersten Start erzeugt und in `/data/.app_key` abgelegt. Ein in der Compose-Datei gesetzter Schlüssel gilt weiter. Ohne gesetzten Schlüssel müssen sich alle Benutzer nach diesem Update einmal neu anmelden.
- `TRUSTED_PROXIES` steht standardmäßig auf den internen Netzen statt `*`. Läuft der Reverse Proxy außerhalb dieser Netze, seine Adresse eintragen.
- PHP 8.4 und Laravel 13 (LdapRecord 3.4, Dompdf 3.1).
- „Support & Lizenz“ heißt jetzt **Hilfe**.
- Oberfläche durchgehend mit „Sie“.
- Der Scheduler läuft als `www-data`; App und Scheduler nutzen denselben Schlüssel.

### Behoben
- „Neues Token generieren“ unter Einstellungen → API führte zu einem Fehler (fehlende Aktion); erzeugt jetzt ein 48-stelliges Token.
- Docker-Bau: Leserechte der Dateien werden im Abbild gesetzt — auch wenn der Bauhost die Dateien mit Modus 600 ablegt (sonst „Forbidden“ wegen unlesbarer `.htaccess`).

### Sicherheit
- Sicherheits-Header für jede Antwort: Content-Security-Policy, `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, hinter HTTPS HSTS und sichere Cookies.
- Bremse gegen Passwort-Raten: 10 Versuche je Minute und Adresse; nach 10 Fehlversuchen ist ein Benutzername 30 Minuten gesperrt.

### Dokumentation
- Anleitungen neu geschrieben und nach `installation/` verschoben: `docker.md`, `synology.md`, `reverse-proxy.md`, `betrieb.md`, englisch unter `installation/en/`.

## [26101701] — 2026-10-17

### Geändert
- CrewGate ist Open Source: Lizenz AGPL-3.0-or-later (`LICENSE`), dazu `CONTRIBUTING.md`, `SECURITY.md` und eine englische README (`README.en.md`).
- Neues Versionsschema JJMMTTNN statt „1.0 + Build“. Das Update von 1.0 läuft automatisch (der Versionsabgleich erkennt die neue Nummer als neuer).
- Docker-Abbild heißt jetzt `crewgate:latest` bzw. `crewgate:<version>`, die Container `crewgate` und `crewgate-scheduler` (vorher `hr-onboarding`). Die Daten in den Volumes bleiben erhalten, solange der Projektname im Container Manager gleich bleibt. Befehle lauten jetzt `docker exec crewgate …`.
- Abbild trägt Version und Lizenz als Kennzeichnung; GitHub baut es bei jedem Versions-Tag für amd64 und arm64 (`.github/workflows/docker.yml`).

### Dokumentation
- README neu gegliedert (Funktionen, Rollen, Schnellstart, Einstellungen, Betrieb, Versionen, Entwicklung).
- Anleitungen nach `docs/` verschoben (`ANLEITUNG-SYNOLOGY.md`, `ANLEITUNG-REVERSE-PROXY.md`), neu: `ANLEITUNG-DOCKER.md` und `ANLEITUNG-BETRIEB.md`, englische Fassungen unter `docs/en/`.
- Installation per `git clone` / ZIP von GitHub statt der früheren Pakete und `install.sh`.

### Werkzeuge
- `werkzeuge/version.py` und `werkzeuge/release.sh` für Versionen und Veröffentlichung, `werkzeuge/sprachen.py` prüft die Übersetzungen.

## 1.0 – Build 2607172243 (17.07.2026)
- Fehler 500 auf der Vorgangsseite behoben („Call to a member function
  isPast() on string"): die Fristen-Spalten waren nicht als Datum gecastet –
  betroffen waren alle Vorgänge mit gesetzter Frist (auch Erinnerungs-Job)
- Support & Lizenz: neues Panel „Dokumentation" zeigt die Handbücher direkt
  in der Anwendung (Benutzerhandbuch und Überblick für alle; Admin-Handbuch,
  API, Architektur und Sicherheit nur für Administratoren)
- Übersetzungen geprüft und vervollständigt (396 Einträge, 0 fehlend)
- Paketierung: es wird nur noch das Synology-Paket ausgeliefert

## 1.0 – Build 2607171753 (17.07.2026)
- Synology-Port von 7170 auf **7101** geändert (Compose-Portmapping, APP_URL
  und alle Anleitungen). Beim Update Projekt neu erstellen; Reverse-Proxy-
  Weiterleitung und Firewall-Regeln auf den neuen Port anpassen

## 1.0 – Build 2607171748 (17.07.2026)
- Support & Lizenz: neues Panel „Letzte Fehler" (nur Administratoren) zeigt
  die letzten Fehlereinträge aus dem Anwendungslog direkt im Browser –
  500er lassen sich damit ohne Terminal diagnostizieren

## 1.0 – Build 2607171320 (17.07.2026)
- Scheduler-Absturzschleife behoben („bootstrap/cache directory must be
  present and writable"): bootstrap/cache gehört jetzt schon im Image
  www-data, und der Scheduler-Zweig des Entrypoints stellt Ordner und
  Rechte als root sicher, bevor er die Privilegien auf www-data absenkt
  (bootstrap/cache ist kein Volume – das chown des App-Containers half dem
  Scheduler-Container daher nicht)

## 1.0 – Build 2607171117 (17.07.2026)
- Stabilität: Scheduler-Container wird jetzt am Kommando erkannt und führt
  nie mehr Setup/Migration aus – auch wenn SKIP_SETUP in der Compose fehlt
  (behebt Absturz-Schleifen durch SQLite-Sperrkonflikte); runuser mit
  Fallback (setpriv), Notfall-Reset FRESH_DB=1 wie bei FairShare
- Dokumentation strukturell an FairShare angeglichen: Ordner docs/ mit
  DOKUMENTATION.md (Module, Abläufe, Update-Verfahren), BENUTZERHANDBUCH.md,
  ADMIN-HANDBUCH.md, API.md (ausgegliedert), ARCHITEKTUR.md, SICHERHEIT.md;
  README verweist auf die neue Struktur

## 1.0 – Build 2607170027 (17.07.2026)
- Workflow-Grafik zeigt jetzt die vollständige Kette: Anlage (HR) →
  Vorgesetzter (Vorbereitung) → Abteilungen → Abschluss (HR). Der aktuell
  anstehende Schritt ist blau hervorgehoben, erledigte Schritte tragen den
  Haken, Tooltips nennen Details (Anlagedatum, Vorgesetzter, Quittierungen);
  bei abgebrochenen Vorgängen werden offene Schritte ausgegraut

## 1.0 – Build 2607170019 (17.07.2026)
- CSV-Import als geführter Assistent: Schritt 1 aktuelle Vorlage samt
  Spalten-Anleitung, Schritt 2 Upload mit Prüfung (ohne Anlage), Schritt 3
  jede Zeile als korrigierbare Karte mit allen Feldern, Vorgesetzten-Auswahl
  per AD-Suche und Einzel-Anlage je Zeile bis alles abgearbeitet ist;
  Fortschrittsanzeige, Zeilen verwerfen, Import abbrechen
- Vorlage enthält jetzt Beispiel-Zeilen mit den tatsächlich vorhandenen
  Prozessarten

## 1.0 – Build 2607170009 (17.07.2026)
- Vorgangsseite: neues Panel „Aktionen (HR)" macht nach der Anlage klar
  sichtbar, dass HR die Angaben selbst pflegen, den Vorgang abbrechen oder
  löschen kann (mit erklärenden Hinweisen)
- Abbruch informiert den Vorgesetzten jetzt automatisch per zweisprachiger
  E-Mail; Vorlage (DE/EN) in den Einstellungen anpassbar
- Beide Erinnerungs-Mails (Vorgesetzter und Abteilungen) sind jetzt als
  Vorlagen (DE/EN) mit Platzhaltern anpassbar
- Bugfixes: doppelt registrierte Routen (Abbrechen/Löschen) bereinigt,
  fehlendes @endif auf der Vorgangsseite, drei fehlende Übersetzungen
- Texte und UI/UX geprüft (Bestätigungsdialoge präzisiert)

## 1.0 – Build 2607161927 (16.07.2026)
- API v2 mit Token-Authentifizierung (Einstellungen → API: Token generieren
  oder manuell ändern, leer = deaktiviert): Vorgänge anlegen, Statusliste
  mit Filtern, Einzelstatus inkl. Quittierungen und aller gepflegten Werte
- CSV-Massenimport für HR auf „Neuen Vorgang anlegen": Vorlage zum Download,
  je Zeile ein Vorgang (Vorgesetzte werden benachrichtigt und ergänzen wie
  gewohnt), übersprungene Zeilen werden einzeln gemeldet
- UI/UX-Feinschliff (Import-Bereich, Fehlerliste auf der Übersicht,
  API-Panel) und englische Übersetzung durchgesehen (einheitlich, vollständig)
- Dokumentation erweitert: API-Kapitel und CSV-Import in den Handbüchern,
  Architektur- und Sicherheitsdokument ergänzt

## 1.0 – Build 2607161826 (16.07.2026)
- Dokumentation ergänzt: ARCHITEKTUR.md (Systemarchitektur mit Diagramm,
  Datenmodell, Statusmaschine, Update-System) und SICHERHEIT.md
  (Sicherheitsarchitektur: Authentifizierung, Berechtigungen, Sichtbarkeit,
  Protokollierung, Datenschutz, Härtungsempfehlungen) – beide werden wie
  der Changelog mit jedem Build aktuell gehalten

## 1.0 – Build 2607161418 (16.07.2026)
- Fehler 500 „Permission denied" (storage/framework/sessions) behoben:
  Dateirechte werden beim App-Start als letzter Schritt erzwungen und der
  Scheduler-Container läuft jetzt als www-data statt root – es entstehen
  keine root-eigenen Dateien mehr im geteilten Storage-Volume

## 1.0 – Build 2607161348 (16.07.2026)
- Apache-Startmeldung AH00558 unterdrückt (ServerName global gesetzt)

## 1.0 – Build 2607160731 (16.07.2026)
- Sicheres Update-System: beim Container-Start Versionsabgleich mit
  Downgrade-Schutz (alter Stand gegen neuere Datenbank startet nicht),
  automatisches Datenbank-Backup nach /data/backups (10 Stände),
  automatische Schema-Migration und versionsspezifische Konvertierungen
  alter Major-Stände; Version/Build/Update-Zeitpunkt werden vermerkt,
  protokolliert und unter Support & Lizenz angezeigt

## 1.0 – Build 2607160725 (16.07.2026)
- Changelog eingeführt: `CHANGELOG.md` im Paket, wird bei jedem Build
  fortgeschrieben; in der Anwendung einsehbar unter Support & Lizenz →
  Änderungshistorie (aufklappbar)

## 1.0 – Build 2607160701 (16.07.2026)
- Konfigurierbare Prozessarten: neuer Menüpunkt „Prozesse" (HR/Admin) zum
  Anlegen eigener Arten (z. B. Azubi-Übernahme) mit verkürztem Workflow –
  beteiligte Abteilungen je Prozess wählbar; nicht Beteiligte werden weder
  benachrichtigt noch quittieren sie, ihre Felder entfallen
- Fristen je Vorgang: Erledigungsdatum für den Vorgesetzten und späteres für die
  Abteilungen; automatische Erinnerung mit Zusammenfassung der offenen
  Aufgaben an den Vorgesetzten, Abteilungs-Erinnerung per Einstellung
  zuschaltbar (täglich 07:00, je einmalig); überfällige Fristen rot am Vorgang
- Demodaten: Prozess „Azubi-Übernahme" und Beispielvorgänge mit Fristen

## 1.0 – Build 2607130507 (13.07.2026)
- Test-Modus (TEST_MODE): TESTSYSTEM-Banner im Header, Kennzeichnung unter
  Support & Lizenz sowie auf allen Exporten (CSV/PDF)
- Version & Build auf Login-Seite und Support-Seite; Support-Seite vollständig
  ins Englische übersetzt
- Vierte Prozessart „Stammdatenänderung" (z. B. Namenswechsel) inkl. Felder,
  Demodaten und Datumsbezeichnung „Gültig ab"
- Bericht/Protokoll geprüft und korrigiert: alle fünf Status zweisprachig im
  PDF, Prozess-Spalte in PDF, CSV und Protokoll; Prozess in Log-Meldungen und
  Lösch-Details
- Produktname CrewGate (UI, Mails, Berichte, Doku, Logo/Favicon)

## 1.0 – Build 2607122100 (12.07.2026)
- Prozessarten Stellenwechsel und Austritt; Felder je Prozess an-/abwählbar
  oder exklusiv zuordenbar; Demodaten für alle Prozesse über den kompletten
  Lebenszyklus
- Drei HR-Feldarten: Anlage durch HR, Ausfüllen durch den Vorgesetzten in der
  Vorbereitung, „für alle Abteilungen sichtbar"
- Anpassbare E-Mail-Vorlagen mit Platzhaltern (DE/EN) in den Einstellungen;
  Standardtext mit Quittier-Aufforderung und Portal-Link
- Workflow-Grafik mit Kreisen (Übersicht zentriert groß, Vorgangsseite mit
  Quittier-Status); breitere Pflege-Felder; GD-Extension für PDF mit Bildern
- Benutzer- und Administratorhandbuch; Reverse-Proxy-Unterstützung
  (TrustProxies, forceScheme, Anleitung)

## 1.0 – Build 2607121700 (12.07.2026)
- Paralleles Quittieren: alle Abteilungen werden gleichzeitig benachrichtigt
  und quittieren einzeln; HR quittiert abschließend; ausstehende
  Quittierungen in allen Übersichten sichtbar
- HR kann Vorgänge abbrechen (Status „abgebrochen") und protokolliert löschen
- Kennzahlen-Karten; offene/abgeschlossene Vorgänge getrennt und nach Datum
  sortiert; Pflichtfeld-Meldung beim Absenden mit lesbaren Feldnamen
- Zweisprachigkeit (DE/EN) mit Sprachumschalter und Flaggen, automatische
  Browsersprache; Mails und Exporte zweisprachig; englische Bezeichnungen für
  Felder, Produkte und Kategorien
- iOS-Glass-Design und mobile Optimierung

## 1.0 – Build 2607112000 (11.07.2026)
- Vorbereitungsphase: Vorgesetzten-Mail nach Anlage (pauschaler Portal-Link),
  Datenpflege mit Zwischenspeichern und Absenden
- Mail-Log (Admin) mit vollständigem Nachrichtentext; Rollen-Mails als Text
  mit sortierten Daten inkl. freigegebener Rollen
- Support & Lizenz (Lizenzschema mit Kundennummer, Support-Ende, Anzahl);
  Einstellungen in nummerierte Kategorien gegliedert; Kategorien nur für
  Admins; Glas-Design
- Basis: Vorgänge mit AD-Anbindung, Rollen aus AD-Gruppen, konfigurierbare
  Felder und Produktkatalog, revisionssicheres Protokoll (SHA-256-Kette),
  CSV-/PDF-Export, monatlicher Audit-Bericht, Aufbewahrung & automatische
  Löschung, Demo-Modus
