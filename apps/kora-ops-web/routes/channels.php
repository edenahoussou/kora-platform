<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Canal privé d'un utilisateur : seul son propriétaire peut s'y abonner
// (MEZ-96). Aucune donnée d'un autre périmètre n'y transite.
Broadcast::channel('ops-notifications.{userUuid}', fn (User $user, string $userUuid): bool => $user->uuid === $userUuid);
