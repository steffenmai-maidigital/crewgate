<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Der Fuchs-Helfer Rua (gemeinsamer Baustein, Standard 03): begrüßt, gibt
 * Tipps passend zu Rolle und Seite und wirbt auf der Anmeldeseite für freie
 * Software. Rua ist ein Wikinger-Fuchs aus einem norwegischen Fjorddorf, den
 * Namen („der Rote“) gaben ihm die Iren. Er duzt, spricht warm und knapp,
 * höchstens ein Seefahrer-Bild je Hinweis; bei Passwörtern, Datenschutz,
 * Löschen und Protokoll ohne Bilder und ohne Witz.
 * Alle Texte laufen durch __(), damit er jede Sprache spricht.
 * Zentral abschaltbar (Einstellung fox_enabled), je Person unter „Hilfe“.
 *
 * The fox helper Rua (shared component, Standard 03): greets, gives tips
 * suited to role and page, and promotes free software on the sign-in
 * page. Rua is a Viking fox from a Norwegian fjord village; the Irish gave
 * him his name (“the red one”). He uses informal address, speaks warmly
 * and briefly, at most one seafaring image per hint; no images or jokes
 * for passwords, data protection, deletion and the log.
 * All texts go through __(), so it speaks every language.
 * Can be switched off centrally (setting fox_enabled), per person under “Help”.
 */
class Fox
{
    /** Soll der Fuchs auf der aktuellen Seite erscheinen? / Should the fox appear on the current page? */
    public static function visible(): bool
    {
        if (Setting::get('fox_enabled', '1') !== '1') {
            return false;
        }
        $u = session('ldap_user');
        if ($u) {
            $account = User::find($u['id'] ?? 0);

            return $account ? $account->showsFox() : true;
        }

        return request()->routeIs('login');
    }

    /**
     * Daten für fox.js — angemeldet oder Gast auf der Anmeldeseite
     *
     * Data for fox.js — signed in, or guest on the sign-in page
     */
    public static function display(): array
    {
        $u = session('ldap_user');

        return $u ? self::data($u, request()->route()?->getName(), (bool) session('ok')) : self::guest();
    }

    /** Anmeldeseite: freie Software, Hilfe beim Anmelden / Sign-in page: free software, help with signing in */
    public static function guest(): array
    {
        $promotion = [
            __('Gut zu wissen: CrewGate ist freie Software. Deine Firma darf sie kostenlos nutzen, anpassen und weitergeben.'),
            __('Alle Daten bleiben auf eurem eigenen Server. Keine Cloud, kein Tracking, keine fremden Dienste.'),
            __('Offener Quellcode heißt: Jeder kann nachsehen, was mit den Daten geschieht.'),
            __('Keine Lizenzkosten, kein Kleingedrucktes — und niemand kann dir den Dienst einfach abschalten.'),
            __('Freie Software lebt vom Mitrudern: Fehlermeldungen und Verbesserungen sind willkommen.'),
            __('CrewGate spricht Deutsch und Englisch — und lernt gern weitere Sprachen dazu.'),
        ];
        $source = ProductInfo::sourceUrl();

        return self::package(
            greeting: self::greeting(null),
            context: [$promotion[array_rand($promotion)]],
            tips: array_merge($promotion, [
                __('Melde dich mit deinem Firmenkonto an — oder mit dem Konto, das dir der Administrator eingerichtet hat.'),
                __('Passwort vergessen? Der Administrator kann dir ein neues Startpasswort vergeben.'),
                __('Dein Passwort kennt nur du. Gib es niemandem weiter, auch mir nicht.'),
                __('Woher ich komme? Aus einem Fjorddorf in Norwegen. Den Namen gaben mir die Iren in Dyflin, dem Wikinger-Dublin: Rua, der Rote.'),
            ]),
            sayings: [],
            praise: null,
            link: $source ? ['text' => __('Zum Quellcode'), 'url' => $source] : null,
        );
    }

