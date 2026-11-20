<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Models\User;
use App\Support\Mfa;
use App\Support\Totp;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Eigenes Konto: Passwort (eigene Konten), Zwei-Faktor-Anmeldung
 *
 * Own account: password (own accounts), two-factor sign-in
 */
class AccountController extends Controller
{
    private function account(): User
    {
        return User::findOrFail($this->user()['id'] ?? 0);
    }

    public function show(Request $request)
    {
        $user = $this->account();
        $setup = null;

        // Einrichtung läuft: Geheimnis liegt (noch unbestätigt) in der Sitzung
        // Setup in progress: the secret is held (not yet confirmed) in the session
        if ($secret = $request->session()->get('2fa.secret')) {
            $uri = Totp::uri($secret, $user->username, (Setting::get('brand_company_name') ?: 'CrewGate') . ' CrewGate');
            $svg = (new Writer(new ImageRenderer(new RendererStyle(220, 1), new SvgImageBackEnd())))->writeString($uri);
            $setup = ['secret' => trim(chunk_split($secret, 4, ' ')), 'qr' => $svg];
        }

        return view('account', [
            'user'       => $user,
            'setup'      => $setup,
            'codes'      => $request->session()->get('2fa.codes'),
            'policy'     => $this->user()['mfa'] ?? Mfa::policy($user),
            'mfaPending' => (bool) ($this->user()['mfa_pending'] ?? false),
            'mustChange' => $user->mustChangePassword(),
        ]);
    }

    public function password(Request $request)
    {
        $user = $this->account();
        abort_unless($user->isLocal(), 403, 'Verzeichniskonten ändern ihr Passwort im Verzeichnis.');
        $data = $request->validate([
            'current'  => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'max:200', 'confirmed'],
        ]);
        if (! Hash::check($data['current'], $user->password)) {
            return back()->withErrors(['current' => __('Das bisherige Passwort stimmt nicht.')]);
        }
        if (Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => __('Bitte ein neues Passwort wählen.')]);
        }
        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        // Alle anderen Sitzungen dieses Kontos enden (Fingerabdruck passt nicht
        // mehr); die laufende bleibt: neuen Fingerabdruck übernehmen, neue Sitzungs-ID
        // All other sessions of this account end (the fingerprint no longer
        // matches); the running one stays: take over the new fingerprint, new session ID
        $request->session()->put('ldap_user.password_fingerprint', $user->passwordFingerprint());
        $request->session()->regenerate();
        ActivityLog::write(ActivityLog::TYPE_USER, 'Eigenes Passwort geändert', $user->username);

        return redirect()->route('account')->with('ok', __('Passwort geändert. Andere Anmeldungen mit diesem Konto sind beendet.'));
    }

    public function startTwoFactor(Request $request)
    {
        abort_unless(($this->user()['mfa'] ?? Mfa::OPTIONAL) !== Mfa::OFF, 403, 'Zwei-Faktor ist für diese Gruppe ausgeschaltet.');
        $request->session()->put('2fa.secret', Totp::newSecret());

        return redirect()->route('account');
    }

    public function confirmTwoFactor(Request $request)
    {
        // wie beim Start: bei Stufe „aus“ keine Einrichtung / as at the start: no set-up at level "off"
        abort_unless(($this->user()['mfa'] ?? Mfa::OPTIONAL) !== Mfa::OFF, 403, 'Zwei-Faktor ist für diese Gruppe ausgeschaltet.');
        $request->validate(['code' => ['required', 'string', 'max:10']]);
        $secret = $request->session()->get('2fa.secret');
        $step = $secret ? Totp::verify($secret, preg_replace('/\s+/', '', $request->input('code'))) : null;
        if ($step === null) {
            return back()->withErrors(['code' => __('Der Code passt nicht. Uhrzeit am Handy prüfen und den aktuellen Code eingeben.')]);
        }

        $user = $this->account();
        $user->forceFill([
            'two_factor_secret'       => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_last_step'    => $step,
        ])->save();
        $request->session()->forget('2fa.secret');
        $request->session()->put('ldap_user.mfa_pending', false);
        ActivityLog::write(ActivityLog::TYPE_USER, 'Zwei-Faktor eingerichtet', $user->username);

        return redirect()->route('account')
            ->with('2fa.codes', $user->newRecoveryCodes())
            ->with('ok', __('Zwei-Faktor-Anmeldung ist eingeschaltet.'));
    }

    public function recoveryCodes(Request $request)
    {
        $user = $this->account();
        if (! $this->passwordOk($request, $user)) {
            return back()->withErrors(['password' => __('Das Passwort stimmt nicht.')]);
        }

        return redirect()->route('account')->with('2fa.codes', $user->newRecoveryCodes());
    }

    public function disableTwoFactor(Request $request)
    {
        $user = $this->account();
        if (($this->user()['mfa'] ?? Mfa::OPTIONAL) === Mfa::REQUIRED) {
            return back()->withErrors(['password' => __('Für Ihre Gruppe ist die Zwei-Faktor-Anmeldung vorgeschrieben.')]);
        }
        if (! $this->passwordOk($request, $user)) {
            return back()->withErrors(['password' => __('Das Passwort stimmt nicht.')]);
        }
        $user->disableTwoFactor();
        ActivityLog::write(ActivityLog::TYPE_USER, 'Zwei-Faktor ausgeschaltet', $user->username);

        return redirect()->route('account')->with('ok', __('Zwei-Faktor-Anmeldung ist ausgeschaltet.'));
    }

    /**
     * Bestätigung sicherheitsrelevanter Schritte: eigene Konten mit Passwort,
     * Verzeichniskonten mit einem aktuellen Code der Authenticator-App.
     *
     * Confirmation of security-relevant steps: own accounts with their password,
     * directory accounts with a current code from the authenticator app.
     */
    private function passwordOk(Request $request, User $user): bool
    {
        // Bremse wie beim Code der Anmeldung: 5 Fehlversuche je Konto, dann 5 Minuten Pause
        // Throttle like the sign-in code: 5 failed attempts per account, then a 5-minute pause
        $key = 'account-confirm:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['password' => __('Zu viele Fehlversuche. Bitte in :minuten Minuten erneut versuchen.', [
                'minuten' => (int) ceil(RateLimiter::availableIn($key) / 60),
            ])]);
        }

        $input = (string) $request->input('password', '');
        if ($user->isLocal()) {
            $ok = $user->password && Hash::check($input, $user->password);
        } else {
            // Ein Code der Authenticator-App gilt nur einmal (last_step wie bei der Anmeldung)
            // A code from the authenticator app is valid only once (last_step as at sign-in)
            $step = $user->hasTwoFactor()
                ? Totp::verify($user->two_factor_secret, (string) preg_replace('/\s+/', '', $input), $user->two_factor_last_step)
                : null;
            $ok = $step !== null;
            if ($ok) {
                $user->forceFill(['two_factor_last_step' => $step])->save();
            }
        }

        $ok ? RateLimiter::clear($key) : RateLimiter::hit($key, 300);

        return $ok;
    }
}
