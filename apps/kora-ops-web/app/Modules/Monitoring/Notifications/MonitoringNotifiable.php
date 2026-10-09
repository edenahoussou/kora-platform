<?php

namespace App\Modules\Monitoring\Notifications;

use App\Models\User;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Monitoring\Data\MonitoringPermission;
use Spatie\Health\Notifications\Notifiable;

/**
 * Notifiable des alertes santé : les utilisateurs actifs disposant de la
 * permission monitoring.view. getKey() hérité vaut 1 — le même objet
 * représente toujours le même destinataire collectif.
 */
class MonitoringNotifiable extends Notifiable
{
    /** @return array<int, string> */
    public function routeNotificationForMail(): array
    {
        return User::permission(MonitoringPermission::View->value)
            ->where('status', UserStatus::Active->value)
            ->pluck('email')
            ->all();
    }
}
