# 🧭 CrewGate — Community Edition

**Freies, selbst gehostetes HR-Prozessportal mit Active-Directory-Anbindung.**
CrewGate bildet Einstellung, Stellenwechsel, Austritt und eigene Prozessarten
als Workflow ab: HR legt den Vorgang an, der Vorgesetzte bereitet ihn vor,
die Fachabteilungen (IT, Einkauf, Facility …) quittieren der Reihe nach, HR
schließt ab. Die *Community Edition* ist die vollständige Open-Source-Fassung
(AGPL-3.0). Alle Daten bleiben auf dem eigenen Server — eine SQLite-Datei,
kein Cloud-Dienst, kein Tracking.

*English version: [README.en.md](README.en.md)*

---

## Warum?

Bei jedem Ein- und Austritt müssen viele Stellen etwas tun: Konto anlegen,
Notebook bestellen, Schreibtisch aufbauen, Zugänge sperren. Ohne festen Ablauf
gehen Dinge per E-Mail verloren, und niemand weiß, wer was schon erledigt hat.
CrewGate macht daraus einen nachvollziehbaren Workflow:

- **Anmeldung mit und ohne Active Directory**: eigene Konten pflegt der
  Administrator in der Oberfläche; zusätzlich kann sich jeder Mitarbeiter
  mit seinem Windows-Konto anmelden (LDAP-Bind, CrewGate speichert keine
  Passwörter). Beides geht gleichzeitig.
- **Rechte kommen aus Gruppen oder aus der Benutzerverwaltung**: Rollen sind
  Abteilungen — bei Verzeichniskonten gemappt auf AD-Gruppen, bei eigenen
  Konten per Haken vergeben.
- **Need-to-know**: Jede Abteilung sieht nur ihre eigenen Felder und das, was
  ihr ausdrücklich freigegeben wurde — in der Oberfläche und in den E-Mails.
- **Revisionssicheres Protokoll**: append-only mit SHA-256-Hash-Kette,
  CSV- und PDF-Export, optional monatlicher Audit-Bericht an die Auditoren.
- **Automatische Löschung** abgeschlossener Vorgänge nach einer frei
  wählbaren Frist.

## Funktionen

