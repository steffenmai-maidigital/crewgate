# CrewGate mit Docker installieren

*English version: [en/docker.md](en/docker.md)*

Diese Anleitung gilt für jeden Rechner mit Docker: einen Linux-Server, einen
Mini-PC oder – zum Ausprobieren – Docker Desktop auf Mac und Windows. Für ein
Synology- oder QNAP-NAS gibt es eine eigene Anleitung: [synology.md](synology.md).

CrewGate läuft in zwei Containern aus demselben Abbild:

| Container | Aufgabe |
|---|---|
| `crewgate` | Weboberfläche auf Port **7101** |
| `crewgate-scheduler` | zeitgesteuerte Aufgaben: nächtliche Sicherung, Löschfristen, AD-Abgleich, Erinnerungen, Audit-Bericht |

## Voraussetzungen

- Docker 24 oder neuer mit Docker Compose (`docker compose version` zeigt die Version)
- mindestens 1 GB freier Arbeitsspeicher und 2 GB Speicherplatz
- Internetzugang **nur beim Bauen** des Abbilds (Composer lädt Laravel und
  weitere Pakete). Im Betrieb braucht CrewGate kein Internet.
- für den Normalbetrieb: Zugriff auf einen Domain Controller (LDAP, besser LDAPS)
- ein Mailserver (SMTP) für die Benachrichtigungen

## 1. Dateien holen

```bash
git clone https://github.com/steffenmai-maidigital/crewgate.git
cd crewgate
mkdir -p backups
```

Ohne Git: auf GitHub **Code → Download ZIP** wählen, das ZIP entpacken, in den
Ordner wechseln und darin den Ordner `backups` anlegen. In diesem Ordner
landen die nächtlichen Sicherungen.

## 2. Zum Ausprobieren starten

Im Auslieferungszustand läuft CrewGate im **Demo-Modus**: ohne Active Directory,
mit Beispieldaten und festen Demo-Konten. E-Mails werden nicht verschickt, sondern ins Protokoll geschrieben.

```bash
docker compose up -d --build
docker compose logs -f app
```

Der erste Start baut das Abbild und dauert einige Minuten. Fertig ist er,
wenn im Protokoll `resuming normal operations` erscheint (Protokoll mit **Strg+C** verlassen). Dann im Browser öffnen:
<http://localhost:7101> bzw. `http://<Server-IP>:7101`.

Demo-Konten (Passwort jeweils `demo`):

| Konto | Rolle |
|---|---|
| `anna.hr` | Personalabteilung – legt Vorgänge an |
| `max.admin` | Administrator – Rollen, Felder, Einstellungen |
| `petra.chef`, `karl.chef` | Vorgesetzte (Vertrieb bzw. Produktion) |
| `tim.it`, `eva.einkauf`, `frank.facility` | Fachabteilungen IT, Einkauf, Facility |
| `rita.audit` | Auditor – liest das Protokoll |

Die Beispieldaten lassen sich jederzeit neu aufbauen:
`docker exec crewgate php artisan demo:seed --fresh`.

## 3. Für den Betrieb einrichten

### 3.1 Compose-Datei anpassen

Alle Einstellungen stehen in der `docker-compose.yml` beim Dienst `app`. Eine
`.env`-Datei ist nicht nötig; der `scheduler` übernimmt die Werte automatisch.

| Einstellung | Was eintragen |
|---|---|
| `DEMO_MODE` | `"false"` |
| `APP_URL` | die Adresse, unter der CrewGate später erreichbar ist, z. B. `https://onboarding.firma.de` – ohne Schrägstrich am Ende |
| `LDAP_HOST` | Name des Domain Controllers, z. B. `dc01.firma.local` |
| `LDAP_PORT` und `LDAP_SSL` | `636` und `"true"` für LDAPS (empfohlen) – oder `389` mit `LDAP_TLS: "true"` |
| `LDAP_BASE_DN` | Suchbasis, z. B. `DC=firma,DC=local` |
| `LDAP_USERNAME`, `LDAP_PASSWORD` | ein Servicekonto, das im AD nur **lesen** darf |
| `AD_GROUP_HR` | vollständiger DN der Gruppe der Personalabteilung, z. B. `CN=HR,OU=Gruppen,DC=firma,DC=local` |
| `AD_GROUP_ADMIN` | vollständiger DN der Gruppe der CrewGate-Administratoren |
| Port `"7101:80"` | links einen anderen Port eintragen, falls 7101 belegt ist |

