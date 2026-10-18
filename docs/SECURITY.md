# CrewGate – Sicherheitsarchitektur

> Wird bei jedem Release mitgepflegt.

---

## 1. Schutzziele

| Ziel | Umsetzung (Kurzfassung) |
|---|---|
| Vertraulichkeit | AD-Anmeldung, rollenbasierte Sichtbarkeit bis auf Feldebene, keine Direktlinks in Mails |
| Integrität | append-only-Protokoll mit SHA-256-Hash-Kette, serverseitige Validierung, CSRF-Schutz |
| Verfügbarkeit | automatische Backups vor Updates, Downgrade-Schutz, Container-Isolation |
| Nachvollziehbarkeit | lückenloses Protokoll aller Aktionen, Mail-Log mit Volltext, Audit-Berichte |

## 2. Authentifizierung

- Anmeldung ausschließlich gegen **Active Directory** (LDAP-Bind mit den
  Benutzer-Credentials). CrewGate speichert **keine Passwörter** – weder
  Hashes noch Klartext.
- Sitzung über Laravel-Session (Cookie `HttpOnly`, signiert mit `APP_KEY`);
  Abmelden invalidiert die Session serverseitig.
- **Demo-Modus** (`DEMO_MODE=true`): interne Testkonten, deutlich auf der
  Login-Seite ausgewiesen – nur für Evaluierung, nie für Produktivdaten.
- Empfohlen: LDAPS zum Domain Controller (`LDAP_SSL`/Port 636 in der .env).

## 3. Autorisierung (rollenbasiert über AD-Gruppen)

Alle Rechte leiten sich aus AD-Gruppenmitgliedschaften ab; die Gruppen-DNs
pflegt der Admin in den Einstellungen. Durchgesetzt wird serverseitig über
Middleware **und** zusätzliche Prüfungen in den Controllern (kein Vertrauen
in ausgeblendete Buttons).

| Aktion | HR | Admin | Rollen-Mitglied | Vorgesetzter | Auditor |
|---|:--:|:--:|:--:|:--:|:--:|
| Vorgang anlegen, abschließen, abbrechen, löschen | ✓ | | | | |
| Vorbereitung pflegen/absenden | ✓ | | | ✓ (eigene Abteilung) | |
| Erhalt quittieren | | (✓ Notfall) | ✓ (nur eigene Rolle) | | |
| Prozesse pflegen | ✓ | ✓ | | | |
| Eigene Felder/Produkte pflegen | | ✓ | ✓ | | |
| Rollen, Kategorien, Einstellungen, Mail-Log | | ✓ | | | |
| Protokoll lesen/exportieren | ✓ | ✓ | | | ✓ (nur lesend) |

Wesentliche Invarianten:
- **Quittieren kann nur die Fachabteilung selbst** (AD-Mitgliedschaft der
  Rolle); weder HR noch Vorgesetzte können für eine Abteilung quittieren
  (Admin nur als dokumentierte Notfall-Ausnahme).
- Statusübergänge sind serverseitig strikt: Absenden nur aus `draft`,
  Quittieren nur aus `open` und nur für ausstehende Rollen, HR-Abschluss nur
  aus `review`.

## 4. Sichtbarkeit der Daten (Need-to-know)

- Abteilungs-Benutzer sehen nur die **Felder ihrer eigenen Rollen** plus die
  per „Sieht Daten von" ausdrücklich freigegebenen Rollen plus Felder mit
  „für alle sichtbar".
- Dieselbe Filterung gilt in den **E-Mails**: jede Rolle erhält nur ihre
  sichtbaren Daten (`MailService::dataTextForRole`).
- HR, Admin, Auditor, Vorgesetzter (eigene Abteilung) und Ersteller sehen
  den vollständigen Vorgang.
- E-Mails enthalten **nie Direktlinks auf Vorgänge**, nur den pauschalen
  Portal-Link – Datenzugriff erfordert immer eine Anmeldung.

## 5. Eingabe-Validierung & Web-Sicherheit

- **CSRF**: alle Formulare mit Laravel-CSRF-Token (`@csrf`), zustandsändernde
  Aktionen ausschließlich per POST/DELETE.
- **Validierung serverseitig** aus der Feldkonfiguration erzeugt (Typ,
  Pflicht, Optionen-Whitelist, Regex); Client-Attribute sind nur Komfort.
- **XSS**: Blade-Escaping (`{{ }}`) durchgängig; keine ungeprüfte
  HTML-Ausgabe von Benutzereingaben.
- **SQL-Injection**: ausschließlich Eloquent/Query-Builder mit Bindings.
- **Uploads** (Logo, Produktbilder): Typ-Whitelist (png/jpg/svg/webp),
  Größenlimit, Speicherung unter kontrolliertem Namen in `public/uploads`.
- **Lizenzschlüssel/Settings**: strenge Schema-Prüfung vor dem Speichern;
  URL-Felder mit URL-Validierung.

## 6. Protokollierung & Revisionssicherheit

- `activity_logs` ist **append-only**: Anlage, Datenpflege, Quittierungen,
  Statuswechsel, Mails, Einstellungsänderungen, Exporte und **jede Löschung**
  (automatisch wie manuell, mit Eckdaten des gelöschten Vorgangs).
- Jeder Eintrag referenziert den Vorgänger über eine **SHA-256-Hash-Kette**
  → nachträgliche Manipulation ist erkennbar (Manipulationsnachweis im Sinne
  von ISO/IEC 27001 A.8.15).
- **Mail-Log** speichert jede ausgehende Mail mit Volltext und Status –
  nur für Administratoren einsehbar.
