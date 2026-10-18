# 🧭 CrewGate — Community Edition

**Free, self-hosted HR process portal — with or without Active Directory.**
CrewGate turns onboarding, position changes, offboarding and your own process
types into a workflow: HR creates the case, the line manager prepares it, the
departments (IT, purchasing, facility …) acknowledge it one after another, and
HR closes it. The *Community Edition* is the complete open-source version
(AGPL-3.0). All data stays on your own server — one SQLite file, no cloud
service, no tracking.

*Deutsche Fassung: [README.md](README.md)*

---

## Why?

Every joiner and leaver means work for many people: create the account, order
the notebook, set up the desk, revoke access. Without a fixed process things
get lost in e-mails and nobody knows what has already been done. CrewGate
makes it a traceable workflow:

- **Sign-in with or without Active Directory**: the administrator maintains
  own accounts in the interface; in addition every employee can sign in with
  their Windows account (LDAP bind, CrewGate stores no passwords). Both work
  at the same time.
- **Permissions come from groups or from the user management**: roles are
  departments — mapped to AD groups for directory accounts, ticked per own
  account.
- **Need-to-know**: each department only sees its own fields and what was
  explicitly shared with it — in the UI and in e-mails.
- **Tamper-evident audit log**: append-only with a SHA-256 hash chain, CSV
  and PDF export, optional monthly audit report to the auditors.
- **Automatic deletion** of closed cases after a configurable period.

## Features

| Area | What it does |
|---|---|
| Process types | onboarding, position change, offboarding and master data change built in; own types (e.g. "apprentice takeover") with a shortened workflow — departments not involved are neither notified nor asked to acknowledge |
| Workflow | roles with a sort order; preparation by the line manager with save-as-draft, then notification of the departments; acknowledge right from the task list; HR close; workflow graphic on dashboard and case page |
| Line managers | HR enters the manager, the department is read from AD (`department`); managers see all cases of their department and may fill in the steps of all departments |
| Fields | per role and process: select, multi-select, checkbox, date, product selection and free text with regex; HR intake fields; "visible to all departments" |
| Product catalogue | products with image, price, article and manufacturer number; categories with colour and order; maintained by the responsible role itself |
| Visibility | per role "sees data of"; HR, admins, auditors and managers see the whole case |
| E-mails | text e-mail to the central address of the next role with the data visible to it; never direct links, only a generic portal link; templates (DE/EN) editable; mail log with full text and status |
| Deadlines | due dates for managers and departments, automatic reminders |
| Bulk import | CSV import for many cases at once (e.g. an apprentice intake) |
| API v2 | create cases, read status and values — for HR and ITSM systems ([docs/API.md](docs/API.md)) |
| Audit log | who filled in, acknowledged, sent or changed what and when; hash chain; CSV and PDF export; "auditor" role with read-only access |
| Deletion | switchable, 1–120 months after closing, daily at 02:00; every deletion is logged |
| Languages | German and English, switcher with flags; English labels for fields, products and categories; e-mails and exports bilingual |
| Branding | company name, logo, header, accent and footer colour, footer text; glass design, light and dark |
| Imprint and privacy | can be switched on individually, as your own text or a link to the company website; reachable without login, with a template for the privacy notice |
| Help | version, licence, manuals and change history right in the app (footer → **Help**) |
| Sign-in | own accounts (menu **Users**) always; directory optional (Active Directory, Samba AD, OpenLDAP, Univention) with “Test connection”; groups as DN or name, nested AD groups; emergency access via `php artisan crewgate:admin-password` |
| Two-factor | TOTP with authenticator app; per group mandatory, optional or off, directory groups with mandatory, exception per user; recovery codes; reset by administrators |
| Fox helper | Rua the fox (a Viking fox from a Norwegian fjord, named in Ireland), talks informally; greets, gives tips matching role and page, promotes free software on the sign-in page; can be switched off centrally and per person |
| Operations | Docker, SQLite, nightly backup with checksum, safe updates with downgrade protection, test system marker (`TEST_MODE`), demo mode without a directory |
| Security | security headers (CSP, HSTS), password-guessing brake, proxy headers only trusted from internal networks |

## Roles

| Role | May |
|---|---|
| **HR** | create (single or CSV), close, cancel and delete cases; maintain process types and HR intake fields; read the audit log |
| **Creator group** | create cases in addition to HR |
| **Line manager** | see, prepare and submit the cases of their department |
| **Department** (role) | work on and acknowledge its own steps; maintain its own fields and products |
| **Admin** | roles, fields, categories, settings, mail log, API token, licence |
| **Auditor** | read and export the audit log, nothing else |

Assignment works only via AD groups (**Settings → Permissions**, initial
values from `AD_GROUP_HR` and `AD_GROUP_ADMIN`). The full permission matrix is
in [docs/SECURITY.md](docs/SECURITY.md) (German).

## Guides

