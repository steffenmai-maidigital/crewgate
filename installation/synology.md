# CrewGate auf Synology (Container Manager) und QNAP installieren

*English version: [en/synology.md](en/synology.md)*

> **Namen:** Bei **Synology** heißt die Docker-Oberfläche **Container
> Manager** (DSM 7.2 und neuer; auf älteren DSM-Versionen das Paket
> „Docker“). Bei **QNAP** heißt das Gegenstück **Container Station**. Beide
> Wege stehen in dieser Anleitung, QNAP weiter unten.

## Voraussetzungen

- Synology NAS mit **DSM 7.2** oder neuer
- Paket **Container Manager** aus dem Paket-Zentrum
- mindestens 2 GB Arbeitsspeicher im NAS
- Intel/AMD-Modelle (z. B. Plus-Serie) und ARM-Modelle werden unterstützt; auf
  ARM dauert der erste Bau deutlich länger
- Internetzugang des NAS **beim Bauen**; im Betrieb braucht CrewGate kein Internet
- für den Normalbetrieb: Netzwerkzugriff vom NAS auf den Domain Controller

## 1. Dateien auf das NAS bringen

1. Auf GitHub **Code → Download ZIP** wählen oder das ZIP eines Releases
   herunterladen.
2. In der **File Station** im freigegebenen Ordner `docker` einen Ordner
   `crewgate` anlegen.
3. Das ZIP dorthin hochladen, Rechtsklick → **Extrahieren → Hier
   extrahieren**. Danach müssen `Dockerfile`, `docker-compose.yml`, `app/`
   usw. **direkt** in `docker/crewgate/` liegen – nicht in einem Unterordner
   wie `crewgate-main/`. Sonst den Inhalt eine Ebene nach oben verschieben.
4. Im Ordner `docker/crewgate` einen leeren Ordner **`backups`** anlegen. Die
   Synology legt ihn nicht selbst an; ohne ihn startet das Projekt nicht.

## 2. Einstellungen anpassen

Zum Ausprobieren kann alles bleiben, wie es ist: CrewGate startet im Demo-Modus.
Für den Normalbetrieb die `docker-compose.yml` mit dem **Text-Editor** des
DSM öffnen (Paket „Text Editor“) oder später im Container Manager unter
**Projekt → YAML konfigurieren** bearbeiten:

