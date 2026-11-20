<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Support\Directory;
use App\Support\Mfa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Benutzerverwaltung (Admin): eigene Konten anlegen, Rechte und Rollen
 * vergeben, Passwörter zurücksetzen, Konten sperren. Verzeichniskonten
 * werden nur angezeigt — ihre Rechte kommen aus den Gruppen. Ein Passwort
 * vom Admin ist ein Startpasswort: Die laufenden Sitzungen der Person enden,
 * bei der nächsten Anmeldung muss sie es ändern (außer im Demo-Modus).
 *
 * User management (admin): create own accounts, assign rights and
 * roles, reset passwords, lock accounts. Directory accounts are only
 * shown — their rights come from the groups. A password from the admin is
 * an initial password: the person's running sessions end, and they must
 * change it at the next sign-in (except in demo mode).
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles')->orderBy('name');
        // „suche“/„bearbeiten“: alte Parameternamen (Lesezeichen) / old parameter names (bookmarks)
        $editId = $request->integer('edit') ?: $request->integer('bearbeiten');
        if ($s = trim((string) $request->input('search', $request->input('suche')))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$s%")
                ->orWhere('username', 'like', "%$s%")
                ->orWhere('email', 'like', "%$s%"));
        }

        return view('admin.users', [
            'users'       => $query->get(),
            'roles'       => Role::where('is_intake', false)->orderBy('sort_order')->get(),
            'directory'   => Directory::enabled(),
            'credentials' => session('credentials'),
            'editing'     => $editId ? User::with('roles')->find($editId) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $password = $data['password'] ?: User::initialPassword();

        $user = new User([
            'username'    => $data['username'],
            'name'        => $data['name'],
            'email'       => $data['email'] ?? null,
            'department'  => $data['department'] ?? null,
            'auth_source' => User::LOCAL,
            'is_hr'       => $request->boolean('is_hr'),
            'is_admin'    => $request->boolean('is_admin'),
            'is_auditor'  => $request->boolean('is_auditor'),
            'can_create'  => $request->boolean('can_create') || $request->boolean('is_hr'),
            'active'      => true,
        ]);
        // Startpasswort vom Admin: bei der ersten Anmeldung ändern (nicht im Demo-Modus)
        // Initial password from the admin: change it at the first sign-in (not in demo mode)
        $user->assignInitialPassword($password);
        $user->save();
        $user->roles()->sync($data['roles'] ?? []);

        ActivityLog::write(ActivityLog::TYPE_USER, 'Eigenes Konto angelegt: ' . $user->username, $this->user()['username'] ?? null);

        return redirect()->route('admin.users')
            ->with('ok', __('Konto :name angelegt.', ['name' => $user->name]))
            ->with('credentials', ['username' => $user->username, 'password' => $password]);
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validated($request, $user);
        $ownId = (int) ($this->user()['id'] ?? 0);

        if ($user->isLocal()) {
            if ($user->id === $ownId && ! $request->boolean('is_admin')) {
                return back()->withErrors(['is_admin' => __('Sie können sich die Admin-Rechte nicht selbst entziehen.')]);
            }
            $user->fill([
                'username'   => $data['username'],
                'name'       => $data['name'],
                'email'      => $data['email'] ?? null,
                'department' => $data['department'] ?? null,
                'is_hr'      => $request->boolean('is_hr'),
                'is_admin'   => $request->boolean('is_admin'),
                'is_auditor' => $request->boolean('is_auditor'),
                'can_create' => $request->boolean('can_create') || $request->boolean('is_hr'),
            ]);
            if ($data['password'] && $user->id === $ownId) {
                // Eigenes Konto: wie „Passwort ändern“ auf der Kontoseite (keine Änderungspflicht)
                // Own account: like "change password" on the account page (no duty to change)
                $user->forceFill(['password' => $data['password'], 'must_change_password' => false]);
            } elseif ($data['password']) {
                // Neues Passwort vom Admin: andere Sitzungen enden, bei der nächsten Anmeldung ändern
                // New password from the admin: other sessions end, change it at the next sign-in
                $user->assignInitialPassword($data['password']);
                \Illuminate\Support\Facades\RateLimiter::clear('sign-in:' . $user->username);
            }
            $user->roles()->sync($data['roles'] ?? []);
        }

        // Sperren und Zwei-Faktor-Ausnahme gelten für beide Herkünfte — Sperren nie fürs eigene Konto
        // Locking and the two-factor exception apply to both origins — never lock your own account
        if ($user->id !== $ownId) {
            $user->active = $request->boolean('active');
        }
        $user->mfa_policy = $data['mfa_policy'] ?: null;
        $user->save();
        if ($user->id === $ownId) {
            // eigene laufende Sitzung bleibt, andere enden / own running session stays, others end
            $request->session()->put('ldap_user.password_fingerprint', $user->passwordFingerprint());
        }

        ActivityLog::write(ActivityLog::TYPE_USER, 'Konto geändert: ' . $user->username, $this->user()['username'] ?? null);

        return redirect()->route('admin.users')->with('ok', __('Konto gespeichert.'));
    }

    public function resetPassword(User $user)
    {
        abort_unless($user->isLocal(), 403, 'Verzeichniskonten haben hier kein Passwort.');
        // Eigenes Konto: Die Sitzung endete sofort, das Startpasswort stünde nirgends mehr
        // Own account: the session would end at once, the initial password would be shown nowhere
        if ($user->id === (int) ($this->user()['id'] ?? 0)) {
            return back()->withErrors(['user' => __('Ihr eigenes Passwort ändern Sie unter „Mein Konto“.')]);
        }
        // Neues Startpasswort: alle Sitzungen der Person enden (Fingerabdruck),
        // bei der nächsten Anmeldung muss sie es ändern (nicht im Demo-Modus)
        // New initial password: all sessions of the person end (fingerprint),
        // they must change it at the next sign-in (not in demo mode)
        $password = User::initialPassword();
        $user->assignInitialPassword($password);
        $user->save();
        \Illuminate\Support\Facades\RateLimiter::clear('sign-in:' . $user->username);

        ActivityLog::write(ActivityLog::TYPE_USER, 'Passwort zurückgesetzt: ' . $user->username, $this->user()['username'] ?? null);

        return redirect()->route('admin.users')
            ->with('ok', __('Neues Passwort für :name vergeben.', ['name' => $user->name]))
            ->with('credentials', ['username' => $user->username, 'password' => $password]);
    }

    /**
     * Zwei-Faktor zurücksetzen (Handy verloren) — die Person richtet ihn danach neu ein
     *
     * Reset two-factor (phone lost) — the person sets it up again afterwards
     */
    public function resetTwoFactor(User $user)
    {
        $user->disableTwoFactor();
        ActivityLog::write(ActivityLog::TYPE_USER, 'Zwei-Faktor zurückgesetzt: ' . $user->username, $this->user()['username'] ?? null);

        return redirect()->route('admin.users')->with('ok', __('Zwei-Faktor-Anmeldung für :name zurückgesetzt.', ['name' => $user->name]));
    }

    public function destroy(User $user)
    {
        if ($user->id === (int) ($this->user()['id'] ?? 0)) {
            return back()->withErrors(['user' => __('Das eigene Konto kann nicht gelöscht werden.')]);
        }
        if ($user->is_admin && User::where('is_admin', true)->where('active', true)->count() <= 1) {
            return back()->withErrors(['user' => __('Der letzte Administrator kann nicht gelöscht werden.')]);
        }
        $name = $user->username;
        $user->delete();
        ActivityLog::write(ActivityLog::TYPE_USER, 'Konto gelöscht: ' . $name, $this->user()['username'] ?? null);

        return redirect()->route('admin.users')->with('ok', __('Konto gelöscht.'));
    }

    private function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'username'   => ['required', 'string', 'min:2', 'max:100', 'regex:/^[a-z0-9._@\-]+$/',
                Rule::unique('users', 'username')->ignore($user?->id)],
            'name'       => ['required', 'string', 'max:150'],
            'email'      => ['nullable', 'email', 'max:255'],
            'department' => ['nullable', 'string', 'max:150'],
            'password'   => ['nullable', 'string', 'min:8', 'max:200'],
            'roles'      => ['nullable', 'array'],
            'roles.*'    => ['integer', Rule::exists('roles', 'id')],
            'mfa_policy' => ['nullable', 'in:' . implode(',', array_keys(Mfa::LEVELS))],
        ], [
            'username.regex' => __('Der Benutzername darf nur Kleinbuchstaben, Ziffern, Punkt, Minus, Unterstrich und @ enthalten.'),
        ]);
        $data['username'] = mb_strtolower($data['username']);
        $data['password'] ??= null;
        $data['mfa_policy'] ??= null;

        return $data;
    }
}
