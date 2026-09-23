<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal privé d'un utilisateur : seul son propriétaire peut s'y abonner
// (MEZ-96). Aucune donnée d'un autre périmètre n'y transite.
Broadcast::channel('ops-notifications.{userUuid}', fn (User $user, string $userUuid): bool => $user->uuid === $userUuid);

// Diffusion des positions de la flotte (MEZ-100) : un canal par agence, plus
// un canal `network` pour les rôles à périmètre réseau. Même règle que
// FleetTrackingQuery côté API HTTP — un utilisateur ne peut s'abonner qu'au(x)
// canal(aux) qu'il pourrait de toute façon lire via fleet/tracking.
Broadcast::channel('fleet-tracking.{scope}', function (User $user, string $scope): bool {
    if ($user->can('tracking.view-network')) {
        return true;
    }

    return $user->can('tracking.view-agency') && $user->agency?->uuid === $scope;
});
