<?php

// Startwerte für die Anmeldung über ein Verzeichnis (Active Directory, Samba,
// OpenLDAP …). Gepflegt wird alles in der Oberfläche unter Einstellungen →
// Anmeldung; die Werte aus der Tabelle settings haben Vorrang. Siehe
// App\Support\Directory (Standard 09).
// Initial values for sign-in via a directory (Active Directory, Samba,
// OpenLDAP …). Everything is maintained in the interface under Settings →
// Sign-in; the values from the settings table take precedence. See
// App\Support\Directory (Standard 09).
return [
    // null = eingeschaltet, sobald ein Host gesetzt ist und kein Demo-Modus läuft
    // null = enabled as soon as a host is set and demo mode is not active
    'enabled'        => env('LDAP_ENABLED') === null ? null : filter_var(env('LDAP_ENABLED'), FILTER_VALIDATE_BOOLEAN),
    'demo'           => filter_var(env('DEMO_MODE', false), FILTER_VALIDATE_BOOLEAN),
    'host'           => env('LDAP_HOST', ''),
    'port'           => env('LDAP_PORT', filter_var(env('LDAP_SSL', false), FILTER_VALIDATE_BOOLEAN) ? 636 : 389),
    'encryption'     => filter_var(env('LDAP_SSL', false), FILTER_VALIDATE_BOOLEAN) ? 'ldaps'
                        : (filter_var(env('LDAP_TLS', true), FILTER_VALIDATE_BOOLEAN) ? 'starttls' : 'none'),
    'base_dn'        => env('LDAP_BASE_DN', ''),
    'bind_dn'        => env('LDAP_USERNAME', ''),
    'bind_password'  => env('LDAP_PASSWORD', ''),
    // ohne Servicekonto: Anmeldename nach Vorlage, z. B. "%s@firma.local"
    // without a service account: sign-in name from a template, e.g. "%s@firma.local"
    'bind_template'  => env('LDAP_BIND_TEMPLATE', ''),
    'user_attribute' => env('LDAP_USER_ATTRIBUTE', 'sAMAccountName'),
    'user_filter'    => env('LDAP_USER_FILTER', ''),
    'certificate'    => filter_var(env('LDAP_VERIFY_CERT', true), FILTER_VALIDATE_BOOLEAN),
    'timeout'        => (int) env('LDAP_TIMEOUT', 5),
];