- `DEMO_MODE: "false"`
- `APP_URL: "https://onboarding.firma.de"` (oder vorerst `http://<NAS-IP>:7101`)
- die `LDAP_*`- und `AD_GROUP_*`-Zeilen: das `#` am Zeilenanfang entfernen und
  die eigenen Werte eintragen. Was wohin gehört, erklärt
  [docker.md](docker.md#31-compose-datei-anpassen).
- Port `"7101:80"`: links einen freien Port wählen, falls 7101 belegt ist

## 3. Projekt anlegen

1. **Container Manager** öffnen → **Projekt** → **Erstellen**.
2. **Projektname:** `crewgate`
3. **Pfad:** den Ordner `docker/crewgate` wählen.
4. **Quelle:** „Vorhandene docker-compose.yml verwenden“ (wird erkannt).
5. Den Schritt **Webportal** überspringen – HTTPS kommt in Schritt 5.
6. **Fertig**. Der erste Start baut das Abbild und dauert je nach NAS 5 bis
   20 Minuten. Den Fortschritt zeigt **Projekt `crewgate` → Protokoll**.

Fertig ist es, wenn beide Container **`crewgate`** und **`crewgate-scheduler`**
grün laufen. Dann im Browser öffnen: `http://<NAS-IP>:7101`.

## 4. Erste Schritte

Im Demo-Modus mit `anna.hr` (Personalabteilung) oder `max.admin`
(Administrator) und dem Passwort `demo` anmelden. Für den Normalbetrieb mit
einem Konto aus der Administratoren-Gruppe anmelden und die
**Einstellungen** ausfüllen (Firma, E-Mail-Versand mit Portal-Link,
Berechtigungen), dann unter **Rollen** die AD-Gruppen einlesen. Die ausführliche
Reihenfolge steht in [docker.md](docker.md#33-in-der-oberfläche-einrichten).

## 5. HTTPS über den Reverse Proxy des DSM

1. **Zertifikat:** Systemsteuerung → **Sicherheit → Zertifikat** →
   **Hinzufügen** → „Zertifikat von Let's Encrypt abrufen“ für
   `onboarding.firma.de`. Der Name muss per DNS auf das NAS zeigen, und Port 80
   muss erreichbar sein. Im reinen Firmennetz geht auch ein Zertifikat der
   eigenen Zertifizierungsstelle.
2. **Reverse Proxy:** Systemsteuerung → **Anmeldeportal → Erweitert →
   Reverse Proxy** → **Erstellen**:
   - Quelle: Protokoll **HTTPS**, Hostname `onboarding.firma.de`, Port **443**
   - Ziel: Protokoll **HTTP**, Hostname `localhost`, Port **7101**
   - Reiter **Benutzerdefinierte Kopfzeile** → **Erstellen → WebSocket**
3. Unter Systemsteuerung → Sicherheit → Zertifikat → **Einstellungen** dem
   neuen Eintrag das Zertifikat zuordnen.
4. In der `docker-compose.yml` `APP_URL: "https://onboarding.firma.de"` setzen
   und das Projekt neu erstellen (**Aktion → Bereinigen und neu erstellen**).
5. CrewGate über `https://onboarding.firma.de` öffnen.

Mehr dazu, auch für Nginx Proxy Manager:
[reverse-proxy.md](reverse-proxy.md).

## 6. Firewall und Portfreigaben

- Von außen erreichbar sein muss höchstens **443** (HTTPS) und für Let's
  Encrypt **80**. Den Port **7101** nicht im Router freigeben.
- In der DSM-Firewall den Port 7101 höchstens für das lokale Netz öffnen.

## Update

1. Die eigene `docker-compose.yml` sichern (z. B. herunterladen).
2. Projekt **stoppen**.
3. Die neuen Dateien über die alten in `docker/crewgate` entpacken und die
   gesicherte `docker-compose.yml` zurückkopieren.
4. Projekt → **Aktion → Bereinigen und neu erstellen**.

Beim Start prüft CrewGate die Version, sichert die Datenbank und spielt
Änderungen automatisch ein. Die Daten in den Volumes bleiben erhalten.
Ausführlich: [betrieb.md](betrieb.md).

## Alternative: per SSH

SSH aktivieren (Systemsteuerung → **Terminal & SNMP**), dann:

```bash
ssh admin@<NAS-IP>
cd /volume1/docker/crewgate
sudo docker compose up -d --build
sudo docker compose logs -f app
```

---

## QNAP Container Station

1. In der **File Station** das ZIP nach z. B. `/Container/crewgate` entpacken
   (Dateien wieder **direkt** im Ordner) und darin den Ordner `backups`
   anlegen.
2. `docker-compose.yml` wie in Schritt 2 anpassen.
3. **Container Station** → **Anwendungen** → **Erstellen** →
   Anwendungsname `crewgate` → den Inhalt der `docker-compose.yml` einfügen.
   Weil CrewGate beim ersten Start gebaut wird, braucht Container Station den
   Ordner als Bau-Kontext. Klappt das in der Oberfläche nicht, ist SSH der
   verlässliche Weg:

   ```bash
   ssh admin@<NAS-IP>
   cd /share/Container/crewgate
   docker compose up -d --build
   ```
4. `http://<NAS-IP>:7101` öffnen und wie in Schritt 4 weitermachen.
5. **HTTPS:** In QTS bzw. QuTS hero unter **Systemsteuerung → Netzwerk- &
   Dateidienste → Reverse Proxy** eine Regel von
   `https://onboarding.firma.de:443` auf `http://localhost:7101` anlegen und ein
   Zertifikat zuweisen.

## Häufige Probleme

| Problem | Lösung |
|---|---|
| Projekt lässt sich nicht erstellen: „bind source path does not exist“ | Der Ordner `backups` fehlt im Projektordner (Schritt 1.4). |
| Der Bau bricht ab | Protokoll ansehen. Meist fehlte kurz das Internet: einfach erneut **Erstellen**. |
| Container startet nicht, Protokoll meldet „Downgrade abgelehnt“ | Siehe [betrieb.md](betrieb.md#zurück-zu-einer-älteren-version). |
| „Server Error“ im Browser | `APP_DEBUG: "true"` setzen, Projekt neu erstellen, Fehlermeldung lesen, danach wieder `"false"`. |
| Seite ohne Gestaltung oder „Zu viele Weiterleitungen“ | [reverse-proxy.md](reverse-proxy.md#häufige-fehler) |
| Anmeldung schlägt fehl | `LDAP_LOGGING: "true"` ergänzen, Projekt neu erstellen und im **Protokoll** nachsehen. |
| Nach dem Update alte Ansicht | Seite mit **Strg+F5** bzw. **Umschalt+Neu laden** aufrufen. |
