# CrewGate – Systemarchitektur

> Wird bei jedem Release mitgepflegt.

---

## 1. Überblick

CrewGate ist ein HR-Prozessportal für Einstellung, Stellenwechsel, Austritt,
Stammdatenänderung und selbst definierte Prozesse. Die Anwendung ist bewusst
schlank aufgebaut: **ein Laravel-Monolith in einem Docker-Container**, eine
**SQLite-Datenbank in einem Volume**, ein zweiter Container für zeitgesteuerte
Aufgaben. Keine externen Dienste außer Active Directory (Anmeldung/Gruppen)
und einem SMTP-Server (E-Mail).

```
                   ┌────────────────────────────────────────────────┐
   Browser ──TLS──▶│ Reverse Proxy (nginx / NPM, optional)          │
                   └───────────────┬────────────────────────────────┘
                                   │ HTTP (X-Forwarded-*)
                   ┌───────────────▼────────────────┐   ┌───────────────────┐
                   │ app-Container                  │   │ scheduler-Container│
                   │  Apache 2.4 + PHP 8.4          │   │  php artisan       │
                   │  Laravel (CrewGate)            │   │  schedule:work     │
                   │  läuft als www-data            │   │  läuft als www-data│
                   └───┬─────────┬─────────┬────────┘   └───────┬───────────┘
                       │         │         │                    │
             LDAP(S)   │         │ SMTP(S) │  gemeinsame Volumes│
        ┌──────────────▼──┐  ┌───▼──────┐  │  ┌─────────────────▼──────────┐
        │ Active Directory│  │Mailserver│  └─▶│ app-data   → /data         │
        └─────────────────┘  └──────────┘     │   database.sqlite, backups/│
                                              │ app-storage→ storage/      │
                                              │ app-uploads→ public/uploads│
                                              └────────────────────────────┘
```

## 2. Komponenten

| Komponente | Technik | Aufgabe |
|---|---|---|
| app-Container | php:8.4-apache, Laravel 13 | Web-UI, API-Endpunkte (AD-Suche), gesamte Geschäftslogik |
| scheduler-Container | gleiches Image, `schedule:work`, `SKIP_SETUP=1` | Zeitgesteuerte Jobs (siehe 7) |
| SQLite | Datei `/data/database.sqlite` | gesamte Persistenz; Backups unter `/data/backups/` |
| Volumes | app-data, app-storage, app-uploads | Daten, Laravel-Storage (Sessions, Logs, Caches), Logo/Produktbilder |
| Active Directory | LdapRecord (directorytree/ldaprecord-laravel) | Anmeldung, Benutzersuche, Gruppen→Rollen-Sync |
| SMTP | Laravel Mail (SMTP/SMTPS aus den Einstellungen) | alle ausgehenden Mails |

Der Demo-Modus (`DEMO_MODE=true`) ersetzt das AD durch die interne Klasse
`DemoDirectory` und unterdrückt echten Mailversand (nur Mail-Log).

## 3. Schichten der Anwendung

1. **HTTP-Schicht**: Routen (`routes/web.php`) → Middleware → Controller.
   Wichtige Middleware: `SetLocale` (DE/EN), `EnsureLdapAuthenticated`,
   `EnsureHr`, `EnsureAdmin`, `EnsureHrOrAdmin`, `EnsureCanCreate`,
   `EnsureCanManage`, TrustProxies (Reverse-Proxy-Header).
