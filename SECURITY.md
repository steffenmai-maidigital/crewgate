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

- **Anmeldung nur über Active Directory** (LDAP-Bind); CrewGate speichert
  keine Passwörter. Der Demo-Modus ist auf der Anmeldeseite deutlich
  gekennzeichnet.
- **Rechte nur aus AD-Gruppen**, serverseitig in Middleware und Controllern
  geprüft. Statusübergänge sind strikt (Absenden nur aus der Vorbereitung,
  Quittieren nur durch die eigene Abteilung, Abschluss nur durch HR).
- **Need-to-know bis auf Feldebene**, auch in E-Mails. E-Mails enthalten nie
  Direktlinks auf Vorgänge, nur einen pauschalen Portal-Link.
- **Revisionssicheres Protokoll**: append-only, SHA-256-Hash-Kette,
  Lösch-Nachweise; jeder Export wird selbst protokolliert.
- **Gegen eingeschleusten Code**: Blade-Escaping durchgängig, CSRF-Schutz
  aller Formulare, Eloquent mit Bindings, serverseitige Validierung aus der
  Feldkonfiguration.
- **Uploads**: nur Bildtypen, Größenlimit, eigener Dateiname.
- **API v2**: nur mit Token (timing-sicherer Vergleich), leeres Token =
  API aus; Rotation per Knopfdruck.
- **Sicheres Update**: Downgrade-Schutz und Datenbanksicherung vor jeder
  Änderung.

Die vollständige Sicherheitsarchitektur steht in
[docs/SECURITY.md](docs/SECURITY.md).

### Hinweise für den Betrieb

- Nur über HTTPS betreiben (Reverse Proxy mit Zertifikat), Port `7101`
  nicht direkt freigeben; am besten nur intern oder per VPN erreichbar.
- `TRUSTED_PROXIES` auf die IP des Reverse Proxys einschränken.
- LDAPS zum Domain Controller verwenden; das Servicekonto braucht nur
  Leserechte.
- Die Anmeldung hat kein eigenes Rate-Limit: Kontosperrung im AD und/oder
  Rate-Limit am Reverse Proxy nutzen.
- `APP_DEBUG` im Betrieb immer auf `false` lassen.
- `docker-compose.yml` bzw. `.env` enthalten `APP_KEY`, AD- und SMTP-Zugang:
  Dateirechte restriktiv halten.
- Datenbank und Sicherungen enthalten Personaldaten; bei erhöhtem
  Schutzbedarf auf einem verschlüsselten Volume ablegen.
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

- **Login only via Active Directory** (LDAP bind); CrewGate stores no
  passwords. Demo mode is clearly marked on the login page.
- **Permissions only from AD groups**, enforced on the server in middleware
  and controllers. Strict status transitions.
- **Need-to-know down to field level**, including e-mails. E-mails never
  contain direct links to cases, only a generic portal link.
- **Tamper-evident audit log**: append-only, SHA-256 hash chain, deletion
  records; every export is logged itself.
- **Against injected code**: consistent Blade escaping, CSRF protection on
  all forms, Eloquent with bindings, server-side validation.
- **Uploads**: image types only, size limit, generated file name.
- **API v2**: token only (timing-safe comparison), empty token = API off,
  one-click rotation.
- **Safe updates**: downgrade protection and database backup before any
  change.

See [docs/SECURITY.md](docs/SECURITY.md) (German) for the full security
architecture.

### Operating securely

- Only run it over HTTPS behind a reverse proxy; do not expose port `7101`
  directly, ideally only internally or via VPN.
- Restrict `TRUSTED_PROXIES` to the reverse proxy's IP.
- Use LDAPS; the service account only needs read access.
- There is no login rate limit in the app: use AD account lockout and/or a
  rate limit on the reverse proxy.
- Always keep `APP_DEBUG` set to `false` in production.
- `docker-compose.yml` / `.env` contain `APP_KEY`, AD and SMTP credentials:
  keep file permissions tight.
- The database and backups contain personnel data; store them on an
  encrypted volume if required.
- Update to the latest version regularly.
