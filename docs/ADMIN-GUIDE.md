# CrewGate – Administratorhandbuch

Dieses Handbuch beschreibt Installation, Konfiguration und Betrieb der
Anwendung. Für die tägliche Arbeit im Portal gibt es das
**Benutzerhandbuch** (`docs/USER-GUIDE.md`).

---

## 1. Überblick

| | |
|---|---|
| **Technik** | Laravel (PHP 8.4), SQLite, Apache – alles in einem Docker-Container |
| **Anmeldung** | eigene Konten (Menü **Benutzer**) und optional ein Verzeichnis: Active Directory, Samba AD, OpenLDAP, Univention (PHP-Erweiterung `ldap`, Baustein `App\Support\Directory`) |
| **Berechtigungen** | bei eigenen Konten per Haken unter **Benutzer**, bei Verzeichniskonten über Gruppen |
| **Daten** | SQLite-Datei im Volume `app-data` (`/data/database.sqlite`) |
| **Protokoll** | append-only, SHA-256-Hash-Kette, CSV- und PDF-Export |

Installiert wird aus dem Quellcode mit Docker Compose – auf einem
Linux-Server, einer VM, einem Mini-PC oder einem NAS mit Container-Oberfläche.
Die Anleitungen dazu liegen unter `installation/docker.md` und
`installation/reverse-proxy.md`, ein Beispiel für ein NAS unter `installation/synology.md`.

---

## 2. Installation

### 2.1 Docker mit Compose

```bash
git clone https://github.com/steffenmai-maidigital/crewgate.git
cd crewgate
docker compose up -d --build
```

Die Anwendung läuft anschließend auf **Port 7101**. Im Auslieferungszustand
ist der Demo-Modus aktiv (`DEMO_MODE: "true"` in der `docker-compose.yml`).
Details: `installation/docker.md`.

### 2.2 Container-Oberfläche (z. B. auf einem NAS)

Projektordner auf das Gerät kopieren, in der Container-Oberfläche ein Projekt
aus der `docker-compose.yml` anlegen und starten. Die Anwendung läuft auf
**Port 7101**; der Demo-Modus ist standardmäßig aktiv. Schritt für Schritt am
Beispiel einer Synology: `installation/synology.md`.

### 2.3 Nach dem Start

Migrationen laufen automatisch. Ein zweiter Container (`scheduler`) übernimmt
die zeitgesteuerten Aufgaben (Löschung, Gruppen-Sync, Audit-Bericht).

### 2.4 Aktualisieren (sicheres Update)

Beim Start prüft der App-Container automatisch (`app:upgrade`):

| Schritt | Wirkung |
|---|---|
| Versionsabgleich | Installierte Version (Datenbank) vs. Paket-Version. **Downgrade-Schutz**: passt ein altes Paket nicht zur neueren Datenbank, startet der Container nicht – mit klarer Fehlermeldung, ohne Datenänderung |
| Backup | Vor jeder Änderung konsistente Sicherung der SQLite-Datenbank nach `/data/backups/` (die 10 neuesten bleiben erhalten) |
| Migrationen | Schema wird automatisch hochmigriert |
| Konvertierungen | Versionsspezifische Datenumbauten (auch über Major-Sprünge hinweg) laufen der Reihe nach genau einmal |
| Vermerk | Version, Build und Update-Zeitpunkt werden gespeichert (sichtbar unter Hilfe) und im Protokoll festgehalten |

**Rollback**: Container stoppen, gewünschtes Backup aus `/data/backups/` als
`database.sqlite` zurückkopieren, das dazu passende Paket einspielen, starten.

### 2.4a Klassisch aktualisieren

Neuen Stand holen (`git pull` bzw. neue Dateien über den Ordner kopieren) und das Projekt neu erstellen/starten.
Migrationen werden automatisch nachgezogen; ein `--no-cache`-Build ist nur
nötig, wenn sich das `Dockerfile` geändert hat. Nach CSS-Änderungen im Browser
einmal **Strg+F5** drücken.

---