- [Installing with Docker](installation/en/docker.md) — Linux server, mini PC, Docker Desktop
- [Installing on Synology (Container Manager) and QNAP (Container Station)](installation/en/synology.md)
- [Running behind a reverse proxy](installation/en/reverse-proxy.md) — HTTPS, `APP_URL`, nginx
- [Operations](installation/en/operations.md) — updates, backups, restore, test system
- Manuals (German): [users](docs/USER-GUIDE.md) · [administrators](docs/ADMIN-GUIDE.md) · [architecture](docs/ARCHITECTURE.md) · [security](docs/SECURITY.md)

## Quick start

You need Docker with Compose.

```bash
git clone https://github.com/steffenmai-maidigital/crewgate.git
cd crewgate
mkdir -p backups
docker compose up -d --build
```

CrewGate is then available at <http://localhost:7101>. Out of the box it runs
in **demo mode** (`DEMO_MODE: "true"`): eight demo accounts with sample
cases, no directory needed, e-mails go to the log instead of a mail server.

| Account | Password | Role |
|---|---|---|
| `anna.hr` | `demo` | HR — creates cases |
| `max.admin` | `demo` | admin |
| `petra.chef` | `demo` | line manager (sales) |
| `karl.chef` | `demo` | line manager (production) |
| `tim.it` | `demo` | IT department |
| `eva.einkauf` | `demo` | purchasing department |
| `frank.facility` | `demo` | facility department |

Rebuild the demo data: `docker exec crewgate php artisan demo:seed --fresh`
(eleven sample cases across all process types and stages).

### Production

1. In `docker-compose.yml` set `DEMO_MODE: "false"`. No `.env` file is
   needed. On the first start CrewGate creates the administrator `admin` as
   an own account; the initial password is printed in the container log
   (`docker compose logs app`) or set beforehand as `ADMIN_PASSWORD`.
2. Sign in as `admin` → **Users**: set your own password, create further
   accounts (HR, departments, supervisors) — or enter the directory (Active
   Directory, Samba AD, OpenLDAP, Univention) under **Settings → 3 ·
   Sign-in**, save and “Test connection”. Afterwards every employee signs in
   with their company account; permissions come from the groups under
   **Settings → 4**.
3. **Settings**: company, e-mail with portal link, send a test mail.
4. **Roles**: create manually or “Import groups from the directory”, add
   roles to the workflow, set order and central e-mail address. Own accounts
   are assigned to their departments under **Users**.
5. Create **fields** and **products** per role.
6. Create the first case as HR.

Nobody gets locked out: `docker exec crewgate php artisan crewgate:admin-password`
creates an own admin account or resets its password at any time.

CrewGate generates the `APP_KEY` itself on the first start and stores it in
`/data/.app_key`. Step by step: [installation/en/docker.md](installation/en/docker.md).

Behind a reverse proxy also set `APP_URL` to the public address, see
[installation/en/reverse-proxy.md](installation/en/reverse-proxy.md).

### Settings (environment variables)

| Variable | Default | Meaning |
|---|---|---|
| `DEMO_MODE` | true (Compose) | demo accounts and sample data, no directory |
| `ADMIN_USERNAME` / `ADMIN_PASSWORD` | admin / *(empty = random, printed in the log)* | first administrator as own account, only on the very first start |
| `TEST_MODE` | false | shows "TESTSYSTEM" as a banner, on the support page and on all exports |
| `APP_URL` | http://localhost:7101 | public address; if it starts with `https://`, all links are generated with https |
| `APP_KEY` | *(empty = generated on first start, stored in `/data/.app_key`)* | only set it if a specific key is required |
| `TRUSTED_PROXIES` | internal networks | reverse proxies whose `X-Forwarded-*` headers are trusted; `*` = all (not recommended) |
| `BACKUP_PATH` | /backups | folder for the nightly backup (`./backups` in Compose) |
| `BACKUP_KEEP_DAYS` | 14 | how long nightly backups are kept (at least 4) |
| `SOURCE_URL` | *(empty)* | link to the source code in the footer; point it to your own repo if you change the code (AGPL) |
| `GITHUB_URL`, `PROJECT_URL` | GitHub project, www.mai-digital.de | links under **Help** and in the footer |
| `DB_DATABASE` | /data/database.sqlite | database file in the `app-data` volume |
| `LDAP_HOST`, `LDAP_PORT`, `LDAP_SSL`, `LDAP_TLS` | — | optional initial values for the directory (otherwise everything under **Settings → Sign-in**); a set host enables directory sign-in |
| `LDAP_BASE_DN`, `LDAP_USERNAME`, `LDAP_PASSWORD` | — | search base and service account (initial values) |
| `LDAP_ENABLED`, `LDAP_USER_ATTRIBUTE`, `LDAP_USER_FILTER`, `LDAP_BIND_TEMPLATE`, `LDAP_VERIFY_CERT`, `LDAP_TIMEOUT` | — | further initial values, see `.env.example` |
| `AD_GROUP_HR`, `AD_GROUP_ADMIN` | — | initial HR and admin groups (afterwards in the settings) |
| `FRESH_DB` | 0 | `1` deletes the database at start. Set it back to `0` right away |

