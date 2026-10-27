<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OpsSessionErrorCode;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Exceptions\OpsSessionException;
use App\Modules\IdentityAccess\Models\TerminalP501;

use function Illuminate\Support\enum_value;

/**
 * Règles d'accès OPS Mobile d'un utilisateur : compte actif, rôle terrain,
 * permission de consulter les départs, et terminal affecté à cet utilisateur.
 * Sans effet de bord : les appelants décident de révoquer ou non.
 */
class OpsSessionGuard
{
    /** Permission minimale pour ouvrir une session (tous les rôles terrain la portent). */
    private const string PERMISSION_VIEW_DEPARTURES = 'departures.view';

    /**
     * @throws OpsSessionException
     */
    public function assertUserAllowed(User $user): void
    {
        $status = enum_value($user->status);

        if ($status === UserStatus::Suspended->value) {
            throw new OpsSessionException(OpsSessionErrorCode::AccountSuspended);
        }

        if ($status === UserStatus::Locked->value || ($user->locked_until !== null && $user->locked_until->isFuture())) {
            throw new OpsSessionException(OpsSessionErrorCode::AccountLocked);
        }

        $fieldRoles = array_map(
            static fn (SystemRole $role): string => $role->value,
            SystemRole::fieldRoles(),
        );

        // Rôles système terrain ou rôle custom de catégorie « terrain »
        // (créé via l'écran Rôles) actif.
        $hasFieldRole = $user->hasAnyRole($fieldRoles)
            || $user->roles()
                ->where('category', RoleCategory::Terrain->value)
                ->where('is_active', true)
                ->exists();

        if (! $hasFieldRole) {
            throw new OpsSessionException(OpsSessionErrorCode::RoleNotAuthorized);
        }

        if (! $user->can(self::PERMISSION_VIEW_DEPARTURES)) {
            throw new OpsSessionException(OpsSessionErrorCode::PermissionDenied);
        }
    }

    /**
     * Le compte doit-il passer par un terminal P501 (liaison + session OPS) ?
     *
     * Par défaut seul le chauffeur (`bus_driver`, sans aucun autre rôle
     * terrain) en est dispensé : il travaille depuis son téléphone, sa
     * session est portée par son jeton Sanctum. Un compte à la fois
     * chauffeur ET convoyeur (ou tout autre rôle terrain) reste soumis au
     * terminal. `ops_session.force_terminal_for_all`
     * (`OPS_FORCE_TERMINAL_FOR_ALL=true`) l'impose à tous.
     */
    public function requiresTerminal(User $user): bool
    {
        if ((bool) config('ops_session.force_terminal_for_all', false)) {
            return true;
        }

        if (! $user->hasRole(SystemRole::BusDriver->value)) {
            return true;
        }

        $otherFieldRoles = array_values(array_map(
            static fn (SystemRole $role): string => $role->value,
            array_filter(SystemRole::fieldRoles(), static fn (SystemRole $role): bool => $role !== SystemRole::BusDriver),
        ));

        if ($user->hasAnyRole($otherFieldRoles)) {
            return true;
        }

        // Rôle custom de catégorie « terrain » actif (créé via l'écran Rôles) :
        // on ne sait pas ce qu'il autorise, prudence → terminal.
        return $user->roles()
            ->where('category', RoleCategory::Terrain->value)
            ->where('is_active', true)
            ->where('name', '!=', SystemRole::BusDriver->value)
            ->exists();
    }

    /**
     * @throws OpsSessionException
     */
    public function assertTerminalAssignedTo(TerminalP501 $terminal, User $user): void
    {
        if ($terminal->assigned_user_id !== $user->id) {
            throw new OpsSessionException(OpsSessionErrorCode::TerminalNotAssigned);
        }
    }

    /**
     * Permissions OPS effectives, renvoyées au mobile pour masquer les
     * actions non autorisées (le serveur reste l'autorité à chaque requête).
     *
     * @return array{departures_view: bool, boarding_scan: bool, seat_release: bool}
     */
    public function capabilities(User $user): array
    {
        return [
            'departures_view' => $user->can('departures.view'),
            'boarding_scan' => $user->can('boarding.scan'),
            'seat_release' => $user->can('seat-releases.create'),
        ];
    }
}
