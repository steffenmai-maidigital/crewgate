#!/usr/bin/env bash
# CrewGate — Container-Start nach Standard 04:
#   1. .env anlegen   2. Ordner anlegen   3. APP_KEY dauerhaft in /data/.app_key
#   4. FRESH_DB       5. Scheduler: warten, als www-data starten
#   6. App: sicheres Update (app:upgrade), Grunddaten   7. Rechte setzen
# CrewGate — container start according to Standard 04:
#   1. create .env   2. create folders   3. APP_KEY permanently in /data/.app_key
#   4. FRESH_DB      5. scheduler: wait, start as www-data
#   6. app: safe update (app:upgrade), initial data   7. set permissions
set -e
cd /var/www/html

# 1. .env sicherstellen (Umgebungsvariablen aus dem Compose haben Vorrang)
# 1. Make sure .env exists (environment variables from Compose take precedence)
[ -f .env ] || cp .env.example .env

DB_FILE="${DB_DATABASE:-/data/database.sqlite}"
DATA_DIR="$(dirname "$DB_FILE")"
BACKUP_DIR="${BACKUP_PATH:-/backups}"

# Scheduler sicher erkennen — auch wenn SKIP_SETUP in der Compose fehlt:
# schedule:work darf nie Einrichtung oder Migration ausführen
# Detect the scheduler reliably — even if SKIP_SETUP is missing in the Compose file:
# schedule:work must never run setup or migrations
case "$*" in
    *schedule:work*) SKIP_SETUP=1 ;;
esac

# 2. Ordner anlegen (frische Volumes sind leer)
# 2. Create folders (fresh volumes are empty)
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache public/uploads "$DATA_DIR" "$BACKUP_DIR/manual"

# 3. APP_KEY: Ist er nicht gesetzt, wird er einmal erzeugt und im
#    Datenvolume abgelegt. So überleben Anmeldungen jedes Neuerstellen des
#    Containers, und App und Scheduler nutzen denselben Schlüssel.
# 3. APP_KEY: if it is not set, it is generated once and stored in the
#    data volume. That way sign-ins survive every re-creation of the
#    container, and app and scheduler use the same key.
if [ -z "${APP_KEY:-}" ]; then
    KEY_FILE="$DATA_DIR/.app_key"
    if [ "${SKIP_SETUP:-0}" = "1" ]; then
        for i in $(seq 1 60); do [ -s "$KEY_FILE" ] && break; sleep 2; done
    elif [ ! -s "$KEY_FILE" ]; then
        # Bestehende Installationen: einen Schlüssel aus der .env übernehmen,
        # damit niemand abgemeldet wird; sonst einen neuen erzeugen
        # Existing installations: take over a key from the .env,
        # so that nobody is signed out; otherwise generate a new one
        OLD_KEY="$(grep -E '^APP_KEY=base64:' .env 2>/dev/null | head -n1 | cut -d= -f2- || true)"
        if [ -n "$OLD_KEY" ]; then
            echo "$OLD_KEY" > "$KEY_FILE"
        else
            echo "base64:$(head -c 32 /dev/urandom | base64)" > "$KEY_FILE"
        fi
        chmod 600 "$KEY_FILE"
        echo "APP_KEY erzeugt und in $KEY_FILE abgelegt (gehoert in die Sicherung)."
    fi
    if [ ! -s "$KEY_FILE" ]; then
        echo "FEHLER: $KEY_FILE fehlt — der App-Container hat noch keinen Schluessel angelegt. Neuer Versuch beim naechsten Start." >&2
        exit 1
    fi
    export APP_KEY="$(cat "$KEY_FILE")"
    # Auch in die .env des Containers schreiben: „docker exec … php artisan …“
    # (z. B. audit:report) läuft ohne diese Umgebung und braucht den Schlüssel
    # zum Entschlüsseln gespeicherter Passwörter (Verzeichnis, SMTP)
    # Also write it into the container's .env: “docker exec … php artisan …”
    # (e.g. audit:report) runs without this environment and needs the key to
    # decrypt stored passwords (directory, SMTP)
    if grep -q '^APP_KEY=' .env; then
        sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" .env
    else
        echo "APP_KEY=${APP_KEY}" >> .env
    fi
