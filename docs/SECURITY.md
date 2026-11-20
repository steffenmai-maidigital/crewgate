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

- **Eigene Konten** (Menü **Benutzer**) mit bcrypt-Passwort, immer möglich;
  zusätzlich optional ein **Verzeichnis** (Active Directory, Samba AD,
  OpenLDAP, Univention) per LDAP-Bind. Passwörter von Verzeichniskonten
  speichert CrewGate nicht.
- **Startpasswort:** Der erste Admin mit Zufallspasswort, Konten mit einem
  Passwort vom Admin, Konten nach „Passwort zurücksetzen“ und nach dem
  Notzugang müssen bei der nächsten Anmeldung ein eigenes Passwort vergeben
  (`users.must_change_password`); bis dahin nur **Mein Konto** und Abmelden.
  Nicht im Demo-Modus.
- **Zwei-Faktor** (TOTP) je Gruppe Pflicht, wählbar oder aus; Geheimnisse mit
  `APP_KEY` verschlüsselt.
- Sitzung über Laravel-Session (Cookie `HttpOnly`, signiert mit `APP_KEY`);
  Abmelden invalidiert die Session serverseitig. Jede Sitzung trägt einen
  **Fingerabdruck des Passwort-Hashes** (HMAC mit `APP_KEY`). Ändert sich das
  Passwort, enden alle anderen Sitzungen bei der nächsten Anfrage; beim
  eigenen Wechsel bleibt die laufende Sitzung.
- **Notzugang:** `php artisan crewgate:admin-password [name] [--disable-2fa]`
  per `docker exec`; nur mit Zugriff auf den Docker-Host.
- **Demo-Modus** (`DEMO_MODE=true`): interne Testkonten, deutlich auf der
  Login-Seite ausgewiesen – nur für Evaluierung, nie für Produktivdaten.
- Empfohlen: LDAPS zum Verzeichnisserver (Einstellungen → Anmeldung).

## 3. Autorisierung (rollenbasiert über AD-Gruppen)

Rechte eigener Konten vergibt der Admin unter **Benutzer**; bei
Verzeichniskonten leiten sie sich aus Gruppenmitgliedschaften ab. Die Gruppen
pflegt der Admin in den Einstellungen; ein Gruppenname wird beim Speichern zum
vollständigen DN aufgelöst, ein mehrdeutiger Name (gleiche Gruppe in mehreren
OUs) wird abgelehnt. Durchgesetzt wird serverseitig über
Middleware **und** zusätzliche Prüfungen in den Controllern (kein Vertrauen
in ausgeblendete Buttons).

| Aktion | HR | Admin | Rollen-Mitglied | Vorgesetzter | Auditor |
|---|:--:|:--:|:--:|:--:|:--:|
| Vorgang öffnen | ✓ | ✓ | ✓ (eingeschränkte Daten) | ✓ (eigene Vorgänge; Abteilung: nur Kopfdaten) | ✓ |
| Vorgang anlegen, abschließen, abbrechen, löschen | ✓ | | | | |
| Vorbereitung pflegen/absenden | ✓ | | | ✓ (als Vorgesetzter eingetragen) | |
| Erhalt quittieren | | (✓ Notfall) | ✓ (nur eigene Rolle) | | |
| Prozesse pflegen | ✓ | ✓ | | | |
| Eigene Felder/Produkte pflegen | | ✓ | ✓ | | |
| Rollen, Kategorien, Einstellungen, Mail-Log | | ✓ | | | |
| Protokoll lesen/exportieren | ✓ | ✓ | | | ✓ (nur lesend) |

Wesentliche Invarianten:
- **Vorgänge öffnen nur Berechtigte**: Wer keines der Rechte oben hat und den
  Vorgang nicht selbst angelegt hat, bekommt Fehler 403 – auch mit einer
  erratenen Vorgangsnummer.
- **Das Konto wird bei jeder Anfrage geprüft**: Sperren oder Löschen beendet
  eine laufende Sitzung sofort; Rechte eigener Konten gelten ab der nächsten
  Seite. Rechte aus Verzeichnisgruppen werden bei der Anmeldung übernommen.
  Eine strengere Zwei-Faktor-Stufe gilt ebenfalls ab der nächsten Seite.
- **Vorgesetzter ist, wer als Vorgesetzter eingetragen ist** (Benutzername),
  nicht jeder aus derselben Abteilung. Kollegen derselben Abteilung sehen nur
  die Kopfdaten (Name, Prozess, Datum, Stand), keine Notizen und Feldwerte,
  und pflegen keine Vorbereitung.
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
- HR, Admin, Auditor, der eingetragene Vorgesetzte und der Ersteller sehen
  den vollständigen Vorgang. Weitere Konten derselben Abteilung sehen nur die
  Kopfdaten.
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
  Größenlimit, Speicherung unter kontrolliertem Namen in `public/uploads`; die
  Endung kommt aus dem Inhalt der Datei, nie aus ihrem Namen. Ein SVG-Logo
  darf keine Skripte, Ereignis-Attribute, Verweise nach außen oder
  eingebetteten Inhalte enthalten. Unter `/uploads/` liefert der Webserver nur
  Bilddateien aus, jeweils mit eigener Content-Security-Policy (`sandbox`, keine
  Skripte) und `X-Content-Type-Options: nosniff`.
- **Bestätigungsdialoge** setzen Namen (Produkt, Kategorie, Feld, Prozess) als
  JSON-kodierte Zeichenkette ein (`@js`), nie als rohen Text im Skript.
- **CSV-Export**: Zellen, die mit `=`, `+`, `-`, `@`, Tabulator oder
  Apostroph beginnen, bekommen ein Apostroph davor (Schutz vor Formeln in
  Tabellenprogrammen).
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
  Jede Sicherung (nächtlich und „Jetzt sichern“) enthält ihn, damit
  verschlüsselte Felder nach einer Wiederherstellung lesbar sind. Die
  Sicherungen bleiben auf dem Server; der Ordner `backups` gehört nur
  Administratoren.

## 8a. API-Sicherheit (v2)

- Zugriff nur mit **API-Token** (Einstellungen → API); Vergleich mit
  `hash_equals` (timing-sicher), leeres Token = API vollständig deaktiviert
- Token-**Rotation** per Knopfdruck („Neues Token generieren", altes sofort
  ungültig); jede Änderung wird protokolliert
- Gleiche serverseitige **Validierung** wie in der Oberfläche: nur aktive
  Prozesse, Anlage-Felder mit Pflicht, Format und Auswahl-Optionen, sonst 422
  mit Fehlern je Feld; API-Aktionen erscheinen im Protokoll als Benutzer „API"
- **Reguläre Ausdrücke** der Felder (Oberfläche und API) laufen mit kleiner
  Rückschritt-Grenze, Freitext höchstens 2000 Zeichen: Ein zu aufwendiger
  Ausdruck (ReDoS) ergibt eine Feldmeldung statt langer Rechenzeit
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
| Sicherungen | Enthalten Personaldaten und `.app_key`: Ordner `backups` nur für Administratoren, Auslagerung verschlüsselt |
| Updates | Regelmäßig einspielen; Changelog und „Zuletzt aktualisiert" unter Hilfe prüfen |
