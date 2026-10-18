# Installing CrewGate on Synology (Container Manager) and QNAP

*Deutsche Fassung: [../synology.md](../synology.md)*

> **Names:** On **Synology** the Docker interface is called **Container
> Manager** (DSM 7.2 and newer; the "Docker" package on older DSM versions).
> On **QNAP** the counterpart is **Container Station**. Both are covered here,
> QNAP further down.

## Requirements

- Synology NAS with **DSM 7.2** or newer
- the **Container Manager** package from the Package Center
- at least 2 GB of RAM in the NAS
- Intel/AMD models (e.g. the Plus series) and ARM models are supported; on ARM
  the first build takes considerably longer
- internet access for the NAS **while building**; CrewGate does not need the
  internet at runtime
- for production: network access from the NAS to the domain controller

## 1. Copy the files to the NAS

1. Choose **Code → Download ZIP** on GitHub or download the ZIP of a release.
2. In **File Station**, create a folder `crewgate` inside the shared folder
   `docker`.
3. Upload the ZIP there, right-click → **Extract → Extract here**. Afterwards
   `Dockerfile`, `docker-compose.yml`, `app/` etc. must be **directly** inside
   `docker/crewgate/` – not in a subfolder such as `crewgate-main/`. If they are,
   move the content up one level.
4. Create an empty folder **`backups`** inside `docker/crewgate`. The Synology
   does not create it by itself; without it the project will not start.

## 2. Adjust the settings

For a trial everything can stay as it is: CrewGate starts in demo mode. For
production open `docker-compose.yml` with the DSM **Text Editor** package, or
edit it later in Container Manager under **Project → YAML configuration**:

- `DEMO_MODE: "false"`
- `APP_URL: "https://onboarding.firma.de"` (or for now `http://<NAS-IP>:7101`)
- the `LDAP_*` and `AD_GROUP_*` lines: remove the `#` at the start of the line
  and enter your own values. What goes where is explained in
  [docker.md](docker.md#31-adjust-the-compose-file).
- port `"7101:80"`: choose a free port on the left if 7101 is in use

## 3. Create the project

1. Open **Container Manager** → **Project** → **Create**.
2. **Project name:** `crewgate`
3. **Path:** select the folder `docker/crewgate`.
4. **Source:** "Use existing docker-compose.yml" (it is detected).
5. Skip the **Web portal** step – HTTPS follows in step 5.
6. **Done**. The first start builds the image and takes 5 to 20 minutes
   depending on the NAS. **Project `crewgate` → Log** shows the progress.

It is ready when both containers **`crewgate`** and **`crewgate-scheduler`** are
running (green). Then open `http://<NAS-IP>:7101` in the browser.

## 4. First steps

In demo mode log in as `anna.hr` (HR) or `max.admin` (administrator) with
the password `demo`. For production log in with an account from the
administrators group and fill in **Settings** (company, e-mail with portal link,
permissions), then read the AD groups under **Roles**. The detailed order is
in [docker.md](docker.md#33-set-it-up-in-the-web-interface).

## 5. HTTPS via the DSM reverse proxy

1. **Certificate:** Control Panel → **Security → Certificate** → **Add** →
   "Get a certificate from Let's Encrypt" for `onboarding.firma.de`. The name must
   point to the NAS via DNS and port 80 must be reachable. In a purely internal
   network a certificate from your own certificate authority works too.
2. **Reverse proxy:** Control Panel → **Login Portal → Advanced → Reverse
   Proxy** → **Create**:
   - Source: protocol **HTTPS**, hostname `onboarding.firma.de`, port **443**
   - Destination: protocol **HTTP**, hostname `localhost`, port **7101**
   - tab **Custom Header** → **Create → WebSocket**
3. Under Control Panel → Security → Certificate → **Settings**, assign the
   certificate to the new entry.
4. In `docker-compose.yml` set `APP_URL: "https://onboarding.firma.de"` and rebuild
   the project (**Action → Clean and rebuild**).
5. Open CrewGate at `https://onboarding.firma.de`.

More, including Nginx Proxy Manager:
[reverse-proxy.md](reverse-proxy.md).

## 6. Firewall and port forwarding

- From outside, at most **443** (HTTPS) and, for Let's Encrypt, **80** need to
  be reachable. Do not forward port **7101** in your router.
- In the DSM firewall open port 7101 to the local network at most.

## Update

1. Save your own `docker-compose.yml` (e.g. download it).
2. **Stop** the project.
3. Extract the new files over the old ones in `docker/crewgate` and copy your
   saved `docker-compose.yml` back.
4. Project → **Action → Clean and rebuild**.

On start CrewGate checks the version, backs up the database and applies changes
automatically. The data in the volumes is kept. Details:
[operations.md](operations.md).

## Alternative: via SSH

Enable SSH (Control Panel → **Terminal & SNMP**), then:

```bash
ssh admin@<NAS-IP>
cd /volume1/docker/crewgate
sudo docker compose up -d --build
sudo docker compose logs -f app
```

---

## QNAP Container Station

1. In **File Station** extract the ZIP to e.g. `/Container/crewgate` (files
   again **directly** in the folder) and create the folder `backups` inside it.
2. Adjust `docker-compose.yml` as in step 2.
3. **Container Station** → **Applications** → **Create** → application name
   `crewgate` → paste the content of `docker-compose.yml`. Because CrewGate is
   built on the first start, Container Station needs the folder as build
   context. If that does not work in the interface, SSH is the reliable way:

   ```bash
   ssh admin@<NAS-IP>
   cd /share/Container/crewgate
   docker compose up -d --build
   ```
4. Open `http://<NAS-IP>:7101` and continue with step 4.
5. **HTTPS:** in QTS or QuTS hero create a rule under **Control Panel →
   Network & File Services → Reverse Proxy** from `https://onboarding.firma.de:443`
   to `http://localhost:7101` and assign a certificate.

## Common problems

| Problem | Solution |
|---|---|
| Project cannot be created: "bind source path does not exist" | The `backups` folder is missing in the project folder (step 1.4). |
| The build fails | Check the log. Usually the internet dropped briefly: simply **Create** again. |
| Container does not start, log says "Downgrade abgelehnt" | See [operations.md](operations.md#going-back-to-an-older-version). |
| "Server Error" in the browser | Set `APP_DEBUG: "true"`, rebuild the project, read the error, then set it back to `"false"`. |
| Page without styling or "too many redirects" | [reverse-proxy.md](reverse-proxy.md#common-mistakes) |
| Login fails | Add `LDAP_LOGGING: "true"`, rebuild the project and look at the **Log**. |
| Old view after an update | Reload the page with **Ctrl+F5** or **Shift+Reload**. |
