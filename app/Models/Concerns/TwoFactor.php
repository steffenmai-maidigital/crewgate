<?php

namespace App\Models\Concerns;

use App\Support\Totp;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Zwei-Faktor-Anmeldung (TOTP) für das Benutzermodell — gemeinsamer Baustein
 * (Standard 09). Erwartet die Spalten two_factor_secret (encrypted),
 * two_factor_recovery_codes (encrypted:array), two_factor_confirmed_at,
 * two_factor_last_step und mfa_policy (Ausnahme je Benutzer).
 *
 * Two-factor sign-in (TOTP) for the user model — shared building block
 * (Standard 09). Expects the columns two_factor_secret (encrypted),
 * two_factor_recovery_codes (encrypted:array), two_factor_confirmed_at,
 * two_factor_last_step and mfa_policy (per-user exception).
 */
trait TwoFactor
{
    public function hasTwoFactor(): bool
    {
        return $this->two_factor_secret && $this->two_factor_confirmed_at;
    }

    /**
     * Prüft einen App-Code (mit Schutz vor Wiederverwendung) oder einen Wiederherstellungscode
     *
     * Checks an app code (with protection against reuse) or a recovery code
     */
    public function verifyTwoFactor(string $input): bool
    {
        $input = preg_replace('/\s+/', '', $input);
        if (preg_match('/^\d{6}$/', $input)) {
            $step = Totp::verify($this->two_factor_secret, $input, $this->two_factor_last_step);
            if ($step === null) {
                return false;
            }
            $this->forceFill(['two_factor_last_step' => $step])->save();

            return true;
        }

        $codes = $this->two_factor_recovery_codes ?? [];
        foreach ($codes as $i => $hash) {
            if (Hash::check(Str::lower($input), $hash)) {
                unset($codes[$i]);
                $this->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * Erzeugt neue Wiederherstellungscodes, speichert nur deren Hashes
     *
     * Creates new recovery codes, stores only their hashes
     */
    public function newRecoveryCodes(): array
    {
        $plain = collect(range(1, 8))->map(fn () => Str::lower(Str::random(5) . '-' . Str::random(5)))->all();
        $this->forceFill(['two_factor_recovery_codes' => array_map(fn ($c) => Hash::make($c), $plain)])->save();

        return $plain;
    }

    public function disableTwoFactor(): void
    {
        $this->forceFill([
            'two_factor_secret' => null, 'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
        ])->save();
    }
}