    public static function data(array $u, ?string $route, bool $praised): array
    {
        $name = trim(explode(' ', (string) ($u['name'] ?? ''))[0] ?: ($u['username'] ?? ''));
        $hr = $u['is_hr'] ?? false;
        $admin = $u['is_admin'] ?? false;
        $department = ! empty($u['role_ids']);

        $sayings = [
            __('Ein klarer Kurs spart allen Zeit.'),
            __('Gut quittiert ist halb gesegelt.'),
            __('Kleine Schritte, saubere Übergaben — so kommt jeder Neue gut an Land.'),
            __('Fragen kostet nichts: Die Hilfe findest du unten im Footer.'),
            __('Heute ist ein guter Tag, offene Vorgänge in den Hafen zu bringen.'),
            __('Gegenwind gehört dazu. Segel reffen, weiterfahren.'),
        ];

        $tips = [
            __('Tipp: In der Übersicht siehst du zuerst die Aufgaben, die auf dich warten.'),
            __('Tipp: Der Hell/Dunkel-Schalter (◐) sitzt oben rechts, daneben die Sprache.'),
            __('Tipp: Unter „Hilfe“ findest du Version, Handbücher und die Änderungshistorie.'),
        ];
        if ($u['can_create'] ?? false) {
            $tips[] = __('Tipp: „Neuer Vorgang“ legt einen Einstieg, Wechsel oder Austritt an — der Vorgesetzte bekommt automatisch Bescheid.');
            $tips[] = __('Tipp: Viele Vorgänge auf einmal? Der kürzeste Weg ist der CSV-Import unter „Neuer Vorgang“.');
        }
        if ($hr) {
            $tips[] = __('Tipp: Erst wenn alle Abteilungen quittiert haben, erscheint der Vorgang unter „wartet auf Abschluss“.');
            $tips[] = __('Tipp: Fristen je Vorgang lösen automatische Erinnerungen aus (Einstellungen → Erinnerungen).');
        }
        if ($department) {
            $tips[] = __('Tipp: Öffne einen Vorgang, trag deine Angaben ein und quittiere — fertig.');
            $tips[] = __('Tipp: Unter „Felder“ passt du die Angaben an, die deine Abteilung je Vorgang braucht.');
        }
        if ($admin) {
            $tips[] = __('Tipp: Unter „Benutzer“ legst du eigene Konten an — auch ohne Active Directory.');
            $tips[] = __('Tipp: Einstellungen → Anmeldung verbindet CrewGate mit eurem Verzeichnis; „Verbindung testen“ prüft die Angaben.');
            $tips[] = __('Tipp: Logo und Farben eurer Firma stellst du unter Einstellungen → Firma ein.');
            $tips[] = __('Tipp: Die nächtliche Sicherung liegt im Ordner backups. Vor jedem Update: „Jetzt sichern“.');
        }

        $context = match ($route) {
            'dashboard'          => [$hr ? __('Hier siehst du alle Vorgänge nach Status — offen, in Bearbeitung, abgeschlossen.') : __('Hier siehst du, was auf dich wartet. Tipp mich an, wenn du einen Tipp willst.')],
            'onboardings.create' => [__('Tipp den Vorgesetzten ein — ich such ihn im Verzeichnis oder unter den eigenen Konten und übernehme die Abteilung.')],
            'onboardings.import' => [__('CSV-Datei hochladen, Zeilen prüfen, Vorgänge einzeln anlegen. Die Vorlage steht oben zum Herunterladen.')],
            'admin.settings'     => [__('Die Abschnitte sind nummeriert. Unter „Anmeldung“ schaltest du das Verzeichnis ein oder aus.')],
            'admin.users'        => [__('Eigene Konten brauchen kein Verzeichnis. Ein Startpasswort wird einmal angezeigt.')],
            'admin.roles'        => [__('Rollen sind Abteilungen im Workflow. Mit Gruppen-DN oder Gruppenname kommen die Mitglieder aus dem Verzeichnis.')],
            'account'            => [__('Hier kannst du mich auch dauerhaft ausblenden. Kein Groll — ich warte dann im Hafen.')],
            'help'               => [__('Hier liegen die Seekarten: Version, Handbücher, Änderungshistorie. Ganz unten geht’s zum Support.')],
            'logs'               => [__('Das Protokoll ist eine Hash-Kette: Jeder Eintrag sichert den vorherigen ab.')],
            default              => [],
        };

        $praise = [__('Skål! Erledigt.'), __('Gespeichert, fest wie ein Runenstein.'), __('Das hat geklappt. Gute Fahrt!'), __('Sauber vertäut.')];

        return self::package(
            greeting: self::greeting($name),
            context: $context,
            tips: $tips,
            sayings: $sayings,
            // nach Ernstem (Löschen, Passwort …) schlicht, sonst mit Bild – siehe FoxOccasion
            // after serious actions (deleting, password …) plain, otherwise with an image – see FoxOccasion
            praise: $praised ? (session('fox_occasion') === 'serious' ? __('Erledigt.') : $praise[array_rand($praise)]) : null,
            link: null,
        );
    }

