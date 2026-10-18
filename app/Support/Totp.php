<?php

namespace App\Support;

/**
 * Zeitbasierte Einmal-Codes nach RFC 6238 (SHA1, 6 Stellen, 30 s) —
 * passend für jede Authenticator-App (Google/Microsoft Authenticator,
 * FreeOTP, Aegis, 1Password …).
 *
 * Time-based one-time codes per RFC 6238 (SHA1, 6 digits, 30 s) —
 * suitable for any authenticator app (Google/Microsoft Authenticator,
 * FreeOTP, Aegis, 1Password …).
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function newSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function code(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('J', $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff) % 1000000;
        return str_pad((string) $value, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Prüft den Code mit ±1 Zeitfenster. Gibt den verwendeten Zeitschritt
     * zurück (zum Sperren gegen Wiederverwendung) oder null.
     *
     * Checks the code with a ±1 time window. Returns the time step used
     * (to block reuse) or null.
     */
    public static function verify(?string $secret, string $code, ?int $lastStep = null, ?int $time = null): ?int
    {
        if (! $secret || ! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now = intdiv($time ?? time(), 30);
        foreach ([0, -1, 1] as $d) {
            $step = $now + $d;
            if ($lastStep !== null && $step <= $lastStep) {
                continue;
            }
            if (hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $c) {
            $bits .= str_pad(decbin(strpos(self::ALPHABET, $c)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }
}