SMTP, search base, groups, retention and branding are maintained in the UI
after the first start; the values there take precedence.

### Data and backups

- `./backups` (folder, create it before the first start): every night at
  02:30 a ZIP with the database and uploads plus a SHA-256 checksum; manual
  backups ("Back up now" under **Settings**) in `./backups/manual`
- `app-data` (volume): database `database.sqlite`, key `.app_key` and the
  backups taken before every update under `/data/backups/` (the 10 newest are kept)
- `app-storage` (volume): sessions, logs, cache
- `app-uploads` (volume): company logo and product images

Regular backups, restore and moving: see [installation/en/operations.md](installation/en/operations.md).

### Scheduled tasks

The second container `crewgate-scheduler` runs:

| Time | Command | Task |
|---|---|---|
| daily 02:00 | `onboarding:purge` | deletion after the retention period |
| daily 02:30 | – | backup to `./backups` |
| daily 03:00 | `ad:sync-groups` | sync roles from AD groups |
| daily 07:00 | `reminders:send` | deadline reminders |
| monthly on the 1st, 06:00 | `audit:report` | audit report by e-mail (if enabled) |

Manually: `docker exec crewgate php artisan <command>`.

### Editions and licence

| Edition | What |
|---|---|
| **Community Edition** | the open-source version. Complete, without restrictions and without expiry. |
| **Supported Edition** | the same software with a maintained support licence (Standard or Enterprise). |

The licence is **purely informational and has no functional effect**. Admins
enter it under **Settings → Licence**: licensee and a 20-character key. It is
shown under **Help** in the footer; expired support appears in
red, the application keeps running without restrictions.

| Position | Meaning | Example |
|---|---|---|
| 1 | `1` = Standard, `2` = Enterprise | `1` |
| 2–9 | customer number (8 letters/digits) | `MUSTER01` |
| 10–15 | end of support as `YYMMDD` | `270731` |
| 16–20 | licence count (5 digits, `99999` = unlimited) | `00500` |

## Safe updates

When the app container starts, `php artisan app:upgrade` runs automatically:

1. **Version check** — a downgrade is refused, the container does not start
   and the data stays untouched.
2. **Backup** of the database to `/data/backups/`.
3. **Migrations** and version-specific **conversions**, each exactly once.
4. Version, build and time are recorded and logged.

Procedure and rollback: [installation/en/operations.md](installation/en/operations.md).

## Versions and Docker images

Version numbers follow the scheme **YYMMDDNN**: date plus a running number for
that day. `26101701` is the first version on 17 Oct 2026. Older releases were
numbered 1.0 with a build timestamp (see the [changelog](CHANGELOG.md)).

Every build creates the Docker image **`crewgate:latest`**. A released version
is also tagged with its number, e.g. **`crewgate:26101701`**. For every version
GitHub also builds the image for Intel/AMD and ARM and publishes it as
`ghcr.io/steffenmai-maidigital/crewgate:<version>` and `:latest`.

New versions are published by the maintainers; what changes in each version is
listed in the [CHANGELOG](CHANGELOG.md).

## Development

```bash
docker build -t crewgate:dev .
docker run --rm -p 7101:80 -e DEMO_MODE=true -e APP_DEBUG=true crewgate:dev
```

Layout:

```
app/Models            Onboarding (case), OnboardingStep, OnboardingValue, Role,
                      CustomField, Process, Product, ProductCategory, ActivityLog, MailLog, Setting
app/Services          WorkflowService (state machine), LdapService (user search, groups),
                      DemoDirectory, MailService, ReportService (PDF), UpgradeService, LicenseService
app/Support           Directory (LDAP integration, shared building block), Fox, ProductInfo, LegalPages, ErrorPage, Mfa
app/Http/Controllers  cases, roles, fields, products, settings, audit log, API v2
app/Console/Commands  purge, ad:sync-groups, reminders:send, audit:report, demo:seed, app:upgrade
resources/views       Blade UI, a single stylesheet in public/css/app.css
lang/en.json          English translation (German strings are the keys)
docs/                 manuals (also shown in the app under "Help")
installation/         guides: Docker, Synology, reverse proxy, operations (en/ = English)
```

No build step: no Node and no npm.

### Translating

All UI strings are written in German and serve as keys. The English strings
live in `lang/en.json`. Please add new strings to
`lang/en.json` right away and keep placeholders such as `:name`; before each
version the maintainers check that everything is translated.
Missing strings fall back to German.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Please do not report security issues
publicly, see [SECURITY.md](SECURITY.md).

## Licence

[GNU Affero General Public License v3.0](LICENSE) (AGPL-3.0-or-later).

You may use, modify and share CrewGate freely. If you run a **modified**
version as a web service for others, you must offer its users the source code
of that version.