    private static function greeting(?string $name): string
    {
        $hour = (int) now()->format('G');
        if ($name === null || $name === '') {
            return match (true) {
                $hour < 11 => __('Guten Morgen und heil dir! Ich bin Rua, ein Fuchs vom Fjord. Wohin soll die Fahrt gehen?'),
                $hour < 18 => __('Heil dir! Ich bin Rua, ein Fuchs vom Fjord. Wohin soll die Fahrt gehen?'),
                default      => __('Guten Abend und heil dir! Ich bin Rua, ein Fuchs vom Fjord. Wohin soll die Fahrt gehen?'),
            };
        }

        return match (true) {
            $hour < 11 => __('Guten Morgen, :name! Willkommen zurück im Hafen.', ['name' => $name]),
            $hour < 18 => __('Heil dir, :name! Wohin soll die Fahrt heute gehen?', ['name' => $name]),
            default      => __('Guten Abend, :name! Noch ein Vorgang, bevor der Anker fällt?', ['name' => $name]),
        };
    }

    private static function package(string $greeting, array $context, array $tips, array $sayings, ?string $praise, ?array $link): array
    {
        return [
            'editor'   => null,
            'book'     => null,
            'greeting' => $greeting,
            'context'  => array_values($context),
            'tips'     => $tips,
            'sayings'  => $sayings,
            'praise'   => $praise,
            'link'     => $link,
            // Fehler und Warten (fox.js) / errors and waiting (fox.js)
            'error'    => self::errorLine(),
            'waiting'  => [__('Die Ruder sind im Wasser …'), __('Einen Moment noch, gleich legen wir an.')],
            'texts'    => [
                'close'  => __('Schließen'),
                'sleep'  => __('Schlafen legen'),
                'wake'   => __('Rua schläft — antippen zum Wecken'),
                'woken'  => __('Gähn … ausgeschlafen. Ich bin wieder an Deck.'),
                'next'   => __('Noch ein Tipp'),
                'fox'    => __('Rua, der Fuchs: antippen für einen Tipp'),
            ],
        ];
    }

    /**
     * Rua zu einer Fehlermeldung: Kam die Seite mit Validierungsfehlern zurück,
     * ein Satz dazu. Auf Anmelde-, Konto-, Passwort- und Zwei-Faktor-Seiten ohne
     * Bild und ohne Witz (docs/RUA.md), sonst Gegenwind statt Drama.
     *
     * Rua on an error message: if the page came back with validation errors,
     * one line about it. On sign-in, account, password and two-factor pages
     * without images or jokes (docs/RUA.md), otherwise headwind, not drama.
     */
    private static function errorLine(): ?string
    {
        $errors = session('errors');
        if (! $errors || ! $errors->any()) {
            return null;
        }
        if (request()->routeIs('login', 'login.*', 'account', 'account.*', 'admin.users', 'admin.users.*')) {
            return __('Das hat nicht gepasst. Prüf deine Angaben und versuch es noch einmal.');
        }
        $lines = [__('Gegenwind. Versuch es nochmal, ich bleib an Deck.'), __('Da fehlt noch etwas. Schau dir das markierte Feld an.')];

        return $lines[array_rand($lines)];
    }
}
