<?php

namespace App\Modules\Notifications\Services;

use App\Models\User;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Models\OpsNotificationPreference;

/**
 * Préférences de notification par utilisateur, type d'événement et canal
 * (in_app / mail). Sans ligne, tout est activé. Une alerte critique ne peut
 * jamais être désactivée.
 */
class OpsNotificationPreferences
{
    public function allows(User $user, OpsNotificationType $type, OpsNotificationChannel $channel): bool
    {
        if (! $type->isDisableable()) {
            return true;
        }

        $preference = OpsNotificationPreference::query()
            ->where('user_id', $user->id)
            ->where('type', $type->value)
            ->first();

        if ($preference === null) {
            return true;
        }

        return match ($channel) {
            OpsNotificationChannel::Mail => $preference->mail,
            default => $preference->in_app,
        };
    }

    /**
     * Toutes les préférences effectives de l'utilisateur, un élément par type.
     *
     * @return list<array{type: string, title: string, severity: string, disableable: bool, mail_supported: bool, in_app: bool, mail: bool}>
     */
    public function forUser(User $user): array
    {
        $stored = OpsNotificationPreference::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('type');

        return array_map(function (OpsNotificationType $type) use ($stored): array {
            $preference = $stored->get($type->value);
            $disableable = $type->isDisableable();

            return [
                'type' => $type->value,
                'title' => $type->title(),
                'severity' => $type->severity()->value,
                'disableable' => $disableable,
                'mail_supported' => $type->isAlert(),
                'in_app' => ! $disableable || $preference === null || $preference->in_app,
                'mail' => ! $disableable || $preference === null || $preference->mail,
            ];
        }, OpsNotificationType::cases());
    }
}
