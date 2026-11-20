# Running CrewGate: updates, backups, restore, emergencies

*Deutsche Fassung: [../betrieb.md](../betrieb.md)*

Applies to Docker in general and to Synology/QNAP. On the NAS via SSH, prefix
each command with `sudo`. The examples assume the Compose project is called
`crewgate` and lives in the folder `crewgate` – the volumes are then called
`crewgate_app-data` and so on (`docker volume ls` shows the actual names).

## What is where?

| Location | Content |
|---|---|
| volume `crewgate_app-data` (`/data`) | database `database.sqlite`, key `.app_key`, backups taken before every update under `backups/` |
| volume `crewgate_app-storage` | sessions, cache, logs |
| volume `crewgate_app-uploads` | company logo and product images |
| folder `./backups` | nightly backups; `manual/` for backups created by hand; each one also contains `.app_key` |
| `docker-compose.yml` | all settings, including AD credentials and SMTP |

The Compose file contains credentials. Back it up, but do not keep it in a
folder that many people can read. The database and backups contain personnel data – only give administrators access to the `backups` folder. CrewGate creates the ZIP files without read access for others (`0640`); use `sudo` to check or copy them on the host.

## Update

**Docker:**

```bash
cd crewgate
git pull                     # or copy the new files over the folder
docker compose up -d --build
docker compose logs -f app
```

**Synology Container Manager:** save your own `docker-compose.yml` → stop the
project → extract the new files over the old ones → copy `docker-compose.yml`
back → **Action → Clean and rebuild**.

Before updating, check `CHANGELOG.md` under **Geändert** (changed) for
anything you need to do (e.g. a new folder or a new setting).

### What happens on start

On every start the app container runs `php artisan app:upgrade`:

| Step | Effect |
|---|---|
| Version check | The version stored in the database is compared with the image version. If the database is newer (**downgrade**), the container does not start – nothing is changed. |
| Backup | Before any change a copy of the database is stored in `/data/backups/` (the 10 newest are kept). |
| Migrations | New tables and columns are created. |
| Conversions | Version-specific data changes run exactly once. |
| Record | Version, build and time are then shown under **Help** and in the audit log. |

### Versions

Version numbers follow the scheme **YYMMDDNN**: date plus a running number for
that day (`26101701` = first version on 17 Oct 2026). Every build is called
`crewgate:latest`; released versions also carry their number. To list the
images:

```bash
docker image ls crewgate
```

### Going back to an older version

Because CrewGate refuses downgrades, going back also needs the database from
before:

1. Stop the containers: `docker compose down`
2. Find and restore the backup taken before the update:

   ```bash
   docker run --rm -v crewgate_app-data:/data alpine ls -1 /data/backups
   docker run --rm -v crewgate_app-data:/data alpine sh -c \
     'cp /data/backups/FILE.sqlite /data/database.sqlite && rm -f /data/database.sqlite-wal /data/database.sqlite-shm'
   ```

3. In `docker-compose.yml` comment out `build: .` for **both** services
   (`# build: .`) and set the old number under `image:`, e.g.
   `image: crewgate:26101701`.
4. `docker compose up -d`

So do not delete old images too early (`docker image prune -a` removes them).

## Backups

- **Automatic:** every night at 2:30 the `crewgate-scheduler` container
  stores a ZIP with the database, the key `.app_key` and uploads, plus a
  SHA-256 checksum, in `./backups`. They are kept for `BACKUP_KEEP_DAYS` days
  (default 14, at least 4).
- **Right now, in the browser:** as administrator under **Settings →
  Backup → Back up now**. These backups are stored in `./backups/manual` and are
  never deleted automatically.
- **Keep a copy elsewhere:** regularly copy the `backups` folder and
  `docker-compose.yml` to a second device, on Synology e.g. with
  **Hyper Backup**; encrypt the target or share it with administrators only.
- There is no download of the backups in the browser; they stay on the server.

A backup contains `database/database.sqlite` (consistent copy, even while
running), `.app_key` and the folder `uploads/` (logo, product images).

**`.app_key` matters:** it encrypts the service account password and the
two-factor secrets. Every backup contains it so that a restore on new hardware
is complete. Whoever can read a backup can decrypt these details as well – make
the `backups` folder accessible to administrators only. If `APP_KEY` is set in
`docker-compose.yml`, there is no `.app_key` file; then back up the Compose file
as well.

To check a backup:

```bash
cd backups && sha256sum -c FILE.zip.sha256
```

## Restore

Replace `FILE.zip` with the name of the backup (for manual backups:
`manual/FILE.zip`).

