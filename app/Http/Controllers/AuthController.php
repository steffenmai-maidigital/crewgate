<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use App\Support\Mfa;
use App\Support\Directory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Anmeldung (Standard 09): zuerst eigene Konten, dann — wenn eingeschaltet —
 * das Verzeichnis (Active Directory / LDAP). Verzeichniskonten entstehen bei
 * der ersten Anmeldung; Rechte und Rollen kommen aus den Gruppen. Danach
 * ggf. der zweite Faktor (TOTP) nach der Richtlinie je Gruppe.
 *
 * Sign-in (Standard 09): own accounts first, then — if switched on —
 * the directory (Active Directory / LDAP). Directory accounts are created at
 * the first sign-in; rights and roles come from the groups. After that,
 * if applicable, the second factor (TOTP) according to the policy per group.
 */
class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login', ['directory' => Directory::enabled()]);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'max:200'],
        ]);
        $name = mb_strtolower(trim($data['username']));

        // Bremse gegen Passwort-Raten je Benutzername, unabhängig von der
        // IP-Adresse: 10 Fehlversuche → 30 Minuten gesperrt (Standard 06)
        // Throttle password guessing per username, independent of the
        // IP address: 10 failed attempts → locked for 30 minutes (Standard 06)
        $throttle = 'sign-in:' . $name;
        if (RateLimiter::tooManyAttempts($throttle, 10)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttle) / 60);

            return back()->withErrors(['username' => __('Zu viele Fehlversuche für diesen Benutzernamen. Bitte in :minuten Minuten erneut versuchen.', ['minuten' => $minutes])])->onlyInput('username');
        }
        $fail = function (string $message) use ($throttle) {
            RateLimiter::hit($throttle, 1800);

            return back()->withErrors(['username' => $message])->onlyInput('username');
        };

        $user = User::where('username', $name)->first();
        $groups = [];

        if ($user && $user->isLocal()) {
            // Eigenes Konto: Passwort prüfen
            // Own account: check the password
            if (! $user->password || ! Hash::check($data['password'], $user->password)) {
                return $fail(__('Benutzername oder Passwort falsch.'));
            }
        } else {
            // Verzeichnis (Active Directory / LDAP)
            // Directory (Active Directory / LDAP)
            $directory = Directory::fromSettings();
            if (! $directory) {
                return $fail(__('Benutzername oder Passwort falsch.'));
            }
            try {
                $entry = $directory->authenticate($name, $data['password']);
            } catch (\Throwable $e) {
                report($e);

                return back()->withErrors(['username' => __('Das Verzeichnis ist gerade nicht erreichbar. Bitte später erneut versuchen oder den Administrator informieren.')])->onlyInput('username');
            }
            if (! $entry) {
                return $fail(__('Benutzername oder Passwort falsch.'));
            }

            $user = $this->directoryUser($entry, $entry['username'] ?: $name);
            if (! $user) {
                return $fail(__('Benutzername oder Passwort falsch.'));
            }
            $user->fill([
                'name'        => $entry['name'] ?: $name,
                'email'       => $entry['mail'],
                'department'  => $entry['department'],
                'auth_source' => User::DIRECTORY,
                'ldap_dn'     => $entry['dn'],
                'guid'        => ($entry['guid'] ?? null) ?: $user->guid,
                'active'      => $user->exists ? $user->active : true,
            ])->save();
            $groups = $entry['groups'];
            $user->permissionsFromGroups($groups);
        }

        if (! $user->active) {
            return $fail(__('Dieses Konto ist gesperrt.'));
        }
        RateLimiter::clear($throttle);

        // Zweiter Faktor nach Richtlinie: eingerichtet → Code abfragen
        // Second factor per policy: set up → ask for the code
        $policy = Mfa::policy($user, $groups);
        if ($policy !== Mfa::OFF && $user->hasTwoFactor()) {
            $request->session()->put('login.2fa', [
                'id' => $user->id, 'until' => now()->addMinutes(5)->timestamp,
                'groups' => $groups, 'policy' => $policy,
            ]);

            return redirect()->route('login.code');
        }

        return $this->signIn($request, $user, $groups, $policy);
    }

    public function showTwoFactor(Request $request)
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('login');
        }

        return view('auth.code');
    }

    public function twoFactor(Request $request)
    {
        $request->validate(['code' => ['required', 'string', 'max:20']]);
        $user = $this->pendingUser($request);
        if (! $user) {
            return redirect()->route('login')->withErrors(['username' => __('Bitte melden Sie sich erneut an.')]);
        }

        $key = '2fa:' . $user->id;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $request->session()->forget('login.2fa');

            return redirect()->route('login')->withErrors(['username' => __('Zu viele falsche Codes. Bitte später erneut anmelden.')]);
        }
        if (! $user->verifyTwoFactor($request->input('code'))) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['code' => __('Der Code stimmt nicht.')]);
        }
        RateLimiter::clear($key);

        $p = $request->session()->pull('login.2fa');

        return $this->signIn($request, $user, $p['groups'] ?? [], $p['policy'] ?? Mfa::OPTIONAL);
    }

    /**
     * Sitzung aufbauen — nach Passwort bzw. Verzeichnis und ggf. zweitem Faktor
     *
     * Build the session — after password or directory and, if applicable, second factor
     */
    private function signIn(Request $request, User $user, array $groups, string $policy)
    {
        $user->forceFill(['last_login_at' => now()])->save();

        $request->session()->regenerate();
        $request->session()->put('ldap_user', $user->sessionData($groups) + [
            'mfa'         => $policy,
            // Pflicht ohne eingerichteten zweiten Faktor: erst einrichten (Middleware)
            // Mandatory without a second factor set up: set it up first (middleware)
            'mfa_pending' => $policy === Mfa::REQUIRED && ! $user->hasTwoFactor(),
        ]);
        ActivityLog::write(ActivityLog::TYPE_SIGN_IN, 'Anmeldung (' . ($user->isLocal() ? 'eigenes Konto' : 'Verzeichnis') . ')', $user->username);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Konto zu einem Verzeichniseintrag: zuerst über die unveränderliche
     * Kennung (objectGUID bzw. entryUUID), sonst über den Benutzernamen. So
     * bleibt es dasselbe Konto (Rollen, Zwei-Faktor, Verlauf), wenn das Konto
     * im Verzeichnis umbenannt wird. null = Anmeldung ablehnen (Name gehört
     * einem anderen Konto).
     *
     * Account for a directory entry: first via the immutable id (objectGUID
     * or entryUUID), otherwise via the user name. This keeps the same account
     * (roles, two-factor, history) when the account is renamed in the
     * directory. null = refuse sign-in (the name belongs to another account).
     */
    private function directoryUser(array $entry, string $username): ?User
    {
        $guid = ($entry['guid'] ?? null) ?: null;

        // Eigene Konten tragen nie eine Kennung und werden nie darüber gefunden
        // Own accounts never carry an id and are never found by it
        $user = $guid ? User::where('guid', $guid)->where('auth_source', User::DIRECTORY)->first() : null;
        if ($user) {
            if ($user->username !== $username) {
                // Im Verzeichnis umbenannt: Namen nachziehen, wenn er frei ist
                // Renamed in the directory: follow the name if it is free
                if (User::where('username', $username)->whereKeyNot($user->id)->exists()) {
                    logger()->warning('Verzeichnis-Konto umbenannt, neuer Name gehört schon einem anderen Konto – Anmeldung abgelehnt / '
                        . 'directory account renamed, new name already belongs to another account – sign-in refused',
                        ['user_id' => $user->id, 'old' => $user->username, 'new' => $username]);

                    return null;
                }
                logger()->info('Verzeichnis-Konto umbenannt / directory account renamed',
                    ['user_id' => $user->id, 'old' => $user->username, 'new' => $username]);
                $user->username = $username;
            }

            return $user;
        }

        $user = User::firstOrNew(['username' => $username]);
        if ($user->exists && $user->isLocal()) {
            // gleicher Name wie ein eigenes Konto: das eigene Konto hat Vorrang
            // same name as an own account: the own account takes precedence
            return null;
        }
        if ($user->exists && $guid && $user->guid && $user->guid !== $guid) {
            // Name gehört einem anderen Verzeichnisobjekt (Konto gelöscht und neu
            // angelegt): nicht dessen Rollen und Zwei-Faktor übernehmen
            // Name belongs to another directory object (account deleted and
            // re-created): do not take over its roles and two-factor
            logger()->warning('Verzeichnis-Konto mit gleichem Namen, aber anderer Kennung – Anmeldung abgelehnt, altes Konto bitte löschen / '
                . 'directory account with the same name but another id – sign-in refused, please delete the old account',
                ['user_id' => $user->id, 'username' => $username]);

            return null;
        }

        return $user;
    }

    private function pendingUser(Request $request): ?User
    {
        $p = $request->session()->get('login.2fa');
        if (! $p || ($p['until'] ?? 0) < time()) {
            return null;
        }

        return User::find($p['id']);
    }

    public function logout(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
