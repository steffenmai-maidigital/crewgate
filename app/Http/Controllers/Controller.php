<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function user(): array
    {
        return session('ldap_user', []);
    }

    /**
     * Rollen, deren Felder und Produkte der angemeldete Benutzer pflegen darf:
     * Admins alle, Abteilungs-Benutzer ihre eigenen Rollen, HR zusätzlich die
     * HR-Anlage-Rolle.
     *
     * Roles whose fields and products the signed-in user may maintain:
     * admins all, department users their own roles, HR additionally the
     * HR intake role.
     */
    protected function manageableRoleIds(): array
    {
        $user = $this->user();

        if ($user['is_admin'] ?? false) {
            return \App\Models\Role::pluck('id')->all();
        }

        $ids = $user['role_ids'] ?? [];

        if (($user['is_hr'] ?? false) && ($intake = \App\Models\Role::intake())) {
            $ids[] = $intake->id;
        }

        return array_values(array_unique($ids));
    }
}
