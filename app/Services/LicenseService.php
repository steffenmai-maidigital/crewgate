<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * Lizenzschlüssel-Schema (20 Zeichen), rein informativ ohne
 * funktionale Auswirkungen:
 *   Stelle 1      : 1 = Standard-Lizenz, 2 = Enterprise-Lizenz
 *   Stellen 2–9   : Kundennummer (8 Zeichen, Buchstaben und Zahlen)
 *   Stellen 10–15 : Support-Ende als Datum JJMMTT (z. B. 260202)
 *   Stellen 16–20 : Lizenzanzahl (5 Ziffern; >= 99999 = "unlimitiert")
 *
 * Licence key schema (20 characters), purely informational without
 * functional effects:
 *   Position 1       : 1 = standard licence, 2 = enterprise licence
 *   Positions 2–9    : customer number (8 characters, letters and digits)
 *   Positions 10–15  : end of support as date YYMMDD (e.g. 260202)
 *   Positions 16–20  : licence count (5 digits; >= 99999 = "unlimited")
 */
class LicenseService
{
    public static function parse(?string $key): ?array
    {
        $key = trim((string) $key);

        if (! preg_match('/^[12][A-Za-z0-9]{8}[0-9]{6}[0-9]{5}$/', $key)) {
            return null;
        }

        $year  = 2000 + (int) substr($key, 9, 2);
        $month = (int) substr($key, 11, 2);
        $day   = (int) substr($key, 13, 2);

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $supportEnd = Carbon::create($year, $month, $day)->endOfDay();
        $count = (int) substr($key, 15, 5);

        return [
            'key'          => $key,
            'name'         => $key[0] === '2' ? 'Enterprise-Lizenz' : 'Standard-Lizenz',
            'customer_no'  => substr($key, 1, 8),
            'support_end'  => $supportEnd,
            'count'        => $count,
            'count_display' => $count >= 99999 ? 'unlimitiert' : (string) $count,
            'expired'      => $supportEnd->isPast(),
        ];
    }

    /** Aktuell gepflegte Lizenz aus den Einstellungen / Currently maintained licence from the settings */
    public static function current(): ?array
    {
        return static::parse(Setting::get('license_key'));
    }
}
