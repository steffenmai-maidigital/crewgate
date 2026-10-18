<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Impressum und Datenschutzerklärung: vom Admin ein- und ausschaltbar,
 * entweder als eigener Text oder als Link auf eine vorhandene Seite
 * (z. B. die Firmen-Homepage). Öffentlich, auch vor der Anmeldung.
 *
 * Imprint and privacy policy: can be switched on and off by the admin,
 * either as own text or as a link to an existing page
 * (e.g. the company homepage). Public, even before signing in.
 */
class LegalPages
{
    public const TYPES = ['imprint', 'privacy'];
    public const MAX = 30000;

    public static function enabled(string $type): bool
    {
        if (Setting::get($type . '_enabled', '0') !== '1') {
            return false;
        }
        return self::link($type) !== null || trim(self::text($type)) !== '';
    }

    public static function text(string $type): string
    {
        return (string) Setting::get($type . '_text', '');
    }

    /**
     * Externe Adresse, wenn die Seite als Link eingestellt ist (nur http/https).
     *
     * External address if the page is set as a link (http/https only).
     */
    public static function link(string $type): ?string
    {
        if (Setting::get($type . '_type', 'text') !== 'link') {
            return null;
        }
        $url = (string) Setting::get($type . '_url', '');
        return preg_match('#^https?://[^\s<>"]+$#i', $url) ? $url : null;
    }

    public static function title(string $type): string
    {
        return $type === 'imprint' ? __('Impressum') : __('Datenschutz');
    }

    /** Adresse für Links in Fußzeile und Anmeldeseite / Address for links in the footer and on the sign-in page */
    public static function url(string $type): string
    {
        return self::link($type) ?? route($type);
    }

    /**
     * Sichtbare Seiten als [type => [title, url, external]]
     *
     * Visible pages as [type => [title, url, external]]
     */
    public static function links(): array
    {
        $out = [];
        foreach (self::TYPES as $type) {
            if (self::enabled($type)) {
                $out[$type] = ['title' => self::title($type), 'url' => self::url($type), 'external' => self::link($type) !== null];
            }
        }
        return $out;
    }

    /**
     * Klartext → sicheres HTML: Zeilen mit „# “ werden Überschriften,
     * Leerzeilen trennen Absätze, Adressen und E-Mails werden zu Links.
     * Alles andere wird maskiert — der Admin kann kein HTML einschleusen.
     *
     * Plain text → safe HTML: lines starting with “# ” become headings,
     * blank lines separate paragraphs, addresses and e-mails become links.
     * Everything else is escaped — the admin cannot inject HTML.
     */
    public static function html(string $text): string
    {
        $blocks = preg_split("/\R{2,}/", trim(str_replace("\r", '', $text))) ?: [];
        $html = '';
        foreach ($blocks as $block) {
            $lines = explode("\n", $block);
            $paragraph = [];
            $flush = function () use (&$paragraph, &$html) {
                if ($paragraph) {
                    $html .= '<p>' . implode('<br>', array_map([self::class, 'line'], $paragraph)) . "</p>\n";
                    $paragraph = [];
                }
            };
            foreach ($lines as $z) {
                if (preg_match('/^(#{1,3})\s+(.+)$/', $z, $m)) {
                    $flush();
                    $h = strlen($m[1]) === 1 ? 'h2' : 'h3';
                    $html .= "<$h>" . e($m[2]) . "</$h>\n";
                } else {
                    $paragraph[] = $z;
                }
            }
            $flush();
        }
        return $html;
    }

    private static function line(string $z): string
    {
        $parts = preg_split('#(https?://[^\s<>"]+[^\s<>".,;:!?)]|[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,})#', $z, -1, PREG_SPLIT_DELIM_CAPTURE);
        $out = '';
        foreach ($parts as $i => $t) {
            if ($i % 2 === 0) {
                $out .= e($t);
            } elseif (str_contains($t, '@') && ! str_starts_with($t, 'http')) {
                $out .= '<a href="mailto:' . e($t) . '">' . e($t) . '</a>';
            } else {
                $out .= '<a href="' . e($t) . '" target="_blank" rel="noopener">' . e($t) . '</a>';
            }
        }
        return $out;
    }

