# Installing CrewGate with Docker

*Deutsche Fassung: [../docker.md](../docker.md)*

This guide applies to any machine with Docker: a Linux server, a mini PC or –
for trying it out – Docker Desktop on Mac and Windows. There is a separate
guide for Synology and QNAP: [synology.md](synology.md).

CrewGate runs in two containers built from the same image:

| Container | Purpose |
|---|---|
| `crewgate` | web interface on port **7101** |
| `crewgate-scheduler` | scheduled tasks: nightly backup, retention, AD sync, reminders, audit report |

## Requirements

- Docker 24 or newer with Docker Compose (`docker compose version` shows the version)
- at least 1 GB of free RAM and 2 GB of disk space
- internet access **only while building** the image (Composer downloads
  Laravel and other packages). CrewGate does not need the internet at runtime.
- for production: access to a domain controller (LDAP, preferably LDAPS)
- a mail server (SMTP) for notifications

## 1. Get the files

```bash
git clone https://github.com/steffenmai-maidigital/crewgate.git
cd crewgate
mkdir -p backups
```

Without Git: choose **Code → Download ZIP** on GitHub, unpack it, change into
the folder and create a folder called `backups` inside it. The nightly backups
are stored there.

## 2. Start it for a trial

Out of the box CrewGate runs in **demo mode**: without Active Directory, with
sample data and fixed demo accounts. E-mails are not sent but written to the log.

```bash
docker compose up -d --build
docker compose logs -f app
```

The first start builds the image and takes a few minutes. It is ready when the
log shows `resuming normal operations` (leave the log with **Ctrl+C**). Then
open <http://localhost:7101> or `http://<server-ip>:7101` in the browser.

Demo accounts (password `demo` for all):

| Account | Role |
|---|---|
| `anna.hr` | HR – creates cases |
| `max.admin` | administrator – roles, fields, settings |
| `petra.chef`, `karl.chef` | line managers (sales and production) |
| `tim.it`, `eva.einkauf`, `frank.facility` | IT, purchasing and facility departments |
| `rita.audit` | auditor – reads the audit log |

The sample data can be rebuilt at any time:
`docker exec crewgate php artisan demo:seed --fresh`.

## 3. Set it up for production

### 3.1 Adjust the Compose file

All settings are in `docker-compose.yml` under the `app` service. No `.env`
file is needed; the `scheduler` takes over the values automatically.

| Setting | What to enter |
|---|---|
| `DEMO_MODE` | `"false"` |
| `APP_URL` | the address CrewGate will be reached at, e.g. `https://onboarding.firma.de` – without a trailing slash |
| `LDAP_HOST` | name of the domain controller, e.g. `dc01.example.local` |
| `LDAP_PORT` and `LDAP_SSL` | `636` and `"true"` for LDAPS (recommended) – or `389` with `LDAP_TLS: "true"` |
| `LDAP_BASE_DN` | search base, e.g. `DC=example,DC=local` |
| `LDAP_USERNAME`, `LDAP_PASSWORD` | a service account that can only **read** the directory |
| `AD_GROUP_HR` | full DN of the HR group, e.g. `CN=HR,OU=Groups,DC=example,DC=local` |
| `AD_GROUP_ADMIN` | full DN of the CrewGate administrators group |
| Port `"7101:80"` | put another port on the left if 7101 is already in use |

The full list of settings is in the
[README](../../README.en.md#settings-environment-variables).

Nobody needs to create `APP_KEY` by hand any more: CrewGate generates it on the
first start and stores it in the data volume as `/data/.app_key`.

### 3.2 Restart

```bash
docker compose up -d
```

If you tested in demo mode before and want to start with an empty database,
set `FRESH_DB: "1"` for **one** start and change it straight back to
`FRESH_DB: "0"` – otherwise the database is wiped on every start.

### 3.3 Set it up in the web interface

1. Log in with an account from the administrators group.
2. Open **Settings** and fill in, in order:
   - **Company & appearance:** company name, logo, colours
   - **E-mail:** SMTP server, sender and the **portal link** – the public
     address that appears in every e-mail. Then send a test mail under
     **Test SMTP** at the very bottom.
   - **Active Directory:** check the search base
   - **Permissions:** groups for HR, administrators and auditors (values here
     take precedence over the Compose file)
   - **Retention & automatic deletion:** agree the period with data protection
3. **Roles:** click "Read AD groups now", add the departments involved to the
   workflow, set the order and a central e-mail address per department.
4. Create **fields** and **products** per department – or let the departments
   do it themselves.
5. As an HR member, create a first case and walk through it once.

## 4. Set up HTTPS

Inside the container CrewGate only speaks HTTP. In production put a reverse proxy
with a certificate in front of it – for example nginx, Nginx Proxy Manager,
Caddy or the Synology reverse proxy. How to do this and what matters is in
[reverse-proxy.md](reverse-proxy.md).

Do not open port 7101 to the outside in your router or firewall; only the
reverse proxy should be reachable.

## 5. Checklist before going live

- [ ] `DEMO_MODE: "false"` and `APP_DEBUG: "false"`
- [ ] login with a real AD account works and the roles are correct
- [ ] CrewGate is only reachable over HTTPS, `APP_URL` starts with `https://`
- [ ] `TRUSTED_PROXIES` contains the reverse proxy's address (default: internal networks)
- [ ] after the first night there is a backup in the `backups` folder
- [ ] the `backups` folder and `docker-compose.yml` are also backed up elsewhere (e.g. to a second device)
- [ ] imprint and privacy notice are set under **Settings**, if required
- [ ] a test mail arrives and the portal link in the settings points to the HTTPS address
- [ ] the retention period under **Settings → Retention & automatic deletion** has been agreed with data protection

## Trying it without Compose

For a quick look a single container is enough. All data is lost when it stops.

```bash
docker build -t crewgate:dev .
docker run --rm -p 7101:80 -e DEMO_MODE=true crewgate:dev
```

## Common problems

| Problem | Solution |
|---|---|
| The build fails | Usually the internet connection dropped while downloading packages. Simply run `docker compose up -d --build` again. |
| The container does not start, the log says "Downgrade abgelehnt" | The database comes from a newer version than the image. See [operations.md](operations.md#going-back-to-an-older-version). |
| "Server Error" in the browser | Set `APP_DEBUG: "true"` briefly, run `docker compose up -d`, read the error and make sure to set it back to `"false"`. |
| Page without styling (text only) | `APP_URL` does not match the address in the browser, see [reverse-proxy.md](reverse-proxy.md). |
| Login fails | With `LDAP_LOGGING: "true"` the LDAP messages appear in the log (`docker compose logs app`). Common causes: wrong base DN, service account locked, the domain controller's certificate is not trusted. |
| "Too many failed attempts for this user name" | After 10 wrong passwords the name is locked for 30 minutes. Wait – or clear the cache: `docker exec crewgate php artisan cache:clear`. |