| Bereich | Was geht |
|---|---|
| Prozessarten | Einstellung, Stellenwechsel, Austritt und Stammdatenänderung eingebaut; eigene Arten (z. B. „Azubi-Übernahme“) mit verkürztem Workflow — nicht beteiligte Abteilungen werden weder benachrichtigt noch müssen sie quittieren |
| Workflow | Rollen mit Sortiernummer; Vorbereitung durch den Vorgesetzten mit Zwischenspeichern, danach Benachrichtigung der Abteilungen; Quittieren direkt aus der Aufgabenliste; HR-Abschluss; Workflow-Grafik auf Übersicht und Vorgangsseite |
| Vorgesetzte | HR trägt den Vorgesetzten ein, seine Abteilung kommt aus dem AD (`department`); er sieht alle Vorgänge seiner Abteilung und darf die Schritte aller Abteilungen ausfüllen |
| Felder | je Rolle und Prozess: Auswahl, Mehrfachauswahl, Checkbox, Datum, Produktauswahl und Freitext mit Regex; Felder der HR-Anlage; „für alle Abteilungen sichtbar“ |
| Produktkatalog | Produkte mit Bild, Preis, Artikel- und Herstellernummer; Kategorien mit Farbe und Reihenfolge; Pflege durch die zuständige Rolle selbst |
| Sichtbarkeit | je Rolle „Sieht Daten von“; HR, Admins, Auditoren und Vorgesetzte sehen den ganzen Vorgang |
| E-Mails | Text-Mail an die zentrale Adresse der nächsten Rolle mit den für sie sichtbaren Daten; nie Direktlinks, nur ein pauschaler Portal-Link; Vorlagen DE/EN anpassbar; Mail-Log mit Volltext und Status |
| Fristen | Erledigungsdatum für Vorgesetzte und Abteilungen, automatische Erinnerungen |
| Massenimport | CSV-Import für viele Vorgänge auf einmal (z. B. Ausbildungsjahrgang) |
| API v2 | Vorgänge anlegen, Status und Werte abrufen — für HR- und ITSM-Systeme ([docs/API.md](docs/API.md)) |
| Protokoll | wer wann was ausgefüllt, quittiert, verschickt oder geändert hat; Hash-Kette; CSV- und PDF-Export; Rolle „Auditor“ mit reinem Lesezugriff |
| Löschung | per Schalter, Frist 1–120 Monate nach Abschluss, täglich um 02:00 Uhr; jede Löschung wird protokolliert |
| Sprachen | Deutsch und Englisch, Umschalter mit Flaggen; englische Bezeichnungen für Felder, Produkte und Kategorien; Mails und Exporte zweisprachig |
| Gestaltung | Firmenname, Logo, Header-, Akzent- und Footer-Farbe, Footer-Text; Glas-Design, hell und dunkel |
| Impressum und Datenschutz | einzeln einschaltbar, als eigener Text oder Link auf die Firmen-Homepage; ohne Anmeldung erreichbar, mit Vorlage für die Datenschutzhinweise |
| Hilfe | Version, Lizenz, Handbücher und Änderungshistorie direkt in der Anwendung (Footer → **Hilfe**) |
| Anmeldung | eigene Konten (Menü **Benutzer**) immer; Verzeichnis optional (Active Directory, Samba AD, OpenLDAP, Univention) mit „Verbindung testen“; Gruppen als DN oder Name, verschachtelte AD-Gruppen; Notzugang per `php artisan crewgate:admin-password` |
| Zwei-Faktor | TOTP mit Authenticator-App; je Gruppe Pflicht, wählbar oder aus, Verzeichnisgruppen mit Pflicht, Ausnahme je Benutzer; Wiederherstellungscodes; Zurücksetzen durch Administratoren |
| Fuchs-Helfer | Rua, der Fuchs (Wikinger-Fuchs aus einem norwegischen Fjord, Name aus Irland), duzt; begrüßt, gibt Tipps passend zu Rolle und Seite, wirbt auf der Anmeldeseite für freie Software; zentral und je Person abschaltbar |
| Betrieb | Docker, SQLite, nächtliche Sicherung mit Prüfsumme, sicheres Update mit Downgrade-Schutz, Testsystem-Kennzeichnung (`TEST_MODE`), Demo-Modus ohne Verzeichnis |
| Sicherheit | Sicherheits-Header (CSP, HSTS), Bremse gegen Passwort-Raten, Proxy-Angaben nur aus internen Netzen |

## Rollen

| Rolle | Darf |
|---|---|
| **HR** | Vorgänge anlegen (einzeln oder per CSV), abschließen, abbrechen, löschen; Prozessarten und Felder der HR-Anlage pflegen; Protokoll lesen |
| **Ersteller-Gruppe** | zusätzlich zu HR Vorgänge anlegen |
| **Vorgesetzter** | Vorgänge seiner Abteilung sehen, vorbereiten und absenden |
| **Abteilung** (Rolle) | eigene Schritte bearbeiten und quittieren; eigene Felder und Produkte pflegen |
| **Admin** | Rollen, Felder, Kategorien, Einstellungen, Mail-Log, API-Token, Lizenz |
| **Auditor** | Protokoll lesen und exportieren, sonst nichts |