2. **Controller**: Onboarding (Vorgangs-Lebenszyklus), Dashboard, Process,
   Support, Log sowie Admin/* (Rollen, Felder, Produkte, Einstellungen,
   Mail-Log).
3. **Services** (gesamte Geschäftslogik):
   - `WorkflowService` – Absenden (Benachrichtigung aller beteiligten Rollen)
     und Quittierung inkl. Statusübergang
   - `MailService` – zentraler Text-Mailversand mit Vorlagen/Platzhaltern,
     Portal-Link, Mail-Log; `dataTextForRole` (sortierte, sichtbare Daten)
   - `LdapService` / `DemoDirectory` – Anmeldung, Benutzersuche, Gruppen
   - `LicenseService` – Lizenzschlüssel-Parser (Schema, Support-Ende)
   - `ReportService` – PDF-Audit-Bericht (dompdf)
   - `UpgradeService` – sicheres Update (siehe 8)
4. **Modelle/Datenbank**: Eloquent-Modelle, Migrationen `0001_…_000000` bis
   `…_000012` (fortlaufend, additiv).
5. **API v2** (`routes/api.php`, Middleware `api.token`): tokenbasierte
   REST-Schnittstelle – Vorgang anlegen, Statusliste, Einzelstatus mit
   Quittierungen und gepflegten Werten. Anlage über die API durchläuft
   denselben Workflow wie die Oberfläche (Vorbereitung, Mails, Protokoll).

## 4. Datenmodell (Kerntabellen)

| Tabelle | Inhalt |
|---|---|
| onboardings | Vorgang: Person, Prozess-Key, Status, Vorgesetzter (+E-Mail), Abteilung, Fristen (`supervisor_due_date`, `roles_due_date`) + Erinnerungs-Merker, `completed_at` |
| processes | Prozessarten: key, Name (DE/EN), beteiligte `role_ids` (leer = alle), aktiv, `is_system` |
| roles | Abteilungen: AD-Gruppen-DN, zentrale E-Mail, Reihenfolge, `in_workflow`, `is_intake` (HR-Anlage), `visible_role_ids` (Daten-Freigaben) |
| custom_fields | Feldkonfiguration: Rolle, Typ (select/multiselect/checkbox/date/product/text+Regex), Pflicht, DE/EN-Bezeichnung, `processes` (Zuordnung), `fill_stage` (Anlage/Vorbereitung), `visible_to_all` |
| onboarding_values | erfasste Werte je Vorgang+Feld (`filled_by`) |
| onboarding_steps | Quittierungen je Vorgang+Rolle (`completed_by/at`) |
| products / product_categories | Produktkatalog inkl. DE/EN-Namen, Preis, Bild, Feld-Zuordnung |
| settings | Key-Value: SMTP, AD, Berechtigungs-Gruppen, Branding, Aufbewahrung, Portal-Link, Mail-Vorlagen, Lizenz, App-Version/Build |
| activity_logs | revisionssicheres Protokoll (append-only, SHA-256-Kette) |
| mail_logs | jede ausgehende Mail mit vollständigem Text und Status |

## 5. Vorgangs-Lebenszyklus (Statusmaschine)

```
 draft ──Absenden (Vorgesetzter/HR)──▶ open ──alle Rollen quittiert──▶ review ──HR schließt ab──▶ done
   │                                     │                                 │
   └──────────────── HR kann jederzeit abbrechen ─────────────────────────┴──▶ cancelled
```

- **draft**: HR legt an (Prozessart + Anlage-Felder), Vorgesetzten-Mail mit
  Portal-Link; Vorgesetzter pflegt alle Abteilungs-Felder (Zwischenspeichern
  ohne Pflichtprüfung, Absenden mit Pflichtprüfung)
- **open**: alle am Prozess beteiligten Rollen werden gleichzeitig per
  Text-Mail (DE+EN, mit ihren sichtbaren Daten) informiert und quittieren
  unabhängig voneinander (Formular oder Direkt-Quittierung aus der Liste)
- **review**: alle Quittierungen liegen vor, HR quittiert abschließend →
  **done** (startet die Aufbewahrungsfrist)
- Löschen (manuell durch HR oder automatisch nach Frist) wird vor dem
  Entfernen vollständig protokolliert

## 6. Prozessarten & Feldsteuerung

- Prozesse sind Daten (Tabelle `processes`), nicht Code: HR legt eigene an
  (z. B. Azubi-Übernahme) und wählt die beteiligten Rollen → verkürzter
  Workflow ohne Code-Änderung
- Felder werden dreifach gefiltert: Rolle → Prozessart (`processes`) →
  Phase (`fill_stage` bei HR-Feldern); Pflichtprüfungen nutzen dieselben
  Filter (eine Quelle der Wahrheit im `CustomField`-Scope `forProcess`)

## 7. Zeitgesteuerte Jobs (scheduler-Container)

| Zeit | Command | Aufgabe |
|---|---|---|
| täglich 02:00 | onboarding:purge | Löschung nach Aufbewahrungsfrist (protokolliert) |
| täglich 03:00 | ad:sync-groups | AD-Gruppen → Rollen |
| täglich 07:00 | reminders:send | Fristen-Erinnerungen (Vorgesetzte immer, Rollen per Einstellung) |
| 1. des Monats 06:00 | audit:report | PDF-Audit-Bericht an Auditoren |

## 8. Update-/Versionsarchitektur

Beim Start des app-Containers läuft `php artisan app:upgrade`
(`UpgradeService`):

1. Versionsabgleich Datenbank ↔ Paket (`config/version.php`), **Downgrade
   wird abgelehnt** (Container startet nicht)
2. **Backup** der SQLite-Datenbank via `VACUUM INTO` nach `/data/backups/`
   (10 Stände), nur wenn Änderungen anstehen
3. `migrate --force` (Schema)
4. **Konvertierungs-Registry** `UpgradeService::STEPS`: versionsspezifische
   Datenumbauten laufen aufsteigend genau einmal (Major-Sprünge möglich)
5. Version/Build/Zeitpunkt in `settings` + Protokolleintrag; Caches leeren

Dateirechte werden als letzter Schritt erzwungen (chown/chmod auf storage,
uploads, /data); der Scheduler läuft als www-data.

## 9. Internationalisierung

- Deutsch ist die Quellsprache (Strings = Übersetzungsschlüssel),
  `lang/en.json` liefert Englisch, `lang/de/validation.php` deutsche
  Validierungsmeldungen
- Sprachwahl: Session > Browser-`Accept-Language` (de → Deutsch, sonst
  Englisch); Umschalter im Header
- Inhaltsdaten (Felder, Produkte, Kategorien, Prozesse) tragen optionale
  EN-Bezeichnungen; Mails/CSV/PDF sind zweisprachig (erst DE, dann EN)

## 10. Deployment-Varianten

- **Docker mit Compose** (Linux-Server, VM, Mini-PC, NAS): `docker compose up -d --build`,
  Port 7101 (`installation/docker.md`)
- **Container-Oberflächen** (z. B. auf NAS-Systemen): dieselbe Compose-Datei ohne `.env`
  (Variablen inline), Port 7101; Beispiel für eine solche Oberfläche: `installation/synology.md`
- **Reverse Proxy** (empfohlen für TLS): `APP_URL` auf öffentliche URL,
  `TRUSTED_PROXIES`, `X-Forwarded-*`-Header – Details in
  `installation/reverse-proxy.md`