```bash
cd crewgate
docker compose stop

docker run --rm -v crewgate_app-data:/data -v crewgate_app-uploads:/uploads \
  -v "$PWD/backups:/backups:ro" alpine sh -c "
  set -e; mkdir /tmp/r && cd /tmp/r && unzip -q /backups/FILE.zip
  rm -f /data/database.sqlite /data/database.sqlite-wal /data/database.sqlite-shm
  cp database/database.sqlite /data/database.sqlite
  if [ -f .app_key ]; then cp .app_key /data/.app_key && chmod 600 /data/.app_key; fi
  if [ -d uploads ]; then cp -r uploads/. /uploads/; fi
  echo restored"

docker compose start
```

CrewGate fixes the file permissions itself on start. With the key from the
backup, the service account password and two-factor sign-ins work again at
once; all users sign in once more. Older backups without `.app_key` restore the
data as well; then enter the service account password again under
**Settings → Sign-in**, and two-factor users need “Reset two-factor” under
**Users**.

**Moving to another server:** set up CrewGate there as described in the
installation guide (same `docker-compose.yml`), start it once and then run the
restore as above. Afterwards check `APP_URL` and – if used – the addresses in
the settings.

## Test system

A test system is a second, separate instance for trying out updates and
for training:

1. Copy the project folder, e.g. to `crewgate-test`.
2. In its `docker-compose.yml` change: `container_name` to `crewgate-test` and
   `crewgate-test-scheduler`, `image` to `crewgate:test`, the port to
   `"7102:80"`, `TEST_MODE: "true"` and `APP_URL` accordingly.
3. Point outgoing mail to a test address under **Settings** so that no real
   department receives messages.
4. Run `docker compose up -d --build` in the `crewgate-test` folder (Synology:
   a separate project `crewgate-test`).

The test system shows a red **TESTSYSTEM** banner at the top; all exports are
marked too. To test with real data, restore a production backup as described
above – but change the outgoing mail settings first.

## Emergencies

### No administrator can get in

Create an own admin account or set its password anew (emergency access):

```bash
docker exec crewgate php artisan crewgate:admin-password admin
```

The command prints an initial password; a new one must be chosen at sign-in,
running sessions of this account end. If the account is missing, the command
creates it. If the phone with the authenticator app is gone, `--disable-2fa`
also resets the two-factor sign-in of this account:

```bash
docker exec crewgate php artisan crewgate:admin-password admin --disable-2fa
```

If two-factor sign-in is mandatory for administrators, it is set up again after
signing in. Every call is recorded in the audit log.

For directory accounts: the administrators group is set under **Settings**,
otherwise in `AD_GROUP_ADMIN`. Check that the account is a **direct** member of that group
(enter the full DN, e.g. `CN=CrewGate-Admins,OU=Groups,DC=example,DC=local`). `LDAP_LOGGING: "true"` shows the LDAP messages in the
log (`docker compose logs -f app`).

### A user name is locked

After 10 wrong passwords a user name is locked for 30 minutes. To lift it
immediately:

```bash
docker exec crewgate php artisan cache:clear
```

### Page without styling or "too many redirects"

See [reverse-proxy.md](reverse-proxy.md#common-mistakes).

### Troubleshooting

- Container log: `docker compose logs -f app` (Synology: **Project → Log**)
- Laravel log: `docker exec crewgate tail -n 100 storage/logs/laravel.log`
- For a detailed error in the browser set `APP_DEBUG: "true"` briefly, restart
  and make **sure** to set it back to `"false"`.

### Resetting everything

`FRESH_DB: "1"` in `docker-compose.yml` deletes the database on the next
start. Set it back to `"0"` right away – otherwise it happens on every start.

## Recurring tasks

| When | What |
|---|---|
| monthly | look into the `backups` folder (is there a new backup every night?) and under **Help** |
| before every update | **Backup → Back up now**, read `CHANGELOG.md`, try the update in the test system first |
| yearly | check the groups under **Settings → Permissions** and the members of the roles |

## Useful commands

Each prefixed with `docker exec crewgate`:

| Command | Purpose |
|---|---|
| `php artisan app:upgrade` | run the version check and migrations by hand |
| `php artisan crewgate:admin-password [name] [--disable-2fa]` | emergency access: own admin account with an initial password, optionally without two-factor |
| `php artisan schedule:list` | show scheduled tasks and their next run |
| `php artisan cache:clear` | clear the cache (also lifts login locks) |
| `php artisan ad:sync-groups` | sync roles with the AD groups now |
| `php artisan onboarding:purge` | apply the retention period now |
| `php artisan reminders:send` | send reminders now |
| `php artisan audit:report` | send the audit report now |
| `php artisan demo:seed --fresh` | rebuild demo data (demo mode **only**!) |