## 3. Test-Modus, Version & Build

Mit `TEST_MODE=true` (in `.env` bzw. Compose) wird das System als
**TESTSYSTEM** gekennzeichnet: rotes Banner ganz oben auf jeder Seite,
Hinweis unter *Hilfe* sowie eine Kennzeichnung auf allen Exporten
(erste Zeile im CSV, roter Kasten im PDF-Bericht). Funktional ändert sich
nichts – die Kennzeichnung verhindert Verwechslungen mit dem Produktivsystem.

Version und Build stehen auf der Login-Seite und unter *Hilfe*.
Der Build ist ein Zeitstempel im Format JJMMTTHHMM (z. B. `2607021122`) und
wird bei jedem Paket-Build hochgezählt (`config/version.php`).

## 3a. Demo-Modus

Mit `DEMO_MODE=true` ersetzt eine interne Benutzerverwaltung das Active
Directory. E-Mails werden nicht versendet, sondern nur protokolliert (sichtbar
im **Mail-Log**).

Demo-Konten (Passwort jeweils `demo`):

| Konto | Rolle |
|---|---|
| `anna.hr` | HR – legt Vorgänge an, schließt sie ab |
| `max.admin` | Administrator – Backend |
| `petra.chef` | Vorgesetzte (Vertrieb) |
| `karl.chef` | Vorgesetzter (Produktion) |
| `tim.it`, `eva.einkauf`, `frank.facility` | Fachabteilungen |
| `rita.audit` | Auditorin – Protokoll und Exporte |

Beim ersten Start entstehen Rollen, Felder, Produktkategorien, Produkte, eine
Beispiel-Lizenz und **vier Beispielvorgänge** in allen Status (Vorbereitung,
in Bearbeitung, wartet auf HR-Abschluss, abgeschlossen). Die Demodaten werden
nur angelegt, wenn die Datenbank noch keine Vorgänge enthält.

---

## 4. Einstellungen

Menüpunkt **Einstellungen** (nur Administratoren). Die Seite ist in sieben
Bereiche gegliedert.

### 4.1 Firma & Erscheinungsbild
Firmenname, Logo-Upload (PNG/JPG/SVG/WebP, max. 2 MB), Header-, Akzent- und
Footer-Farbe sowie Footer-Text. Die Farben gelten in hellem und dunklem Modus
und erscheinen auch im PDF-Audit-Bericht.

### 4.1a Fristen & Erinnerungen (Texte anpassbar)
Beim Anlegen eines Vorgangs kann HR zwei optionale Fristen setzen:
**Erledigen bis (Vorgesetzter)** und ein späteres **Erledigen bis
(Abteilungen)**. Ist die Vorgesetzten-Frist erreicht und der Vorgang noch in
Vorbereitung, geht automatisch **eine** Erinnerung mit der Zusammenfassung
der offenen Aufgaben (fehlende Pflichtangaben je Abteilung) an den
Vorgesetzten. Erinnerungen an die ausstehenden Abteilungen werden nur
versendet, wenn der Schalter unter *Einstellungen → E-Mail-Versand* aktiviert
ist. Überschrittene Fristen werden auf der Vorgangsseite rot angezeigt; alle
Erinnerungen erscheinen im Mail-Log. Die Texte beider Erinnerungen sind unter
*Einstellungen → E-Mail-Vorlagen* je in Deutsch und Englisch anpassbar
(Platzhalter u. a. `{due_date}`, `{tasks}` bzw. `{data}`).

### 4.2 E-Mail-Versand
SMTP-Server, Port, Verschlüsselung (STARTTLS/SMTPS/keine), Benutzername,
Passwort (leer lassen = unverändert), Absenderadresse und -name.

**Portal-Link für E-Mails**: Der pauschale Link, der in allen E-Mails steht
(z. B. `https://onboarding.firma.local`). Es wird nie direkt auf einen Vorgang
verlinkt. Leer = `APP_URL`.

Ganz unten auf der Seite: **SMTP testen** – verschickt eine Testmail, die
ebenfalls im Mail-Log landet.