fi

# 4. Einmaliger Reset: FRESH_DB=1 löscht die Datenbank beim Start.
#    Nur für Erstinstallation oder Reparatur — danach sofort wieder auf 0!
# 4. One-off reset: FRESH_DB=1 deletes the database at start.
#    Only for first installation or repair — set it back to 0 right afterwards!
if [ "${FRESH_DB:-0}" = "1" ] && [ "${SKIP_SETUP:-0}" != "1" ]; then
    echo "FRESH_DB=1 gesetzt — Datenbank wird geloescht! Danach FRESH_DB wieder auf 0 stellen."
    rm -f "$DB_FILE" "$DB_FILE-wal" "$DB_FILE-shm"
fi

permissions() {
    chown -R www-data:www-data storage bootstrap/cache public/uploads "$DATA_DIR" "$BACKUP_DIR" 2>/dev/null || true
    chmod -R ug+rwX storage bootstrap/cache public/uploads "$DATA_DIR" "$BACKUP_DIR" 2>/dev/null || true
}

if [ "${SKIP_SETUP:-0}" = "1" ]; then
    # 5. Scheduler: auf die vom App-Container angelegte Datenbank warten
    #    (verhindert SQLite-Sperrkonflikte) und als www-data laufen, damit im
    #    gemeinsamen Storage-Volume keine root-eigenen Dateien entstehen
    # 5. Scheduler: wait for the database created by the app container
    #    (prevents SQLite locking conflicts) and run as www-data so that no
    #    root-owned files are created in the shared storage volume
    permissions
    echo "Warte auf Datenbank ($DB_FILE) …"
    for i in $(seq 1 60); do [ -s "$DB_FILE" ] && break; sleep 2; done
    if command -v runuser >/dev/null 2>&1; then
        exec runuser -u www-data -- "$@"
    fi
    echo "WARNUNG: runuser nicht gefunden — Scheduler laeuft als root." >&2
    exec "$@"
fi

# 6. App-Container: Datenbank anlegen, sicheres Update, Grunddaten
# 6. App container: create the database, safe update, initial data
[ -f "$DB_FILE" ] || touch "$DB_FILE"
permissions

# Kompilierte Vorlagen und Zwischenspeicher liegen im Storage-Volume —
# nach einem Update verwerfen, damit der neue Stand sofort gilt
# Compiled templates and caches live in the storage volume —
# discard them after an update so that the new state applies at once
php artisan view:clear >/dev/null 2>&1 || rm -f storage/framework/views/*.php
php artisan config:clear >/dev/null 2>&1 || true
php artisan route:clear >/dev/null 2>&1 || true

# Sicheres Update: Versionsabgleich (kein Downgrade), Sicherung der
# Datenbank nach /data/backups, Migrationen, Konvertierungen. Schlägt der
# Abgleich fehl, startet der Container NICHT — die Daten bleiben unverändert.
# Safe update: version check (no downgrade), backup of the
# database to /data/backups, migrations, conversions. If the
# check fails, the container does NOT start — the data stays unchanged.
if ! php artisan app:upgrade; then
    echo "FEHLER: Update abgebrochen — Container wird nicht gestartet." >&2
    exit 1
fi

# Erster Administrator als eigenes Konto (nur, wenn noch kein Konto existiert)
# First administrator as an own (local) account (only if no account exists yet)
php artisan db:seed --force

# Demo-Modus: Demo-Konten, Beispiel-Rollen, -Felder und -Vorgänge anlegen
# Demo mode: create demo accounts, sample roles, fields and cases
DEMO=$(echo "${DEMO_MODE:-}" | tr '[:upper:]' '[:lower:]')
if [ "$DEMO" = "true" ] || grep -qi '^DEMO_MODE=true' .env; then
    php artisan demo:seed || true
fi

# 7. Rechte zum Schluss erneut setzen: alles, was Einrichtung und Update
#    als root angelegt haben, gehört ab jetzt www-data
# 7. Set permissions again at the end: everything that setup and update
#    created as root belongs to www-data from now on
permissions

exec "$@"
