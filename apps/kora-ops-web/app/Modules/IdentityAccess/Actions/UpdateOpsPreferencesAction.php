<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Models\UserPreference;

use function activity;

class UpdateOpsPreferencesAction
{
    /**
     * Crée ou met à jour les préférences OPS ; couper le push purge
     * les tokens d'appareils (miroir de UserPreferenceController API).
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(User $user, array $payload): UserPreference
    {
        $preference = UserPreference::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'push_notifications' => UserPreference::defaultPushNotificationsFor($user),
                'whatsapp_notifications' => true,
                'sms_notifications' => true,
                'email_notifications' => true,
                'locale' => 'fr',
                'theme' => 'light',
            ],
        );

        $preference->update($payload);

        if ($preference->wasChanged('push_notifications') && ! $preference->push_notifications) {
            $user->devices()->update(['push_token' => null]);
        }

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($user)
            ->withProperties(['changes' => $preference->getChanges()])
            ->log('preferences_updated');

        return $preference;
    }
}
