# CrewGate hinter einem Reverse Proxy betreiben (HTTPS)

*English version: [en/reverse-proxy.md](en/reverse-proxy.md)*

CrewGate spricht im Container nur HTTP auf Port 80. Im Betrieb nimmt ein
**Reverse Proxy** die Anfragen über HTTPS entgegen und reicht sie weiter. Diese
Anleitung erklärt, worauf es ankommt, und zeigt die Einrichtung für die
gängigen Proxys.

## Die drei Regeln

1. **`APP_URL` auf die öffentliche Adresse setzen** – mit `https://` und ohne
   Schrägstrich am Ende:

   ```yaml
   APP_URL: "https://onboarding.firma.de"
   ```

   Laravel baut daraus alle Links auf Stylesheet, Bilder und Formulare. Steht
   dort noch die interne Adresse, kommt im Browser zwar die Seite an, aber
   ohne Gestaltung. Beginnt `APP_URL` mit `https://`, erzeugt CrewGate alle
   Links mit https – auch wenn der Proxy intern „http“ meldet.

2. **Header weiterreichen:** Der Proxy muss `Host`, `X-Forwarded-For` und vor
   allem **`X-Forwarded-Proto`** an CrewGate geben. Die meisten Proxys tun das von
   selbst.

3. **Dem Proxy vertrauen:** CrewGate glaubt diese Header nur Proxys aus
   `TRUSTED_PROXIES`. Standard sind die internen Netze
   (`127.0.0.1, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16`). Läuft der Proxy
   im selben Docker-Netz oder im Firmennetz, passt das. Sonst seine Adresse
   eintragen, z. B. `TRUSTED_PROXIES: "203.0.113.10"`.

Nach jeder Änderung an der Compose-Datei: `docker compose up -d` (Synology:
Projekt neu erstellen).

## Synology DSM

Systemsteuerung → **Anmeldeportal → Erweitert → Reverse Proxy** → **Erstellen**:

| Feld | Wert |
|---|---|
| Quelle: Protokoll / Hostname / Port | HTTPS / `onboarding.firma.de` / 443 |
| Ziel: Protokoll / Hostname / Port | HTTP / `localhost` / 7101 |
| Benutzerdefinierte Kopfzeile | **Erstellen → WebSocket** |

Das Zertifikat unter Systemsteuerung → **Sicherheit → Zertifikat →
Einstellungen** dem Eintrag zuordnen. Schritt für Schritt:
[synology.md](synology.md#5-https-über-den-reverse-proxy-des-dsm).

## Nginx Proxy Manager

**Hosts → Proxy Hosts → Add Proxy Host:**

- *Domain Names:* `onboarding.firma.de`
- *Scheme:* `http`, *Forward Hostname / IP:* Adresse des Servers bzw. des
  NAS, *Forward Port:* `7101`
- *Block Common Exploits* einschalten
- Reiter **SSL:** Zertifikat wählen oder anfordern, **Force SSL** und
  **HTTP/2** einschalten
- Reiter **Advanced** (für Uploads): `client_max_body_size 8m;`

Nginx Proxy Manager setzt die nötigen Header selbst.

## nginx

```nginx
server {
    listen 443 ssl http2;
    server_name onboarding.firma.de;

    ssl_certificate     /etc/letsencrypt/live/onboarding.firma.de/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/onboarding.firma.de/privkey.pem;

    client_max_body_size 8m;     # Logo und Produktbilder

    location / {
        proxy_pass http://127.0.0.1:7101;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host  $host;
        proxy_read_timeout 120s;
    }
}

server {
    listen 80;
    server_name onboarding.firma.de;
    return 301 https://$host$request_uri;
}
```

## Caddy

Caddy besorgt sich das Zertifikat selbst. Neben der `docker-compose.yml` eine
Datei `Caddyfile` anlegen:

```
onboarding.firma.de {
    reverse_proxy app:80
}
```

und in der `docker-compose.yml` unter `services:` ergänzen:

```yaml
  caddy:
    image: caddy:2
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./Caddyfile:/etc/caddy/Caddyfile:ro
      - caddy-data:/data
    restart: unless-stopped
```

Unter `volumes:` zusätzlich `caddy-data:` eintragen. Voraussetzung: Der Name
zeigt per DNS auf den Server, und die Ports 80 und 443 sind erreichbar.


## Prüfen

1. Die Seite über `https://onboarding.firma.de` öffnen.
2. **Rechtsklick → Seitenquelltext anzeigen** und nach `app.css` suchen. Der
   Link muss mit `https://onboarding.firma.de/` beginnen.
3. Steht dort `http://localhost:7101` oder `http://…`: `APP_URL` prüfen,
   Container neu starten, im Browser **Strg+F5** drücken.

## Häufige Fehler

| Fehler | Ursache und Lösung |
|---|---|
| Seite ohne Gestaltung | `APP_URL` passt nicht zur aufgerufenen Adresse (Regel 1). |
| „Zu viele Weiterleitungen“ | Zwei Stellen erzwingen HTTPS bzw. leiten hin und her, oft ein vorgeschalteter Proxy, der intern per http weiterreicht, und dahinter Nginx Proxy Manager mit „Force SSL“. HTTPS nur an einer Stelle erzwingen, z. B. in NPM „Force SSL“ ausschalten. |
| Links zeigen auf `http://`, Browser meldet „gemischte Inhalte“ | `X-Forwarded-Proto` fehlt (Regel 2) oder der Proxy steht nicht in `TRUSTED_PROXIES` (Regel 3). Am einfachsten: `APP_URL` mit `https://` setzen. |
| Im Protokoll steht immer dieselbe Absender-IP | Der Proxy steht nicht in `TRUSTED_PROXIES`; die Login-Bremse je Adresse trifft dann alle gemeinsam. Proxy-Adresse eintragen. |
| HTTP 413 beim Hochladen | `client_max_body_size` im Proxy erhöhen (mindestens 8 MB). |
| Aufruf in einem Unterverzeichnis (`https://firma.de/crewgate/`) | wird nicht unterstützt – bitte eine eigene Subdomain verwenden. |

**Portal-Link in den E-Mails:** Unter **Einstellungen → E-Mail-Versand** ebenfalls die öffentliche HTTPS-Adresse eintragen, sonst zeigen die Links in den E-Mails auf die interne Adresse.
