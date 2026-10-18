@extends('layout')
@section('title', __('Einstellungen'))
@section('content')
    <h1>{{ __('Einstellungen') }}</h1>

    <form method="post" action="{{ route('admin.settings.update') }}" enctype="multipart/form-data">
        @csrf

        {{-- ===== 1. Firma & Erscheinungsbild / Company & appearance ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">1 · {{ __('Firma & Erscheinungsbild') }}</h2>
            <div class="row">
                <div><label>{{ __('Firmenname (im Header)') }}</label><input type="text" name="brand_company_name" value="{{ $settings['brand_company_name'] }}" placeholder="Musterfirma GmbH"></div>
            </div>
            <label>{{ __('Firmenlogo (PNG, JPG, SVG oder WebP, max. 2 MB)') }}</label>
            @if($settings['brand_logo_path'])
                <p><img src="{{ asset($settings['brand_logo_path']) }}" alt="{{ __('Aktuelles Logo') }}" class="logo-preview"></p>
                <label class="choice"><input type="checkbox" name="remove_logo" value="1"> {{ __('Logo entfernen') }}</label>
            @endif
            <input type="file" name="logo" accept=".png,.jpg,.jpeg,.svg,.webp">
            <div class="row" style="margin-top:.6rem">
                <div style="max-width:200px"><label>{{ __('Header-Farbe') }}</label><input type="color" name="brand_header_color" value="{{ $settings['brand_header_color'] ?: '#14557b' }}"></div>
                <div style="max-width:200px"><label>{{ __('Akzentfarbe (Buttons, Links)') }}</label><input type="color" name="brand_accent_color" value="{{ $settings['brand_accent_color'] ?: '#14557b' }}"></div>
                <div style="max-width:200px"><label>{{ __('Footer-Farbe') }}</label><input type="color" name="brand_footer_color" value="{{ $settings['brand_footer_color'] ?: '#1d2733' }}"></div>
            </div>
            <label>{{ __('Footer-Text') }}</label>
            <input type="text" name="brand_footer_text" value="{{ $settings['brand_footer_text'] }}" placeholder="© Musterfirma GmbH · {{ __('Interner Einstellungsprozess') }}">
            <p class="hint">{{ __('Die Farben gelten im hellen und dunklen Modus. Der Hell/Dunkel-Umschalter (◐) sitzt oben rechts im Header, daneben der Sprachumschalter Deutsch/Englisch.') }}</p>
        </div>

        {{-- ===== 2. E-Mail-Versand / E-mail sending ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">2 · {{ __('E-Mail-Versand (SMTP / SMTPS)') }}</h2>
            <div class="row">
                <div><label>{{ __('Server') }}</label><input type="text" name="smtp_host" value="{{ $settings['smtp_host'] }}" placeholder="mail.firma.local"></div>
                <div style="max-width:120px"><label>{{ __('Port') }}</label><input type="number" name="smtp_port" value="{{ $settings['smtp_port'] ?? 587 }}"></div>
                <div style="max-width:220px">
                    <label>{{ __('Verschlüsselung') }}</label>
                    <select name="smtp_encryption">
                        <option value="tls" @selected(($settings['smtp_encryption'] ?? 'tls') === 'tls')>STARTTLS (Port 587)</option>
                        <option value="smtps" @selected(($settings['smtp_encryption'] ?? '') === 'smtps')>SMTPS (Port 465)</option>
                        <option value="none" @selected(($settings['smtp_encryption'] ?? '') === 'none')>{{ __('Keine') }}</option>
                    </select>
                </div>
            </div>
            <div class="row">
                <div><label>{{ __('Benutzername') }}</label><input type="text" name="smtp_username" value="{{ $settings['smtp_username'] }}" autocomplete="off"></div>
                <div><label>{{ __('Passwort') }} <span class="hint">({{ __('leer lassen = unverändert') }})</span></label><input type="password" name="smtp_password" value="" autocomplete="new-password"></div>
            </div>
            <div class="row">
                <div><label>{{ __('Absenderadresse') }}</label><input type="email" name="smtp_from_address" value="{{ $settings['smtp_from_address'] }}" placeholder="onboarding@firma.local"></div>
                <div><label>{{ __('Absendername') }}</label><input type="text" name="smtp_from_name" value="{{ $settings['smtp_from_name'] }}" placeholder="CrewGate"></div>
            </div>
            <label>{{ __('Portal-Link für E-Mails') }}</label>
            <input type="text" name="portal_url" value="{{ $settings['portal_url'] }}" placeholder="https://onboarding.firma.local">
            <p class="hint">{{ __('Dieser pauschale Link wird in allen E-Mails verwendet (Vorgesetzten-Info und Rollen-Benachrichtigungen). Es wird nie direkt auf einen Vorgang verlinkt. Leer = APP_URL aus der Konfiguration.') }}</p>
        </div>

        {{-- ===== 3. Anmeldung / Sign-in ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">3 · {{ __('Anmeldung') }}</h2>
            <p>{{ __('Eigene Konten (Menü „Benutzer“) funktionieren immer. Zusätzlich kann sich jeder Mitarbeiter mit seinem Konto aus dem Verzeichnis anmelden — Active Directory, Samba AD, OpenLDAP oder Univention.') }}</p>
            @if($demo)
                <div class="flash ok" style="margin-top:.4rem">{{ __('Demo-Modus: Die Anmeldung läuft über die eingebauten Demo-Konten. Ein Verzeichnis wird nicht angesprochen.') }}</div>
            @endif
            <label class="choice" style="font-size:1rem"><input type="checkbox" name="ldap_enabled" value="1" @checked(old('ldap_enabled', $directory['enabled']))> <b>{{ __('Anmeldung über das Verzeichnis einschalten') }}</b></label>
            <div class="row">
                <div><label>{{ __('Server (Hostname oder IP, mehrere mit Komma)') }}</label><input type="text" name="ldap_host" value="{{ old('ldap_host', $directory['host']) }}" placeholder="dc01.firma.local" autocapitalize="none" spellcheck="false"></div>
                <div style="max-width:120px"><label>{{ __('Port') }}</label><input type="number" name="ldap_port" value="{{ old('ldap_port', $directory['port']) }}" min="1" max="65535"></div>
                <div style="max-width:240px">
                    <label>{{ __('Verschlüsselung') }}</label>
                    <select name="ldap_encryption">
                        @foreach(\App\Support\Directory::ENCRYPTION as $value => $label)
                            <option value="{{ $value }}" @selected(old('ldap_encryption', $directory['encryption']) === $value)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label class="choice"><input type="checkbox" name="ldap_certificate" value="1" @checked(old('ldap_certificate', $directory['certificate']))> {{ __('Zertifikat des Servers prüfen (empfohlen; aus bei selbst signierten Zertifikaten im Testnetz)') }}</label>
            <label for="ldap_ca_certificate">{{ __('Eigene Zertifizierungsstelle (CA-Zertifikat im PEM-Format, optional)') }}</label>
            <textarea id="ldap_ca_certificate" name="ldap_ca_certificate" rows="4" spellcheck="false" autocapitalize="none" placeholder="-----BEGIN CERTIFICATE-----&#10;…&#10;-----END CERTIFICATE-----" style="font-family:monospace;font-size:.8rem">{{ old('ldap_ca_certificate', $directory['ca_certificate']) }}</textarea>
            <p class="hint">{{ __('Nötig, wenn das Verzeichnis ein Zertifikat der eigenen Firmen- oder Schul-CA verwendet. Dann die Prüfung eingeschaltet lassen, statt sie abzuschalten.') }}</p>
            <label>{{ __('Suchbasis (Base DN)') }}</label>
            <input type="text" name="ldap_base_dn" value="{{ old('ldap_base_dn', $directory['base_dn']) }}" placeholder="DC=firma,DC=local" autocapitalize="none" spellcheck="false">
            <div class="row">
                <div><label>{{ __('Servicekonto (DN oder Anmeldename, nur lesend)') }}</label><input type="text" name="ldap_bind_dn" value="{{ old('ldap_bind_dn', $directory['bind_dn']) }}" placeholder="CN=svc-crewgate,OU=Service,DC=firma,DC=local" autocomplete="off"></div>
                <div><label>{{ __('Passwort des Servicekontos') }} <span class="hint">({{ __('leer lassen = unverändert') }})</span></label><input type="password" name="ldap_bind_password" value="" autocomplete="new-password"></div>
            </div>
            <details style="margin-top:.6rem">
                <summary style="cursor:pointer;font-size:.9rem">{{ __('Weitere Angaben (meist nicht nötig)') }}</summary>
                <div class="row">
                    <div><label>{{ __('Attribut mit dem Benutzernamen') }}</label><input type="text" name="ldap_user_attribute" value="{{ old('ldap_user_attribute', $directory['user_attribute']) }}" placeholder="sAMAccountName"><p class="hint">{{ __('Active Directory: sAMAccountName · OpenLDAP/Univention: uid') }}</p></div>
                    <div><label>{{ __('Benutzerfilter (LDAP-Filter)') }}</label><input type="text" name="ldap_user_filter" value="{{ old('ldap_user_filter', $directory['user_filter']) }}" placeholder="(&(objectClass=user)(!(userAccountControl:1.2.840.113556.1.4.803:=2)))"><p class="hint">{{ __('Schränkt ein, wer sich anmelden darf, z. B. nur aktive Konten einer OU.') }}</p></div>
                </div>
                <div class="row">
                    <div><label>{{ __('Ohne Servicekonto: Anmeldename nach Vorlage') }}</label><input type="text" name="ldap_bind_template" value="{{ old('ldap_bind_template', $directory['bind_template']) }}" placeholder="%s@firma.local"><p class="hint">{{ __('%s wird durch den eingegebenen Benutzernamen ersetzt, z. B. %s@firma.local oder uid=%s,ou=people,dc=firma,dc=local.') }}</p></div>
                    <div style="max-width:160px"><label>{{ __('Zeitlimit (Sekunden)') }}</label><input type="number" name="ldap_timeout" value="{{ old('ldap_timeout', $directory['timeout']) }}" min="1" max="60"></div>
                </div>
            </details>
            <label>{{ __('Gruppenfilter für den Rollenabgleich (optional)') }}</label>
            <input type="text" name="ldap_group_filter" value="{{ $settings['ldap_group_filter'] }}" placeholder="{{ __('z. B. OB- für Gruppen, die mit OB- beginnen') }}">
            <p class="hint">{{ __('Nach dem Speichern unten „Verbindung testen“ anklicken. Ein eigenes Admin-Konto bleibt als Notzugang immer möglich (docker exec crewgate php artisan crewgate:admin-password).') }}</p>
            <h3 style="margin:1rem 0 .2rem">🔐 {{ __('Zwei-Faktor-Anmeldung') }}</h3>
            <p class="hint" style="margin-top:0">{{ __('Zusätzlicher Code aus einer Authenticator-App — je Gruppe: Pflicht (muss eingerichtet werden), wählbar (jeder kann) oder aus. Gehört jemand zu mehreren Gruppen, gilt die strengste Stufe. Ausnahmen je Benutzer unter „Benutzer“.') }}</p>
            <div class="row">
                @foreach(\App\Support\Mfa::groups() as $mfaKey => [$mfaLabel])
                    <div style="max-width:260px">
                        <label>{{ $mfaLabel }}</label>
                        <select name="mfa_{{ $mfaKey }}">
                            @foreach(\App\Support\Mfa::LEVELS as $level => $levelLabel)
                                <option value="{{ $level }}" @selected(old('mfa_' . $mfaKey, \App\Support\Mfa::level($mfaKey)) === $level)>{{ __($levelLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            <label>{{ __('Verzeichnisgruppen mit Pflicht (eine je Zeile, DN oder Name)') }}</label>
            <textarea name="mfa_required_groups" rows="2" placeholder="Geschaeftsfuehrung&#10;CN=IT-Admins,OU=Gruppen,DC=firma,DC=local">{{ old('mfa_required_groups', \App\Models\Setting::get('mfa_required_groups')) }}</textarea>
            <p class="hint">{{ __('Mitglieder dieser Gruppen müssen den zweiten Faktor einrichten — unabhängig von ihren Rechten in der Anwendung. Wirkt ab der nächsten Anmeldung.') }}</p>
        </div>

        {{-- ===== 4. Berechtigungen / Permissions ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">4 · {{ __('Berechtigungen (Gruppen im Verzeichnis)') }}</h2>
            <p class="hint" style="margin-top:0">{{ __('Gilt für Konten aus dem Verzeichnis: Gruppen als vollständiger DN oder nur als Gruppenname. Bei eigenen Konten vergeben Sie die Rechte unter „Benutzer“.') }}</p>
            <label>{{ __('HR-Gruppe') }}</label>
            <input type="text" name="ad_group_hr" value="{{ $settings['ad_group_hr'] }}" placeholder="CN=HR,OU=Gruppen,DC=firma,DC=local">
            <p class="hint">{{ __('Mitglieder gelten als HR: sehen alle Vorgänge und das Protokoll, dürfen Vorgänge anlegen und pflegen die Anlage-Felder. Leer = Wert aus der Umgebung (AD_GROUP_HR).') }}</p>

            <label>{{ __('Gruppe „Vorgänge anlegen“ (optional)') }}</label>
            <input type="text" name="ad_group_create" value="{{ $settings['ad_group_create'] }}" placeholder="CN=Onboarding-Ersteller,OU=Gruppen,DC=firma,DC=local">
            <p class="hint">{{ __('Zusätzlich zu HR dürfen Mitglieder dieser Gruppe neue Vorgänge anlegen. Leer = nur HR.') }}</p>

            <label>{{ __('Administratoren-Gruppe') }}</label>
            <input type="text" name="ad_group_admin" value="{{ $settings['ad_group_admin'] }}" placeholder="CN=Onboarding-Admins,OU=Gruppen,DC=firma,DC=local">
            <p class="hint">{{ __('Mitglieder verwalten Benutzer, Rollen, Einstellungen, Kategorien sowie alle Felder und Produkte und sehen das Protokoll. Leer = Wert aus der Umgebung (AD_GROUP_ADMIN).') }}</p>

            <label>{{ __('Auditoren-Gruppe (optional)') }}</label>
            <input type="text" name="ad_group_auditor" value="{{ $settings['ad_group_auditor'] }}" placeholder="CN=Auditoren,OU=Gruppen,DC=firma,DC=local">
            <p class="hint">{{ __('Mitglieder sehen das vollständige Protokoll inkl. Löschungen und können es exportieren (lesender Zugriff).') }}</p>

            <p class="hint">{{ __('Änderungen an den Gruppen wirken ab der nächsten Anmeldung der Benutzer. Verschachtelte Gruppen werden bei Active Directory aufgelöst.') }}</p>
        </div>

        {{-- ===== 5. Aufbewahrung & Löschung / Retention & deletion ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">5 · {{ __('Aufbewahrung & automatische Löschung') }}</h2>
            <label class="choice">
                <input type="checkbox" name="retention_enabled" value="1" @checked(($settings['retention_enabled'] ?? '1') !== '0')>
                {{ __('Abgeschlossene Vorgänge automatisch löschen') }}
            </label>
            <div class="row">
                <div style="max-width:220px">
                    <label>{{ __('Löschen nach (Monaten nach Fertigstellung)') }}</label>
                    <input type="number" name="retention_months" value="{{ $settings['retention_months'] ?? 6 }}" min="1" max="120" required>
                </div>
            </div>
            <p class="hint">{{ __('Die Prüfung läuft täglich um 02:00 Uhr. Jede Löschung wird revisionssicher im Protokoll festgehalten (Typ „löschung“); die Protokolleinträge selbst bleiben erhalten.') }}</p>
        </div>

        {{-- ===== 6. Berichte / Reports ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">6 · {{ __('Monatlicher Audit-Bericht') }}</h2>
            <label class="choice">
                <input type="checkbox" name="audit_report_enabled" value="1" @checked(($settings['audit_report_enabled'] ?? '0') === '1')>
                {{ __('Am 1. des Monats (06:00 Uhr) automatisch den PDF-Audit-Bericht per E-Mail an die Auditoren senden') }}
            </label>
            <label>{{ __('Feste Berichtsadresse (optional)') }}</label>
            <input type="email" name="audit_report_email" value="{{ $settings['audit_report_email'] }}" placeholder="revision@firma.local">
            <p class="hint">{{ __('Leer = der Bericht geht an alle Auditoren (eigene Konten mit Auditor-Recht und Mitglieder der Auditoren-Gruppe). Der Bericht enthält Firmendaten, Export-Zeitstempel, alle Protokolleinträge, Löschungen und die Vorgänge der zuletzt eingestellten Mitarbeiter.') }}</p>
        </div>

        {{-- ===== 7. Lizenz / Licence ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">7 · {{ __('Lizenz') }}</h2>
            <div class="row">
                <div><label>{{ __('Firma (Lizenznehmer)') }}</label><input type="text" name="license_company" value="{{ $settings['license_company'] }}" placeholder="Musterfirma GmbH"></div>
                <div><label>{{ __('Lizenzschlüssel (20 Zeichen)') }}</label><input type="text" name="license_key" value="{{ $settings['license_key'] }}" maxlength="20" placeholder="2ABCD123426020299999" style="font-family:monospace"></div>
            </div>
            <p class="hint">{{ __('Schema: Stelle 1 = 1 Standard- / 2 Enterprise-Lizenz · Stellen 2–9 = Kundennummer (Buchstaben/Zahlen) · Stellen 10–15 = Support-Ende als JJMMTT (z. B. 260202) · Stellen 16–20 = Lizenzanzahl (99999 = unlimitiert). Nur schema-konforme Schlüssel werden gespeichert. Die Anzeige erfolgt im Footer unter „Hilfe“ – ein abgelaufener Support wird dort rot angezeigt, hat aber keine funktionalen Auswirkungen.') }}</p>
        </div>

        {{-- ===== 8. E-Mail-Vorlagen / E-mail templates ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">8 · {{ __('E-Mail-Vorlagen') }}</h2>
            <p class="hint">{{ __('Leer lassen = eingebauter Standardtext. Verfügbare Platzhalter:') }}
                <code>{name}</code> <code>{department}</code> <code>{date}</code> <code>{role}</code> <code>{data}</code> <code>{link}</code> <code>{supervisor}</code> <code>{process}</code>.
                {{ __('Die E-Mails werden immer zweisprachig versendet: erst der deutsche, dann der englische Text.') }}</p>

            <label>{{ __('Vorgesetzten-Mail (Deutsch)') }}</label>
            <textarea name="mail_tpl_supervisor_de" rows="7" placeholder="{{ \App\Services\MailService::SUPERVISOR_TEMPLATE_DE }}">{{ $settings['mail_tpl_supervisor_de'] }}</textarea>
            <label>{{ __('Vorgesetzten-Mail (Englisch)') }}</label>
            <textarea name="mail_tpl_supervisor_en" rows="7" placeholder="{{ \App\Services\MailService::SUPERVISOR_TEMPLATE_EN }}">{{ $settings['mail_tpl_supervisor_en'] }}</textarea>

            <label>{{ __('Rollen-Benachrichtigung (Deutsch)') }}</label>
            <textarea name="mail_tpl_role_de" rows="7" placeholder="{{ \App\Services\MailService::ROLE_TEMPLATE_DE }}">{{ $settings['mail_tpl_role_de'] }}</textarea>
            <label>{{ __('Rollen-Benachrichtigung (Englisch)') }}</label>
            <textarea name="mail_tpl_role_en" rows="7" placeholder="{{ \App\Services\MailService::ROLE_TEMPLATE_EN }}">{{ $settings['mail_tpl_role_en'] }}</textarea>
            <p class="hint">{{ __('Der Standardtext (als Platzhalter sichtbar) enthält bereits den Hinweis, sich im Portal einzuloggen und Erhalt und Umsetzung zu quittieren – inklusive des Portal-Links aus Bereich 2.') }}</p>
            <h3 style="margin:.9rem 0 .2rem">{{ __('Abbruch-Mail an den Vorgesetzten') }}</h3>
            <p class="hint" style="margin-top:0">{{ __('Platzhalter: {supervisor}, {name}, {first_name}, {last_name}, {process}, {department}, {date}, {link}') }}</p>
            <div class="row">
                <div><label>{{ __('Deutsch') }}</label><textarea name="mail_tpl_cancel_de" rows="6" placeholder="{{ \App\Services\MailService::CANCEL_TEMPLATE_DE }}">{{ $settings['mail_tpl_cancel_de'] }}</textarea></div>
                <div><label>{{ __('Englisch') }}</label><textarea name="mail_tpl_cancel_en" rows="6" placeholder="{{ \App\Services\MailService::CANCEL_TEMPLATE_EN }}">{{ $settings['mail_tpl_cancel_en'] }}</textarea></div>
            </div>

            <h3 style="margin:.9rem 0 .2rem">{{ __('Erinnerung an den Vorgesetzten (Frist erreicht)') }}</h3>
            <p class="hint" style="margin-top:0">{{ __('Platzhalter: {supervisor}, {name}, {process}, {due_date}, {tasks} (Zusammenfassung der offenen Aufgaben), {link}') }}</p>
            <div class="row">
                <div><label>{{ __('Deutsch') }}</label><textarea name="mail_tpl_reminder_supervisor_de" rows="6" placeholder="{{ \App\Services\MailService::REMINDER_SUPERVISOR_TEMPLATE_DE }}">{{ $settings['mail_tpl_reminder_supervisor_de'] }}</textarea></div>
                <div><label>{{ __('Englisch') }}</label><textarea name="mail_tpl_reminder_supervisor_en" rows="6" placeholder="{{ \App\Services\MailService::REMINDER_SUPERVISOR_TEMPLATE_EN }}">{{ $settings['mail_tpl_reminder_supervisor_en'] }}</textarea></div>
            </div>

            <h3 style="margin:.9rem 0 .2rem">{{ __('Erinnerung an die Abteilungen (Frist erreicht)') }}</h3>
            <p class="hint" style="margin-top:0">{{ __('Platzhalter: {role}, {name}, {process}, {due_date}, {data} (sichtbare Angaben der Rolle), {link}') }}</p>
            <div class="row">
                <div><label>{{ __('Deutsch') }}</label><textarea name="mail_tpl_reminder_role_de" rows="6" placeholder="{{ \App\Services\MailService::REMINDER_ROLE_TEMPLATE_DE }}">{{ $settings['mail_tpl_reminder_role_de'] }}</textarea></div>
                <div><label>{{ __('Englisch') }}</label><textarea name="mail_tpl_reminder_role_en" rows="6" placeholder="{{ \App\Services\MailService::REMINDER_ROLE_TEMPLATE_EN }}">{{ $settings['mail_tpl_reminder_role_en'] }}</textarea></div>
            </div>
        </div>

        {{-- ===== 9. API ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">9 · {{ __('API (v2)') }}</h2>
            <label>{{ __('API-Token') }}</label>
            <input type="text" name="api_token" value="{{ $settings['api_token'] }}" autocomplete="off" style="font-family:monospace" placeholder="{{ __('leer = API deaktiviert') }}">
            <p class="hint">{{ __('Authentifizierung per Header „Authorization: Bearer <Token>" oder „X-Api-Token". Endpunkte: POST /api/v2/vorgaenge (anlegen), GET /api/v2/vorgaenge (Status, Filter status/process), GET /api/v2/vorgaenge/{id} (Status, Quittierungen und gepflegte Werte). Leeres Feld deaktiviert die API. Details im Administratorhandbuch.') }}</p>
        </div>

        {{-- ===== 10. Fuchs-Helfer / Fox helper ===== --}}
        <div class="panel">
            <h2 style="margin-top:0">10 · 🦊 {{ __('Fuchs-Helfer') }}</h2>
            <p>{{ __('Ein freundlicher Fuchs unten rechts begrüßt, gibt Tipps passend zur Rolle und zur Seite und wirbt auf der Anmeldeseite für freie Software.') }}</p>
            <label class="choice"><input type="checkbox" name="fox_enabled" value="1" @checked(old('fox_enabled', ($settings['fox_enabled'] ?? '1') === '1'))> <b>{{ __('Fuchs für alle anzeigen') }}</b></label>
            <p class="hint">{{ __('Ausgeschaltet ist er für niemanden sichtbar. Eingeschaltet kann ihn jede Person unter „Hilfe“ für sich selbst ausblenden.') }}</p>
        </div>

        @include('partials.legal-settings', ['number' => '11'])

        <button class="primary">{{ __('Einstellungen speichern') }}</button>
    </form>

    <div class="panel" style="margin-top:1.2rem">
        <h2 style="margin-top:0">{{ __('Verbindung zum Verzeichnis testen') }}</h2>
        <form method="post" action="{{ route('admin.settings.testldap') }}">
            @csrf
            <button class="primary">{{ __('Verbindung testen') }}</button>
        </form>
        <p class="hint">{{ __('Prüft mit den gespeicherten Angaben aus Abschnitt 3: Server erreichbar, Verschlüsselung, Servicekonto, Suchbasis lesbar.') }}</p>
    </div>

    <div class="panel" style="margin-top:1.2rem">
        <h2 style="margin-top:0">{{ __('API-Token neu generieren') }}</h2>
        <form method="post" action="{{ route('admin.settings.apitoken') }}" onsubmit="return confirm('{{ __('Neues Token generieren? Das bisherige Token wird sofort ungültig.') }}')">
            @csrf
            <button class="primary">{{ __('Neues Token generieren') }}</button>
        </form>
        <p class="hint">{{ __('Erzeugt ein zufälliges 48-Zeichen-Token und speichert es sofort. Alternativ kann das Token oben manuell geändert werden.') }}</p>
    </div>

    <div class="panel" style="margin-top:1.2rem">
        <h2 style="margin-top:0">{{ __('Sicherung') }}</h2>
        <p class="hint">{{ __('Jede Nacht um 02:30 Uhr wird eine Sicherung von Datenbank und hochgeladenen Bildern im Sicherungsordner abgelegt und :tage Tage aufbewahrt. „Jetzt sichern“ legt zusätzlich eine Sicherung im Unterordner manual ab; diese wird nie automatisch gelöscht.', ['tage' => config('backup.days')]) }}</p>
        <form method="post" action="{{ route('admin.backup.now') }}">
            @csrf
            <button class="primary">{{ __('Jetzt sichern') }}</button>
        </form>
    </div>

    <div class="panel" style="margin-top:1.2rem">
        <h2 style="margin-top:0">{{ __('SMTP testen') }}</h2>
        <form method="post" action="{{ route('admin.settings.testmail') }}">
            @csrf
            <div class="row">
                <div><label>{{ __('Empfänger') }}</label><input type="email" name="to" required placeholder="vorname.nachname@firma.local"></div>
                <div style="flex:0"><button class="primary" style="margin-top:1.7rem">{{ __('Testmail senden') }}</button></div>
            </div>
        </form>
    </div>
@endsection