    /**
     * Felder aus dem Einstellungsformular prüfen und speichern.
     * Einschalten geht nur mit Inhalt (Text bzw. Adresse).
     *
     * Check and save the fields from the settings form.
     * Switching on only works with content (text or address).
     */
    public static function save(\Illuminate\Http\Request $request): void
    {
        $rules = [];
        foreach (self::TYPES as $type) {
            $rules[$type . '_type'] = ['nullable', 'in:text,link'];
            $rules[$type . '_text'] = ['nullable', 'string', 'max:' . self::MAX];
            $rules[$type . '_url'] = ['nullable', 'string', 'max:500', 'regex:#^https?://[^\s<>"]+$#i'];
        }
        $data = $request->validate($rules, [
            'imprint_url.regex' => __('Bitte eine vollständige Adresse angeben, z. B. https://www.firma.de/impressum'),
            'privacy_url.regex' => __('Bitte eine vollständige Adresse angeben, z. B. https://www.firma.de/impressum'),
        ]);

        foreach (self::TYPES as $type) {
            $choice = ($data[$type . '_type'] ?? 'text') === 'link' ? 'url' : 'text';
            $content = trim((string) ($data[$type . '_' . $choice] ?? ''));
            if ($request->boolean($type . '_enabled') && $content === '') {
                throw \Illuminate\Validation\ValidationException::withMessages([$type . '_' . $choice => $choice === 'url'
                    ? __(':seite: Bitte die Adresse der Seite angeben oder die Seite ausschalten.', ['seite' => self::title($type)])
                    : __(':seite: Bitte einen Text eingeben oder die Seite ausschalten.', ['seite' => self::title($type)])]);
            }
        }

        foreach (self::TYPES as $type) {
            Setting::set($type . '_enabled', $request->boolean($type . '_enabled') ? '1' : '0');
            Setting::set($type . '_type', ($data[$type . '_type'] ?? 'text') === 'link' ? 'link' : 'text');
            Setting::set($type . '_text', str_replace("\r", '', (string) ($data[$type . '_text'] ?? '')));
            Setting::set($type . '_url', trim((string) ($data[$type . '_url'] ?? '')));
        }
    }

    /**
     * Vorlage für die Datenschutzerklärung — beschreibt, was CrewGate tatsächlich verarbeitet
     *
     * Template for the privacy policy — describes what CrewGate actually processes
     */
    public static function privacyTemplate(): string
    {
        $company = Setting::get('brand_company_name') ?: '[Firma]';

        return <<<TXT
# Datenschutzhinweise zum HR-Prozessportal CrewGate

Diese Hinweise gelten für das interne Portal CrewGate der {$company}. Bitte vor der Veröffentlichung prüfen und ergänzen — Angaben in [eckigen Klammern] müssen ersetzt werden.

## Verantwortlich
[Name und Anschrift der Firma, vertreten durch die Geschäftsführung]
[Telefon, E-Mail]

## Datenschutzbeauftragter
[Name bzw. Stelle, E-Mail]

## Zweck
CrewGate organisiert die Abläufe bei Einstellung, Stellenwechsel und Austritt von Mitarbeitern: Die Personalabteilung legt einen Vorgang an, der Vorgesetzte bereitet ihn vor, die beteiligten Abteilungen (z. B. IT, Einkauf, Facility) bestätigen die Umsetzung.

## Welche Daten verarbeitet werden
- Angaben zum Vorgang: Name, Abteilung, Eintritts- bzw. Austrittsdatum und die Felder, die das Unternehmen dafür eingerichtet hat (z. B. Stellenbezeichnung, Ausstattung, Berechtigungen)
- Anmeldung: Benutzername, Name, Abteilung und Gruppenmitgliedschaften aus dem Active Directory. Passwörter speichert CrewGate nicht.
- Protokoll: wer wann welche Angaben gemacht, quittiert, geändert oder gelöscht hat, und welche E-Mails verschickt wurden
- Technische Daten: Sitzungs-Cookie für die Anmeldung, Fehlerprotokolle

## Wer die Daten sieht
Jede Abteilung sieht nur die Angaben, die sie für ihre Aufgabe braucht. Personalabteilung, Administratoren, Auditoren und der zuständige Vorgesetzte sehen den ganzen Vorgang.

## Wo die Daten liegen
Alle Daten liegen ausschließlich auf einem Server des Unternehmens [Standort]. CrewGate lädt keine Inhalte von fremden Servern und gibt keine Daten an Dritte weiter.

## Rechtsgrundlage
[z. B. § 26 BDSG bzw. Art. 88 DSGVO in Verbindung mit Art. 6 Abs. 1 lit. b DSGVO (Durchführung des Beschäftigungsverhältnisses)]

## Speicherdauer
Abgeschlossene Vorgänge werden nach [6] Monaten automatisch gelöscht. Der Protokolleintrag über die Löschung bleibt als Nachweis erhalten. Sicherungen werden nach [14] Tagen überschrieben.

## Ihre Rechte
Sie haben das Recht auf Auskunft, Berichtigung, Löschung und Einschränkung der Verarbeitung sowie auf Widerspruch. Wenden Sie sich dafür an die Personalabteilung oder den Datenschutzbeauftragten. Außerdem können Sie sich bei der zuständigen Aufsichtsbehörde beschweren: [Landesbehörde].

Stand: [Datum]
TXT;
    }
}
