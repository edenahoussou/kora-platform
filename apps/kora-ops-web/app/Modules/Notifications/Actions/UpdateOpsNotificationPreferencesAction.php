<?php

namespace App\Modules\Notifications\Actions;

use App\Models\User;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Exceptions\OpsNotificationPreferenceException;
use App\Modules\Notifications\Models\OpsNotificationPreference;
use Illuminate\Support\Facades\DB;

class UpdateOpsNotificationPreferencesAction
{
    /**
     * Met à jour les préférences (canal in_app / mail par type d'événement).
     * Tout ou rien : si l'une des lignes tente de désactiver une alerte
     * critique, rien n'est enregistré.
     *
     * @param  list<array{type: string, in_app?: bool, mail?: bool}>  $preferences
     *
     * @throws OpsNotificationPreferenceException
     */
    public function execute(User $user, array $preferences): void
    {
        $blocked = [];

        foreach ($preferences as $preference) {
            $type = OpsNotificationType::from($preference['type']);

            if (! $type->isDisableable() && (($preference['in_app'] ?? true) === false || ($preference['mail'] ?? true) === false)) {
                $blocked[] = $type->value;
            }
        }

        if ($blocked !== []) {
            throw new OpsNotificationPreferenceException(array_values(array_unique($blocked)));
        }

        DB::transaction(function () use ($user, $preferences): void {
            foreach ($preferences as $preference) {
                $stored = OpsNotificationPreference::query()->firstOrNew([
                    'user_id' => $user->id,
                    'type' => $preference['type'],
                ]);

                $stored->fill([
                    'in_app' => $preference['in_app'] ?? ($stored->exists ? $stored->in_app : true),
                    'mail' => $preference['mail'] ?? ($stored->exists ? $stored->mail : true),
                ])->save();
            }
        });
    }
}
