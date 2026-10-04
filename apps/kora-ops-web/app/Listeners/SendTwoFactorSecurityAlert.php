<?php

namespace App\Listeners;

use App\Modules\IdentityAccess\Data\SecurityAlertEvent;
use App\Notifications\IdentityAccess\SecurityAlertNotification;
use Illuminate\Support\Facades\Request;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;

class SendTwoFactorSecurityAlert
{
    /**
     * Envoie l'alerte e-mail correspondant à l'événement Fortify reçu ;
     * les autres événements 2FA (défis, codes valides) sont ignorés.
     */
    public function handle(object $event): void
    {
        $alert = match (true) {
            $event instanceof TwoFactorAuthenticationConfirmed => SecurityAlertEvent::TwoFactorEnabled,
            $event instanceof TwoFactorAuthenticationDisabled => SecurityAlertEvent::TwoFactorDisabled,
            $event instanceof RecoveryCodesGenerated => SecurityAlertEvent::RecoveryCodesGenerated,
            $event instanceof PasswordUpdatedViaController => SecurityAlertEvent::PasswordChanged,
            default => null,
        };

        if ($alert === null) {
            return;
        }

        $event->user->notify(new SecurityAlertNotification($alert, [
            'ip' => Request::ip(),
            'user_agent' => Request::userAgent(),
        ]));
    }
}