### 4.3 Anmeldung (Verzeichnis)
Eigene Konten funktionieren immer (Menü **Benutzer**). Zusätzlich:

- **Anmeldung über das Verzeichnis einschalten** – Schalter; ohne Server und
  Suchbasis lässt er sich nicht setzen
- **Server, Port, Verschlüsselung** – LDAPS (636) empfohlen, STARTTLS (389)
  Standard, „keine“ nur im Testnetz; **Zertifikat prüfen** bei selbst
  signierten Zertifikaten abschalten
- **Suchbasis (Base DN)** – z. B. `DC=firma,DC=local`
- **Servicekonto** – DN oder Anmeldename, nur lesend; das Passwort wird mit
  dem `APP_KEY` verschlüsselt gespeichert (leer lassen = unverändert)
- **Weitere Angaben** – Attribut mit dem Benutzernamen (`sAMAccountName`
  bei AD, `uid` bei OpenLDAP/Univention), Benutzerfilter, Anmeldename nach
  Vorlage (ohne Servicekonto, z. B. `%s@firma.local`), Zeitlimit
- **Gruppenfilter für den Rollenabgleich** – optionales Präfix (z. B. `OB-`)

Nach dem Speichern **„Verbindung testen“** anklicken (eigener Kasten weiter
unten). Verzeichniskonten entstehen bei der ersten Anmeldung; Name, E-Mail,
Abteilung und Rechte kommen bei jeder Anmeldung neu aus dem Verzeichnis.
Ein eigenes Konto mit gleichem Benutzernamen hat Vorrang.

Notzugang, falls das Verzeichnis ausfällt oder niemand mehr hineinkommt:
`docker exec crewgate php artisan crewgate:admin-password` legt ein eigenes
Admin-Konto an bzw. setzt dessen Passwort neu.

**Zwei-Faktor-Anmeldung** (gleicher Abschnitt): je Gruppe Pflicht, wählbar
oder aus — Administratoren, HR, Auditoren, Vorgänge anlegen, Abteilungen,
alle übrigen; Verzeichnisgruppen mit Pflicht (eine je Zeile). Die strengste
Stufe gewinnt. Pflicht ohne Einrichtung sperrt die Anwendung bis zur
Einrichtung unter **Mein Konto** (QR-Code, Wiederherstellungscodes).
Ausnahmen je Benutzer und „Zwei-Faktor zurücksetzen“ (Handy verloren) unter
**Benutzer → Bearbeiten**.

### 4.4 Berechtigungen (Gruppen im Verzeichnis)
Für Verzeichniskonten hängen die Rechte an Gruppen — als vollständiger DN
oder nur als Gruppenname; verschachtelte AD-Gruppen werden aufgelöst. Für
eigene Konten vergibt der Administrator dieselben Rechte unter **Benutzer**.

| Einstellung | Wirkung |
|---|---|
| **HR-Gruppe** | sieht alle Vorgänge, legt sie an, schließt sie ab, bricht sie ab, löscht sie; pflegt die Anlage-Felder; sieht das Protokoll |
| **Gruppe „Vorgänge anlegen"** | optional – darf zusätzlich zu HR Vorgänge anlegen |
| **Administratoren-Gruppe** | Benutzer, Rollen, Einstellungen, Kategorien, alle Felder und Produkte, Mail-Log, Protokoll |
| **Auditoren-Gruppe** | optional – lesender Zugriff auf das gesamte Protokoll inkl. Exporte |

Änderungen wirken ab der nächsten Anmeldung der betroffenen Benutzer.

### 4.5 Aufbewahrung & automatische Löschung
Schalter *Abgeschlossene Vorgänge automatisch löschen* plus Frist in Monaten
(1–120). Die Prüfung läuft täglich um **02:00 Uhr**. Jede Löschung wird
protokolliert; die Protokolleinträge selbst bleiben erhalten.