Die vollständige Liste aller Einstellungen steht in der
[README](../README.md#einstellungen-umgebungsvariablen).

Den Schlüssel `APP_KEY` muss niemand mehr von Hand erzeugen: CrewGate legt ihn
beim ersten Start an und speichert ihn im Datenvolume unter `/data/.app_key`.

### 3.2 Neu starten

```bash
docker compose up -d
```

Wer vorher im Demo-Modus getestet hat und mit einer leeren Datenbank beginnen
möchte, setzt für **einen** Start `FRESH_DB: "1"` und danach sofort wieder
`FRESH_DB: "0"` – sonst wird die Datenbank bei jedem Start gelöscht.

### 3.3 In der Oberfläche einrichten

1. Mit einem Konto aus der Administratoren-Gruppe anmelden.
2. **Einstellungen** öffnen und der Reihe nach ausfüllen:
   - **Firma & Erscheinungsbild:** Firmenname, Logo, Farben
   - **E-Mail-Versand:** SMTP-Server, Absender und den **Portal-Link** – die
     öffentliche Adresse, die in jeder E-Mail steht. Danach ganz unten unter
     **SMTP testen** eine Testmail schicken.
   - **Active Directory:** Suchbasis prüfen
   - **Berechtigungen:** Gruppen für Personalabteilung, Administratoren und
     Auditoren (die Werte hier haben Vorrang vor der Compose-Datei)
   - **Aufbewahrung & automatische Löschung:** Frist mit dem Datenschutz abstimmen
3. **Rollen:** „AD-Gruppen jetzt einlesen“ klicken, die beteiligten
   Abteilungen in den Workflow aufnehmen, Reihenfolge und zentrale
   E-Mail-Adresse je Abteilung eintragen.
4. **Felder** und **Produkte** je Abteilung anlegen – oder die Abteilungen das
   selbst erledigen lassen.
5. Als Mitglied der Personalabteilung einen ersten Vorgang anlegen und einmal
   durchspielen.

## 4. HTTPS einrichten

CrewGate spricht im Container nur HTTP. Für den Betrieb gehört ein Reverse Proxy
mit Zertifikat davor – zum Beispiel nginx, Nginx Proxy Manager, Caddy oder der
Reverse Proxy der Synology. Wie das geht und worauf es ankommt, steht in
[reverse-proxy.md](reverse-proxy.md).

Den Port 7101 nicht im Router oder in der Firewall nach außen freigeben;
erreichbar sein soll nur der Reverse Proxy.

## 5. Prüfliste vor dem Produktivbetrieb

- [ ] `DEMO_MODE: "false"` und `APP_DEBUG: "false"`
- [ ] Anmeldung mit einem echten AD-Konto klappt, Rollen stimmen
- [ ] CrewGate ist nur über HTTPS erreichbar, `APP_URL` beginnt mit `https://`
- [ ] `TRUSTED_PROXIES` enthält die Adresse des Reverse Proxys (Standard: interne Netze)
- [ ] im Ordner `backups` liegt nach der ersten Nacht eine Sicherung
- [ ] der Ordner `backups` und die `docker-compose.yml` werden zusätzlich gesichert (z. B. auf ein zweites Gerät)
- [ ] Impressum und Datenschutz sind unter **Einstellungen** eingetragen, falls nötig
- [ ] eine Testmail kommt an, der Portal-Link in den Einstellungen zeigt auf die HTTPS-Adresse
- [ ] die Löschfrist unter **Einstellungen → Aufbewahrung & automatische Löschung** ist mit dem Datenschutz abgestimmt

## Ausprobieren ohne Compose

Für einen schnellen Blick genügt ein einzelner Container. Alle Daten gehen beim
Beenden verloren.

```bash
docker build -t crewgate:dev .
docker run --rm -p 7101:80 -e DEMO_MODE=true crewgate:dev
```

## Häufige Probleme

| Problem | Lösung |
|---|---|
| Der Bau bricht ab | Meist fehlte kurz das Internet beim Laden der Pakete. `docker compose up -d --build` einfach noch einmal ausführen. |
| Der Container startet nicht, im Protokoll steht „Downgrade abgelehnt“ | Die Datenbank stammt von einer neueren Version als das Abbild. Siehe [betrieb.md](betrieb.md#zurück-zu-einer-älteren-version). |
| „Server Error“ im Browser | Kurz `APP_DEBUG: "true"` setzen, `docker compose up -d`, Fehlermeldung lesen und danach unbedingt wieder `"false"` setzen. |
| Seite ohne Gestaltung (nur Text) | `APP_URL` passt nicht zur aufgerufenen Adresse, siehe [reverse-proxy.md](reverse-proxy.md). |
| Anmeldung schlägt fehl | Mit `LDAP_LOGGING: "true"` stehen die LDAP-Meldungen im Protokoll (`docker compose logs app`). Häufige Ursachen: falscher Basis-DN, Servicekonto gesperrt, Zertifikat des Domain Controllers wird nicht vertraut. |
| „Zu viele Fehlversuche für diesen Benutzernamen“ | Nach 10 falschen Passwörtern ist der Name 30 Minuten gesperrt. Abwarten – oder den Zwischenspeicher leeren: `docker exec crewgate php artisan cache:clear`. |