Die Zuordnung erfolgt ausschließlich über AD-Gruppen (unter
**Einstellungen → Berechtigungen**, Startwerte aus `AD_GROUP_HR` und
`AD_GROUP_ADMIN`). Die vollständige Rechte-Matrix steht in
[docs/SECURITY.md](docs/SECURITY.md#3-autorisierung-rollenbasiert-über-ad-gruppen).

## Anleitungen

- [Installation mit Docker](installation/docker.md) — Linux-Server, Mini-PC, Docker Desktop
- [Installation auf Synology (Container Manager) und QNAP (Container Station)](installation/synology.md)
- [Betrieb hinter einem Reverse Proxy](installation/reverse-proxy.md) — HTTPS, `APP_URL`, nginx
- [Betrieb](installation/betrieb.md) — Updates, Sicherung, Wiederherstellung, Testsystem
- Handbücher: [Benutzer](docs/USER-GUIDE.md) · [Administrator](docs/ADMIN-GUIDE.md) · [Architektur](docs/ARCHITECTURE.md) · [Sicherheit](docs/SECURITY.md) · [Überblick](docs/DOCUMENTATION.md)

## Schnellstart

Voraussetzung ist Docker mit Compose.

```bash
git clone https://github.com/steffenmai-maidigital/crewgate.git
cd crewgate
mkdir -p backups
docker compose up -d --build
```

Danach ist CrewGate unter <http://localhost:7101> erreichbar. Im
Auslieferungszustand läuft der **Demo-Modus** (`DEMO_MODE: "true"`): acht
Demo-Konten mit Beispielvorgängen, kein Verzeichnis nötig, E-Mails landen im
Protokoll statt beim Mailserver.

| Konto | Passwort | Rolle |
|---|---|---|
| `anna.hr` | `demo` | HR — legt Vorgänge an |
| `max.admin` | `demo` | Admin |
| `petra.chef` | `demo` | Vorgesetzte (Vertrieb) |
| `karl.chef` | `demo` | Vorgesetzter (Produktion) |
| `tim.it` | `demo` | Abteilung IT |
| `eva.einkauf` | `demo` | Abteilung Einkauf |
| `frank.facility` | `demo` | Abteilung Facility |

Demodaten neu aufbauen: `docker exec crewgate php artisan demo:seed --fresh`
(elf Beispielvorgänge über alle Prozessarten und Stände).

### Normalbetrieb

1. In `docker-compose.yml` `DEMO_MODE: "false"` setzen. Eine `.env` ist nicht
   nötig. Beim ersten Start legt CrewGate den Administrator `admin` als
   eigenes Konto an; das Startpasswort steht im Container-Protokoll
   (`docker compose logs app`) oder wird vorher als `ADMIN_PASSWORD` gesetzt.
2. Als `admin` anmelden → **Benutzer**: eigenes Passwort vergeben, weitere
   Konten anlegen (HR, Abteilungen, Vorgesetzte) — oder unter
   **Einstellungen → 3 · Anmeldung** das Verzeichnis (Active Directory,
   Samba AD, OpenLDAP, Univention) eintragen, speichern und „Verbindung
   testen“. Danach meldet sich jeder Mitarbeiter mit seinem Firmenkonto an;
   Rechte kommen aus den Gruppen unter **Einstellungen → 4**.
3. **Einstellungen**: Firma, E-Mail-Versand mit Portal-Link, Testmail senden.
4. **Rollen**: von Hand anlegen oder „Gruppen aus dem Verzeichnis einlesen“,
   Rollen in den Workflow aufnehmen, Reihenfolge und zentrale E-Mail-Adresse
   pflegen. Eigene Konten ordnet der Administrator unter **Benutzer** ihren
   Abteilungen zu.
5. **Felder** und **Produkte** je Rolle anlegen.
6. Als HR den ersten Vorgang anlegen.

Niemand ausgesperrt: `docker exec crewgate php artisan crewgate:admin-password`
legt jederzeit ein eigenes Admin-Konto an oder setzt dessen Passwort neu.

Den Schlüssel `APP_KEY` erzeugt CrewGate beim ersten Start selbst und legt ihn
unter `/data/.app_key` ab. Schritt für Schritt: [installation/docker.md](installation/docker.md).

Hinter einem Reverse Proxy zusätzlich `APP_URL` auf die öffentliche Adresse
setzen, siehe [installation/reverse-proxy.md](installation/reverse-proxy.md).

### Einstellungen (Umgebungsvariablen)

| Variable | Standard | Bedeutung |
|---|---|---|
| `DEMO_MODE` | true (Compose) | Demo-Konten und Beispieldaten, kein Verzeichnis |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | admin / *(leer = zufällig, steht im Protokoll)* | erster Administrator als eigenes Konto, nur beim allerersten Start |
| `TEST_MODE` | false | zeigt „TESTSYSTEM“ als Banner, unter Support und auf allen Exporten |
| `APP_URL` | http://localhost:7101 | öffentliche Adresse; beginnt sie mit `https://`, entstehen alle Links mit https |
| `APP_KEY` | *(leer = beim ersten Start erzeugt, liegt in `/data/.app_key`)* | nur setzen, wenn ein bestimmter Schlüssel gelten soll |
| `TRUSTED_PROXIES` | interne Netze | Reverse Proxys, deren `X-Forwarded-*`-Angaben geglaubt werden; `*` = allen (nicht empfohlen) |
| `BACKUP_PATH` | /backups | Ordner der nächtlichen Sicherung (im Compose `./backups`) |
| `BACKUP_KEEP_DAYS` | 14 | so lange bleiben die nächtlichen Sicherungen liegen (mindestens 4) |
| `SOURCE_URL` | *(leer)* | Link zum Quellcode in der Fußzeile; bei eigenen Änderungen auf das eigene Repo setzen (AGPL) |
| `GITHUB_URL`, `PROJECT_URL` | GitHub-Projekt, www.mai-digital.de | Links unter **Hilfe** und in der Fußzeile |
| `DB_DATABASE` | /data/database.sqlite | Datenbankdatei im Volume `app-data` |
| `LDAP_HOST`, `LDAP_PORT`, `LDAP_SSL`, `LDAP_TLS` | — | optionale Startwerte für das Verzeichnis (sonst alles unter **Einstellungen → Anmeldung**); ein gesetzter Host schaltet die Verzeichnis-Anmeldung ein |
| `LDAP_BASE_DN`, `LDAP_USERNAME`, `LDAP_PASSWORD` | — | Suchbasis und Servicekonto (Startwerte) |
| `LDAP_ENABLED`, `LDAP_USER_ATTRIBUTE`, `LDAP_USER_FILTER`, `LDAP_BIND_TEMPLATE`, `LDAP_VERIFY_CERT`, `LDAP_TIMEOUT` | — | weitere Startwerte, siehe `.env.example` |
| `AD_GROUP_HR`, `AD_GROUP_ADMIN` | — | Startwerte für die Gruppen HR und Admin (danach in den Einstellungen) |
| `FRESH_DB` | 0 | `1` löscht beim Start die Datenbank. Danach sofort wieder auf `0` stellen |

SMTP, Verzeichnis, Gruppen, Aufbewahrung und Branding werden nach dem ersten
Start in der Oberfläche gepflegt; die Werte dort haben Vorrang.

### Daten und Sicherung

- `./backups` (Ordner, vor dem ersten Start anlegen): jede Nacht um 02:30 Uhr
  ein ZIP mit Datenbank und Uploads samt SHA-256-Prüfsumme; Sicherungen von
  Hand („Jetzt sichern“ unter **Einstellungen**) unter `./backups/manual`
- `app-data` (Volume): Datenbank `database.sqlite`, Schlüssel `.app_key` und
  die Sicherungen vor jedem Update unter `/data/backups/` (die 10 neuesten bleiben)
- `app-storage` (Volume): Sitzungen, Protokolle, Zwischenspeicher
- `app-uploads` (Volume): Firmenlogo und Produktbilder

Regelmäßige Sicherung, Wiederherstellung und Umzug: siehe
[installation/betrieb.md](installation/betrieb.md).

### Zeitgesteuerte Aufgaben

Der zweite Container `crewgate-scheduler` führt aus:

| Zeit | Befehl | Aufgabe |
|---|---|---|
| täglich 02:00 | `onboarding:purge` | Löschung nach Aufbewahrungsfrist |
| täglich 02:30 | – | Sicherung nach `./backups` |
| täglich 03:00 | `ad:sync-groups` | Rollen aus AD-Gruppen abgleichen |
| täglich 07:00 | `reminders:send` | Erinnerungen bei Fristen |
| monatlich am 1., 06:00 | `audit:report` | Audit-Bericht per Mail (falls eingeschaltet) |

Von Hand: `docker exec crewgate php artisan <befehl>`.

### Editionen und Lizenz

| Edition | Was |
|---|---|
| **Community Edition** | die Open-Source-Fassung. Vollständig, ohne Einschränkungen und ohne Ablaufdatum. |
| **Supported Edition** | dieselbe Software mit einer gepflegten Support-Lizenz (Standard oder Enterprise). |

Die Lizenz ist **rein informativ und hat keine funktionalen Auswirkungen**.
Admins tragen sie unter **Einstellungen → Lizenz** ein: Lizenznehmer und einen
20-stelligen Schlüssel. Angezeigt wird sie unter **Hilfe** im Footer;
ein abgelaufener Support erscheint dort rot, die Anwendung läuft trotzdem
uneingeschränkt weiter.

| Stellen | Bedeutung | Beispiel |
|---|---|---|
| 1 | `1` = Standard-Lizenz, `2` = Enterprise-Lizenz | `1` |
| 2–9 | Kundennummer (8 Zeichen, Buchstaben und Zahlen) | `MUSTER01` |
| 10–15 | Support-Ende als `JJMMTT` | `270731` |
| 16–20 | Lizenzanzahl (5 Ziffern, `99999` = unlimitiert) | `00500` |

## Sicheres Update

Beim Start des App-Containers läuft automatisch `php artisan app:upgrade`:

1. **Versionsabgleich** — ein Downgrade wird abgelehnt, der Container startet
   nicht, die Daten bleiben unangetastet.
2. **Sicherung** der Datenbank nach `/data/backups/`.
3. **Migrationen** und versionsspezifische **Konvertierungen**, jeweils genau einmal.
4. Version, Build und Zeitpunkt werden vermerkt und protokolliert.

Ablauf und Rückweg: [installation/betrieb.md](installation/betrieb.md).

## Versionen und Docker-Abbilder

Versionsnummern folgen dem Schema **JJMMTTNN**: Datum plus laufende Nummer
des Tages. `26101701` ist die erste Version am 17.10.2026. Ältere Stände
trugen die Nummer 1.0 mit Build-Zeitstempel (siehe [CHANGELOG](CHANGELOG.md)).

Jeder Bau erzeugt das Docker-Abbild **`crewgate:latest`**. Eine veröffentlichte
Version bekommt zusätzlich ihre Nummer als Tag, z. B. **`crewgate:26101701`**.
Für jede Version baut GitHub das Abbild außerdem für Intel/AMD und ARM und legt
es als `ghcr.io/steffenmai-maidigital/crewgate:<version>` und `:latest` in die
Paketablage.

Neue Versionen veröffentlichen die Betreuer; was sich in jeder Version ändert,
steht im [CHANGELOG](CHANGELOG.md).

## Entwicklung

```bash
docker build -t crewgate:dev .
docker run --rm -p 7101:80 -e DEMO_MODE=true -e APP_DEBUG=true crewgate:dev
```

Aufbau:

```
app/Models            Onboarding (Vorgang), OnboardingStep, OnboardingValue, Role,
                      CustomField, Process, Product, ProductCategory, ActivityLog, MailLog, Setting
app/Services          WorkflowService (Statusmaschine), LdapService (Benutzersuche, Gruppen),
                      DemoDirectory, MailService, ReportService (PDF), UpgradeService, LicenseService
app/Support           Directory (LDAP-Anbindung, gemeinsamer Baustein), Fox, ProductInfo, LegalPages, ErrorPage, Mfa
app/Http/Controllers  Vorgänge, Rollen, Felder, Produkte, Einstellungen, Protokoll, API v2
app/Console/Commands  purge, ad:sync-groups, reminders:send, audit:report, demo:seed, app:upgrade
resources/views       Blade-Oberfläche, eine Stylesheet-Datei unter public/css/app.css
lang/en.json          englische Übersetzung (deutsche Texte sind die Schlüssel)
docs/                 Handbücher (auch in der Anwendung unter „Hilfe“)
installation/         Anleitungen: Docker, Synology, Reverse Proxy, Betrieb (en/ = englisch)
```

Ohne Build-Schritt: kein Node und kein npm.

### Übersetzen

Alle Oberflächentexte sind auf Deutsch geschrieben und dienen als Schlüssel.
Die englischen Texte stehen in `lang/en.json`. Neue Texte bitte gleich in
`lang/en.json` ergänzen und Platzhalter wie `:name` übernehmen; vor jeder
Version prüfen die Betreuer, ob alles übersetzt ist. Fehlende Texte erscheinen auf Deutsch.

## Mitmachen

Siehe [CONTRIBUTING.md](CONTRIBUTING.md). Sicherheitslücken bitte nicht
öffentlich melden, siehe [SECURITY.md](SECURITY.md).

## Lizenz

[GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-or-later).

Ihr dürft CrewGate frei nutzen, verändern und weitergeben. Wer eine
**veränderte** Fassung als Webdienst für andere betreibt, muss deren Nutzern den
Quellcode dieser Fassung anbieten.