### 4.6 Monatlicher Audit-Bericht
Optional: am **1. des Monats um 06:00 Uhr** geht der PDF-Bericht automatisch
raus – entweder an eine feste Adresse oder an die E-Mail-Adressen aller
Mitglieder der Auditoren-Gruppe (aus dem AD gelesen).

### 4.7 E-Mail-Vorlagen
Die Texte der Vorgesetzten-Mail und der Rollen-Benachrichtigung lassen sich je
Sprache anpassen (leer = eingebauter Standardtext). Platzhalter: `{name}`,
`{department}`, `{date}`, `{role}`, `{data}`, `{link}`, `{supervisor}`.
Der Standardtext fordert die Abteilungen auf, sich im Portal einzuloggen und
**Erhalt und Umsetzung zu quittieren** – mit dem Portal-Link aus den
Einstellungen. Versendet wird immer zweisprachig (erst Deutsch, dann Englisch).

### 4.8 Lizenz
Firma (Lizenznehmer) und 20-stelliger Lizenzschlüssel. Aufbau:

| Stellen | Bedeutung |
|---|---|
| 1 | `1` = Standard-Lizenz, `2` = Enterprise-Lizenz |
| 2–9 | Kundennummer (8 Zeichen, Buchstaben und Zahlen) |
| 10–15 | Support-Ende als **JJMMTT** (z. B. `260202` = 02.02.2026) |
| 16–20 | Lizenzanzahl (5 Ziffern; `99999` oder mehr = „unlimitiert") |

Beispiel: `2DEMO000127123199999` → Enterprise, Kunde DEMO0001, Support bis
31.12.2027, unlimitiert.

Nur schema-konforme Schlüssel (inklusive gültigem Datum) werden gespeichert;
sonst erscheint eine Fehlermeldung mit dem Schema. Die Anzeige erfolgt im
Footer unter **Hilfe**; ein abgelaufener Support wird rot angezeigt,
hat aber **keine funktionalen Auswirkungen**.

### 4.9 Impressum und Datenschutz
Beide Seiten lassen sich einzeln einschalten – als eigener Text oder als Link
auf eine vorhandene Seite (z. B. die Firmen-Homepage). Eingeschaltete Seiten
sind ohne Anmeldung erreichbar (`/imprint`, `/privacy`) und stehen auf
der Anmeldeseite und im Footer. Für die Datenschutzhinweise gibt es eine
Vorlage, die beschreibt, was CrewGate tatsächlich speichert; Angaben in
[eckigen Klammern] ersetzen und den Text vom Datenschutzbeauftragten prüfen
lassen. Einfacher Text: Leerzeile = neuer Absatz, `# ` am Zeilenanfang =
Überschrift, Adressen werden automatisch zu Links.

---

## 5. Rollen (Abteilungen)

Menüpunkt **Rollen**. Eine Rolle entspricht einer AD-Gruppe und damit einer
Abteilung im Workflow.

- **AD-Gruppen jetzt einlesen** – synchronisiert die Gruppen aus dem AD
  (läuft zusätzlich nächtlich automatisch)
- **Reihenfolge** – bestimmt die Sortierung in Listen, Formularen, Mails und
  Exporten
- **AD-Gruppe (DN)** – wer Mitglied ist, darf für diese Rolle quittieren
- **Zentrale E-Mail** – Empfängeradresse der Benachrichtigung. Fehlt sie, wird
  das im Protokoll vermerkt und keine Mail versendet
- **Sieht Daten von** – Mehrfachauswahl: welche anderen Rollen diese Rolle
  zusätzlich einsehen darf. Diese Daten erscheinen auch in der E-Mail
  (Beispiel: IT sieht die Bestellung des Einkaufs)
- **Im Workflow** – nur solche Rollen erhalten Benachrichtigungen und müssen
  quittieren

Eine feste Rolle **„HR (Anlage des Vorgangs)"** ist nicht Teil des Workflows.
Ihre Felder kennen zwei Zusatzoptionen:

- **Ausfüllen bei**: *Anlage durch HR* (Feld erscheint im Anlage-Formular)
  oder *Vorbereitung durch den Vorgesetzten* (Feld erscheint im
  Vorbereitungs-Formular des Vorgesetzten – für Angaben, die der Vorgesetzte
  für HR ergänzen soll)
- **Für alle Abteilungen sichtbar**: der Wert wird jeder Abteilung angezeigt
  und steht in jeder Rollen-E-Mail – unabhängig von „Sieht Daten von"
  (z. B. die Stellenbezeichnung)

### Delegierte Pflege
Rollen-Mitglieder können unter **Verwaltung** die **Felder und Produkte ihrer
eigenen Rolle** pflegen – die IT also ihre IT-Felder, der Einkauf seinen
Produktkatalog. Rollen, Kategorien und Einstellungen bleiben Administratoren
vorbehalten.

---

## 6. Prozessarten

Eingebaut sind vier Prozessarten: **Einstellung**, **Stellenwechsel**,
**Austritt** und **Stammdatenänderung** (z. B. Namenswechsel nach Heirat).
Unter dem Menüpunkt **Prozesse** legen HR und Administratoren zusätzlich
eigene Arten an – etwa eine **Azubi-Übernahme** mit verkürztem Workflow:
Pro Prozess werden die **beteiligten Abteilungen** angehakt (keine Auswahl =
alle). Nicht beteiligte Abteilungen erhalten keine E-Mail, müssen nicht
quittieren, und ihre Felder werden weder abgefragt noch geprüft. Eingebaute
Prozesse lassen sich umbenennen, verkürzen und deaktivieren, aber nicht
löschen; eigene Prozesse sind löschbar, solange keine Vorgänge sie verwenden. HR wählt die Art beim Anlegen des Vorgangs; das Datumsfeld heißt
dann entsprechend Eintritts-, Wechsel-, Austrittsdatum oder „Gültig ab". Ablauf,
Quittierung durch die Abteilungen und HR-Abschluss sind bei allen Arten
identisch. In allen Listen und E-Mails wird die Prozessart angezeigt
(Platzhalter `{process}` in den Vorlagen).

Jedes Feld trägt Checkboxen für die drei Prozesse: So gelten Felder wahlweise
für alle Prozesse, nur für einzelne (z. B. „Möbelauswahl" nicht beim Austritt)
oder exklusiv für einen (z. B. „Konten deaktivieren" nur beim Austritt).
Formulare, Pflichtfeld-Prüfungen und E-Mails berücksichtigen automatisch nur
die Felder des jeweiligen Prozesses.

## 7. Felder

Menüpunkt **Felder**. Pro Rolle werden die Eingabefelder definiert:

| Feldtyp | Beschreibung |
|---|---|
| **Auswahlfeld** | Einfachauswahl aus Optionen (eine Option pro Zeile) |
| **Mehrfachauswahl** | Checkbox-Gruppe |
| **Checkbox** | Ja/Nein; als Pflichtfeld muss sie angehakt werden |
| **Datumsauswahl** | Datumsfeld |
| **Produktauswahl** | Auswahl aus dem Produktkatalog (mit Bild, Preis, Kategorie) |
| **Freitext (mit Regex)** | Text mit optionaler Formatprüfung |

Weitere Eigenschaften:

- **Bezeichnung** und **Bezeichnung (EN, optional)** – ist die Oberfläche auf
  Englisch, wird die englische Bezeichnung verwendet (Fallback: deutsch). In
  E-Mails erscheinen Feldnamen zweisprachig
- **Regulärer Ausdruck** ohne Delimiter, z. B. `^P-\d{5}$`
- **Hinweistext bei Fehleingabe**, z. B. „Format: P-12345"
- **Pflichtfeld** – wird beim Absenden durch den Vorgesetzten und beim
  Quittieren geprüft
- **Reihenfolge** und **Aktiv**

> Deaktivierte Felder verschwinden aus den Formularen, bereits erfasste Werte
> bleiben erhalten.

---

## 7. Produkte und Kategorien

Menüpunkt **Produkte**.

**Kategorien** (nur Administratoren): Name, englischer Name (optional), Farbe
und Reihenfolge. Die Farbe markiert die Produktkarten in der Auswahl.

**Produkte** (Administratoren und die jeweilige Rolle): Name, englischer Name,
Artikelnummer, Herstellernummer, Preis, Bild, Kategorie und die Zuordnung zu
einem **Produktauswahl-Feld**. Nur zugeordnete Produkte erscheinen im
jeweiligen Feld.

Deaktivierte Produkte tauchen nicht mehr in der Auswahl auf, bleiben aber in
bestehenden Vorgängen sichtbar. Der **Bereinigen**-Button (Administratoren)
löscht Produkte, die seit mehr als 6 Monaten deaktiviert sind.

---

## 8. Mail-Log

Menüpunkt **Mail-Log** (Administratoren). Zeigt jede ausgehende E-Mail mit
Zeitstempel, Empfänger, Betreff, Status (gesendet/fehlgeschlagen, ggf. mit
Fehlermeldung) und – per Klick auf den Betreff – dem **vollständigen
Nachrichtentext**. Auch Testmails und der Audit-Bericht erscheinen hier. Im
Demo-Modus werden Mails nicht zugestellt, aber vollständig protokolliert.

---

## 9. Protokoll und Audit

Menüpunkt **Protokoll** (HR, Administratoren, Auditoren). Jeder Eintrag ist
über eine **SHA-256-Hash-Kette** mit seinem Vorgänger verknüpft; nachträgliche
Änderungen wären damit erkennbar (Manipulationsnachweis im Sinne von
ISO/IEC 27001 A.8.15).

Protokolliert werden: Anlage, Datenpflege, Quittierungen, Abschluss, Abbruch,
Löschungen (automatisch und manuell), E-Mail-Versand, Einstellungsänderungen
und Exporte.

- **CSV-Export** mit zweisprachigen Spaltenköpfen
- **PDF-Audit-Bericht** mit Firmen-Branding, Export-Zeitstempel, Löschungen,
  den zuletzt eingestellten Mitarbeitern und dem vollständigen Protokoll

---

## 10. Zeitgesteuerte Aufgaben

| Zeit | Aufgabe |
|---|---|
| täglich 02:00 | Löschung abgelaufener Vorgänge (wenn aktiviert) |
| täglich 07:00 | Fristen-Erinnerungen (Vorgesetzte immer, Abteilungen wenn aktiviert) |
| nächtlich | Synchronisation der AD-Gruppen zu Rollen |
| 1. des Monats, 06:00 | Versand des PDF-Audit-Berichts (wenn aktiviert) |

Ausgeführt vom Container `scheduler`. Läuft er nicht, greifen diese Aufgaben
nicht – der Rest der Anwendung funktioniert weiter.

---

## 11. Betrieb

**Sicherung.** Jede Nacht um 02:30 Uhr legt der Scheduler ein ZIP mit
Datenbank (konsistent per `VACUUM INTO`) und Uploads samt SHA-256-Prüfsumme im
Ordner `./backups` ab; aufbewahrt wird `BACKUP_KEEP_DAYS` Tage (Standard 14,
mindestens 4). **Einstellungen → Sicherung → Jetzt sichern** legt zusätzlich
eine Sicherung unter `./backups/manual` ab, die nie automatisch gelöscht wird.
Wiederherstellung und Umzug: `installation/betrieb.md`.

**Logs.** `docker compose logs -f app` bzw. die Protokollansicht der
Container-Oberfläche.

**Häufige Stolpersteine**

| Symptom | Ursache / Lösung |
|---|---|
| Keine Mails, aber Einträge im Mail-Log mit „fehlgeschlagen" | SMTP-Daten prüfen, Testmail nutzen; die Fehlermeldung steht im Log |
| Anmeldung schlägt fehl | eigenes Konto: Passwort unter **Benutzer** zurücksetzen; Verzeichnis: **Einstellungen → Anmeldung → Verbindung testen**, Benutzerattribut und Filter prüfen; Notzugang `crewgate:admin-password` |
| Benutzer sieht Menüpunkte nicht | eigenes Konto: Rechte unter **Benutzer**; Verzeichniskonto: Gruppen in den Einstellungen prüfen; Neuanmeldung nötig |
| Rollen fehlen | von Hand anlegen oder „Gruppen aus dem Verzeichnis einlesen“, ggf. Gruppenfilter prüfen |
| Alte Optik nach Update | Browser-Cache: Strg+F5 |
| Scheduler startet in Schleife neu, Log: „bootstrap/cache directory must be present and writable" | Image ohne www-data-Rechte auf bootstrap/cache – einmal mit Image-Neubau aktualisieren (`docker compose up -d --build` bzw. Projekt neu erstellen); ab diesem Build setzt Image und Entrypoint die Rechte selbst |
| Fehler 500 auf allen Seiten, Log: „Permission denied" bei storage/framework/sessions | Storage-Volume gehört root statt www-data. Einmalig: `docker compose exec app chown -R www-data:www-data /var/www/html/storage /var/www/html/public/uploads /data`. Ab dieser Version erzwingt der Start die Rechte automatisch (Scheduler läuft als www-data) |
| Keine Demodaten | Sie entstehen nur, wenn noch keine Vorgänge existieren |

---

## 12. Berechtigungen auf einen Blick

| Aktion | HR | Admin | Rolle | Vorgesetzter | Auditor |
|---|:--:|:--:|:--:|:--:|:--:|
| Vorgang anlegen | ✓ | | | | |
| Daten in der Vorbereitung pflegen / absenden | ✓ | | | ✓ | |
| Erhalt quittieren | | (✓) | ✓ | | |
| Vorgang abschließen | ✓ | | | | |
| Vorgang abbrechen / löschen | ✓ | | | | |
| Eigene Felder & Produkte pflegen | | ✓ | ✓ | | |
| Rollen, Kategorien, Einstellungen, Mail-Log | | ✓ | | | |
| Protokoll und Exporte | ✓ | ✓ | | | ✓ |

(✓) = Administratoren können ersatzweise quittieren, wenn eine Abteilung
blockiert ist.


## 9. API v2

Die vollständige API-Dokumentation steht in `docs/API.md` (Aktivierung,
Authentifizierung, Endpunkte, Beispiele, Sicherheitsempfehlungen).

## 10. CSV-Massenimport (HR) – geführter Assistent

Der Import läuft als Assistent in drei Schritten (Menü: „Neuen Vorgang
anlegen" → „Import-Assistenten öffnen"):

1. **Vorlage herunterladen** – immer die aktuelle Vorlage verwenden; sie
   enthält die passenden Spalten und Beispielzeilen mit den tatsächlich
   vorhandenen Prozessarten. Die Spalten-Anleitung (Bedeutung, Format,
   Pflichtangaben) steht direkt auf der Seite
2. **Hochladen** – die ausgefüllte CSV wird eingelesen und geprüft, es wird
   dabei **noch nichts angelegt**
3. **Zeilen abarbeiten** – jede Zeile erscheint als eigene, korrigierbare
   Karte mit allen relevanten Feldern (Name, Prozess-Auswahl, Datum, Fristen,
   Notizen). Der/die Vorgesetzte wird je Zeile über die **AD-Suche**
   bestätigt. Mit „Vorgang anlegen" wird die Zeile einzeln angelegt (der
   Vorgesetzte erhält sofort seine E-Mail), mit „Zeile verwerfen"
   übersprungen – bis alle Zeilen abgearbeitet sind. Der Fortschritt
   (angelegt/offen) wird oben angezeigt

Hinweise: Trennzeichen `;` oder `,`, Datum `TT.MM.JJJJ` oder `JJJJ-MM-TT`;
unbekannte Prozesse oder unlesbare Daten werden je Zeile als Hinweis markiert
und im Schritt 3 korrigiert. Jede Anlage erscheint im Protokoll als „per
CSV-Import-Assistent".
