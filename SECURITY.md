# Sicherheit / Security

*Erst Deutsch, dann Englisch — German first, English below.*

## Deutsch

### Sicherheitslücke melden

Bitte melde Sicherheitslücken **nicht** öffentlich als Issue, sondern
vertraulich an die Betreuer des Projekts, über die private
Sicherheitsmeldung von GitHub (*Security → Report a vulnerability*). Wir
melden uns so schnell wie möglich und sagen Bescheid, sobald eine
Korrektur veröffentlicht ist.

Hilfreich sind: betroffene Version (unter **Hilfe → System**), eine kurze
Beschreibung, Schritte zum Nachstellen und, falls bekannt, mögliche
Auswirkungen. Bitte keine echten Personaldaten mitschicken.

### Unterstützte Versionen

Korrekturen gibt es für die **jeweils neueste Version** (Schema JJMMTTNN,
siehe [README](README.md#versionen-und-docker-abbilder)). Bitte vor einer
Meldung prüfen, ob das Problem dort noch besteht.

### Eingebaute Schutzmaßnahmen

- **Anmeldung** mit eigenen Konten (Passwort als bcrypt-Hash) und optional
  über ein Verzeichnis (LDAP-Bind, Verzeichnispasswörter werden nicht
  gespeichert); Zwei-Faktor-Anmeldung (TOTP) je Gruppe; Bremse gegen
  Passwort-Raten. Startpasswörter (erster Admin, Passwort vom Admin,
  Zurücksetzen, Notzugang) müssen bei der nächsten Anmeldung geändert werden.
  Ein neues Passwort beendet die anderen Sitzungen des Kontos. Der Demo-Modus
  ist auf der Anmeldeseite deutlich gekennzeichnet.
- **Rechte aus der Benutzerverwaltung oder aus Verzeichnisgruppen**,
  serverseitig in Middleware und Controllern geprüft; das Konto wird bei jeder
  Anfrage geprüft (Sperren beendet laufende Sitzungen sofort). Gruppennamen
  werden beim Speichern zum vollständigen DN aufgelöst; ein Name in mehreren
  OUs wird abgelehnt.
  Statusübergänge sind strikt (Absenden nur aus der Vorbereitung, Quittieren
  nur durch die eigene Abteilung, Abschluss nur durch HR).
- **Need-to-know bis auf Feldebene**, auch in E-Mails. E-Mails enthalten nie
  Direktlinks auf Vorgänge, nur einen pauschalen Portal-Link.
- **Revisionssicheres Protokoll**: append-only, SHA-256-Hash-Kette,
  Lösch-Nachweise; jeder Export wird selbst protokolliert.
- **Gegen eingeschleusten Code**: Blade-Escaping durchgängig, CSRF-Schutz
  aller Formulare, Eloquent mit Bindings, serverseitige Validierung aus der
  Feldkonfiguration.
- **Uploads**: nur Bildtypen, Größenlimit, eigener Dateiname mit Endung aus
  dem Inhalt; SVG nur ohne Skripte; Auslieferung nur als Bild mit eigener
  Content-Security-Policy (`sandbox`).
- **API v2**: nur mit Token (timing-sicherer Vergleich), leeres Token =
  API aus; Rotation per Knopfdruck. Anlage-Felder werden wie im Formular
  geprüft; reguläre Ausdrücke laufen mit Rückschritt-Grenze (ReDoS).
- **Sicheres Update**: Downgrade-Schutz und Datenbanksicherung vor jeder
  Änderung.

Die vollständige Sicherheitsarchitektur steht in
[docs/SECURITY.md](docs/SECURITY.md).

### Hinweise für den Betrieb

- Nur über HTTPS betreiben (Reverse Proxy mit Zertifikat), Port `7101`
  nicht direkt freigeben; am besten nur intern oder per VPN erreichbar.
- **`TRUSTED_PROXIES`** steht ab Werk auf allen internen Netzen (`10.0.0.0/8`,
  `172.16.0.0/12`, `192.168.0.0/16`), damit ein Reverse Proxy ohne weitere
  Einstellung funktioniert. Bekannte Folge: Jedes Gerät in diesen Netzen kann
  mit einer gefälschten Kopfzeile `X-Forwarded-For` eine andere Adresse
  vortäuschen; die Anmeldebremse je Adresse greift dann nicht, die Sperre je
  Benutzername schon. Wer das vermeiden will, trägt nur die IP des Reverse
  Proxys ein.
- LDAPS zum Domain Controller verwenden; das Servicekonto braucht nur
  Leserechte.
- Die Anmeldung ist gebremst: 10 Versuche je Minute und Adresse, je
  Benutzername 10 Fehlversuche, dann 30 Minuten Sperre. Zusätzlich die
  Kontosperrung im AD nutzen.
- `APP_DEBUG` im Betrieb immer auf `false` lassen.
- `docker-compose.yml` bzw. `.env` enthalten `APP_KEY`, AD- und SMTP-Zugang:
  Dateirechte restriktiv halten.
- Datenbank und Sicherungen enthalten Personaldaten; bei erhöhtem
  Schutzbedarf auf einem verschlüsselten Volume ablegen. Jede Sicherung
  enthält auch den Schlüssel `.app_key` (für Servicekonto-Passwort und
  Zwei-Faktor-Geheimnisse): Den Ordner `backups` nur Administratoren
  zugänglich machen.
- Regelmäßig auf die neueste Version aktualisieren.

## English

### Reporting a vulnerability

Please do **not** report security vulnerabilities as a public issue. Report
them privately to the project maintainers via GitHub's private vulnerability
reporting (*Security → Report a vulnerability*). We will get back to you as
soon as possible and let you know once a fix has been released.

Helpful details: the affected version (under **Support & licence**), a short
description, steps to reproduce and, if known, the possible impact. Please
do not send real personnel data.

### Supported versions

Fixes are provided for the **latest version** (scheme YYMMDDNN, see the
[README](README.en.md#versions-and-docker-images)). Please check whether the
problem still exists there before reporting it.

### Built-in protections

- **Sign-in** with own accounts (password as a bcrypt hash) and optionally
  via a directory (LDAP bind, directory passwords are not stored); two-factor
  sign-in (TOTP) per group; password-guessing brake. Initial passwords (first
  admin, password set by an admin, reset, emergency access) must be changed at
  the next sign-in. A new password ends the account's other sessions. Demo mode
  is clearly marked on the login page.
- **Permissions from user management or directory groups**, enforced on the
  server in middleware and controllers; the account is checked on every
  request (locking ends running sessions at once). Group names are resolved to
  their full DN when saving; a name found in several OUs is refused. Strict
  status transitions.
- **Need-to-know down to field level**, including e-mails. E-mails never
  contain direct links to cases, only a generic portal link.
- **Tamper-evident audit log**: append-only, SHA-256 hash chain, deletion
  records; every export is logged itself.
- **Against injected code**: consistent Blade escaping, CSRF protection on
  all forms, Eloquent with bindings, server-side validation.
- **Uploads**: image types only, size limit, generated file name with the
  extension taken from the content; SVG only without scripts; delivered as
  images only with their own content security policy (`sandbox`).
- **API v2**: token only (timing-safe comparison), empty token = API off,
  one-click rotation. Intake fields are validated like the form; regular
  expressions run with a backtrack limit (ReDoS).
- **Safe updates**: downgrade protection and database backup before any
  change.

See [docs/SECURITY.md](docs/SECURITY.md) (German) for the full security
architecture.

### Operating securely

- Only run it over HTTPS behind a reverse proxy; do not expose port `7101`
  directly, ideally only internally or via VPN.
- **`TRUSTED_PROXIES`** covers all internal networks by default (`10.0.0.0/8`,
  `172.16.0.0/12`, `192.168.0.0/16`) so that a reverse proxy works without
  further settings. Known consequence: any device in these networks can fake
  another address with a forged `X-Forwarded-For` header; the sign-in throttle
  per address then does not apply, the lock per user name still does. To
  avoid this, enter only the reverse proxy's IP.
- Use LDAPS; the service account only needs read access.
- Sign-in is throttled: 10 attempts per minute and address, 10 failed
  attempts per user name, then a 30-minute lock. Also use AD account lockout.
- Always keep `APP_DEBUG` set to `false` in production.
- `docker-compose.yml` / `.env` contain `APP_KEY`, AD and SMTP credentials:
  keep file permissions tight.
- The database and backups contain personnel data; store them on an
  encrypted volume if required. Every backup also contains the key `.app_key`
  (for the service account password and two-factor secrets): make the
  `backups` folder accessible to administrators only.
- Update to the latest version regularly.
