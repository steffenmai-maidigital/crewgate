<?php

return [
    // Demo-Modus: keine AD-/LDAP-Verbindung nötig. Anmeldung, Benutzersuche
    // und Gruppen-Sync laufen gegen ein fest verdrahtetes Demo-Verzeichnis.
    // Demo mode: no AD/LDAP connection needed. Sign-in, user search
    // and group sync run against a hard-wired demo directory.
    'enabled' => (bool) env('DEMO_MODE', false),
];