- **Audit-Bericht** (PDF) mit Export-Zeitstempel und Exporteur; optional
  monatlich automatisch an die Auditoren-Gruppe.

## 7. Datenschutz & Datenlebenszyklus

- **Datensparsamkeit**: erfasst wird nur, was die konfigurierten Felder
  vorgeben; Mails transportieren nur rollenbezogen sichtbare Daten.
- **Aufbewahrung & Löschung**: abgeschlossene (und abgebrochene) Vorgänge
  werden nach konfigurierbarer Frist (1–120 Monate) automatisch gelöscht;
  HR kann manuell löschen. Gelöscht wird vollständig (Werte, Quittierungen);
  erhalten bleibt nur der Lösch-Protokolleintrag mit Eckdaten.
- **Backups** (`/data/backups/`) enthalten Personaldaten → unterliegen
  denselben Zugriffsregeln wie das Volume (nur Host-Root/Docker).
- **TEST_MODE** kennzeichnet Nicht-Produktivsysteme unübersehbar (Banner,
  Exporte) und verhindert Verwechslungen.

## 8. Transportsicherheit

- **TLS terminiert am Reverse Proxy** (nginx/NPM); die Anwendung erzeugt
  hinter dem Proxy korrekte https-URLs (TrustProxies + `forceScheme`, wenn
  `APP_URL` mit https beginnt). `TRUSTED_PROXIES` kann auf die Proxy-IP
  eingeschränkt werden.
- **SMTP**: STARTTLS oder SMTPS aus den Einstellungen; Passwort wird nie in
  der Oberfläche wieder ausgegeben (leer lassen = unverändert).
- **LDAP**: LDAPS empfohlen (Konfiguration über .env).

## 8b. Sicherheits-Header und Anmeldung

- Jede Antwort trägt eine strenge **Content-Security-Policy** (nur eigener
  Server), `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`,
  `Referrer-Policy: same-origin` und eine `Permissions-Policy` ohne Kamera,
  Mikrofon und Standort. Hinter HTTPS zusätzlich **HSTS** und Cookies nur mit
  `Secure` (Middleware `SecurityHeaders`).
- **Bremse gegen Passwort-Raten:** höchstens 10 Anmeldeversuche je Minute und
  Adresse; nach 10 Fehlversuchen für einen Benutzernamen ist dieser 30 Minuten
  gesperrt – unabhängig von der Adresse.
- **Proxy-Angaben** (`X-Forwarded-*`) werden standardmäßig nur aus internen
  Netzen geglaubt (`TRUSTED_PROXIES`), damit niemand eine falsche
  Absender-Adresse vortäuschen kann.
- Der Schlüssel `APP_KEY` liegt im Datenvolume (`/data/.app_key`, Rechte 600).

## 8a. API-Sicherheit (v2)

- Zugriff nur mit **API-Token** (Einstellungen → API); Vergleich mit
  `hash_equals` (timing-sicher), leeres Token = API vollständig deaktiviert
- Token-**Rotation** per Knopfdruck („Neues Token generieren", altes sofort
  ungültig); jede Änderung wird protokolliert
- Gleiche serverseitige **Validierung** wie in der Oberfläche (Prozess muss
  existieren, Feld-Whitelist bei Anlage-Werten); API-Aktionen erscheinen im
  Protokoll als Benutzer „API"
- Empfehlung: nur über TLS verwenden; am Reverse Proxy den Pfad `/api/`
  bei Bedarf auf interne Netze beschränken
- **CSV-Import** (HR-Oberfläche): Datei-Typ- und Größenprüfung, zeilenweise
  Validierung mit Fehlerbericht statt Abbruch, jede Anlage protokolliert

## 9. Betriebssicherheit (Container & Updates)

- Beide Container laufen mit **www-data** für die Anwendung (Apache droppt
  Privilegien, der Scheduler startet direkt als www-data); Dateirechte auf
  den Volumes werden bei jedem Start erzwungen.
- **Sicheres Update** beim Start: Versionsabgleich mit **Downgrade-Schutz**
  (altes Paket gegen neuere Datenbank startet nicht), konsistentes
  **Datenbank-Backup vor jeder Änderung**, Migrationen und
  Versions-Konvertierungen; alles protokolliert.
- Geheimnisse (`APP_KEY`, AD-Bind, SMTP) liegen in `.env` bzw. der
  Compose-Datei – Zugriff darauf entspricht Host-Zugriff und ist
  entsprechend zu schützen.

## 10. Restrisiken & Empfehlungen

| Thema | Empfehlung |
|---|---|
| Host-/Volume-Zugriff | Wer Docker-Host-Zugriff hat, hat Datenzugriff (SQLite, Backups) → Host härten, Zugriff minimieren |
| SQLite unverschlüsselt | Bei erhöhtem Schutzbedarf: verschlüsseltes Dateisystem/Volume auf dem Host |
| Passwort-Raten | Eingebaut: 10 Versuche je Minute und Adresse sowie 10 Fehlversuche je Benutzername → 30 Minuten Sperre. Zusätzlich Kontosperrung im AD nutzen |
| Öffentliche Erreichbarkeit | Portal nur intern bzw. per VPN exponieren; am Proxy TLS erzwingen und HTTP→HTTPS umleiten |
| .env/Compose | Nicht in Backups mit weitem Leserkreis aufnehmen; Dateirechte restriktiv |
| Updates | Regelmäßig einspielen; Changelog und „Zuletzt aktualisiert" unter Hilfe prüfen |
