# App-Dokumentation — CrewGate

> Wird bei jedem Release mitgepflegt. Aktuelle Version: siehe `config/version.php` bzw. Login-/Support-Seite.

## Module

| Modul | Zweck | Rolle |
|---|---|---|
| Übersicht (Dashboard) | Kennzahlen, eigene Aufgaben, offene/abgeschlossene Vorgänge, Workflow-Kette | alle |
| Vorgang anlegen | Einzelanlage mit AD-Suche, Prozessart, Fristen; CSV-Import-Assistent für Sammel-Anlagen | HR / Ersteller-Gruppe |
| Vorbereitung | Vorgesetzter pflegt die Abteilungs-Felder (Zwischenspeichern/Absenden) | Vorgesetzter, HR |
| Quittieren | Abteilungen bestätigen Erhalt & Umsetzung ihrer Daten | Rollen-Mitglieder |
| Prozesse | Eigene Prozessarten mit verkürztem Workflow (beteiligte Abteilungen je Prozess) | HR, Admin |
| Felder / Produkte / Kategorien | Konfigurierbare Formulare je Rolle/Prozess/Phase, Produktkatalog | Admin, Rollen |
| Rollen | Abteilungen mit AD-Gruppen, zentraler E-Mail, Sichtbarkeits-Freigaben | Admin |
| Einstellungen | SMTP, AD, Berechtigungen, Branding, Aufbewahrung, Mail-Vorlagen, Lizenz, API-Token | Admin |
| Protokoll & Audit | Revisionssicheres Log (SHA-256-Kette), CSV/PDF-Export, Monatsbericht | HR, Admin, Auditor |
| Mail-Log | Jede ausgehende Mail mit Volltext und Status | Admin |
| API v2 | Vorgänge anlegen, Status & gepflegte Werte abrufen (Token) | Systeme (siehe docs/API.md) |

## Typischer Ablauf eines Vorgangs
1. HR legt an (einzeln oder per CSV-Assistent) → Vorgesetzten-Mail
2. Vorgesetzter pflegt die Angaben und sendet ab
3. Alle beteiligten Abteilungen werden gleichzeitig informiert und quittieren
4. HR schließt ab; Aufbewahrungsfrist läuft, danach automatische Löschung

## Update-Verfahren
Neuen Stand holen (`git pull` oder neue Dateien kopieren) → `docker compose up -d --build`
(bzw. in einer Container-Oberfläche das Projekt neu erstellen). Beim Start läuft
`app:upgrade` automatisch: Versionsabgleich (Downgrade-Schutz), Backup nach
`/data/backups/`, Migrationen, Konvertierungen. Daten in den Volumes bleiben
erhalten. Notfall-Reset: `FRESH_DB: "1"` (löscht die Datenbank!).

## Dokumente

| Datei | Inhalt |
|---|---|
| `README.md` | Überblick, Features, Installation |
| `CHANGELOG.md` | Versionshistorie (Schema JJMMTTNN) |
| `CONTRIBUTING.md` / `SECURITY.md` | Mitmachen, Sicherheitslücken melden |
| `installation/*.md` | Installation (Docker, Synology), Reverse Proxy, Betrieb – deutsch, englisch unter `installation/en/` |
| `docs/USER-GUIDE.md` | HR, Vorgesetzte, Abteilungen |
| `docs/ADMIN-GUIDE.md` | Installation, Konfiguration, Betrieb |
| `docs/API.md` | API v2 (Token, Endpunkte, Beispiele) |
| `docs/ARCHITECTURE.md` | Systemarchitektur |
| `docs/SECURITY.md` | Sicherheitsarchitektur |
