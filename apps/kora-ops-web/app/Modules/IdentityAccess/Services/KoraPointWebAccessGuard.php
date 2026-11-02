<?php

namespace App\Modules\IdentityAccess\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use Illuminate\Support\Carbon;

class KoraPointWebAccessGuard
{
    public function canUseTemporaryPassword(User $user): bool
    {
        if (! $user->isKoraPointUser()) {
            return true;
        }

        $invitation = $user->latestKoraPointWebAccessInvitation()->first();

        if ($invitation === null) {
            return true;
        }

        $expiresAt = $invitation->expires_at === null ? null : Carbon::parse($invitation->expires_at);

        if (
            $invitation->status === KoraPointWebAccessInvitation::STATUS_SENT
            && $expiresAt?->isFuture()
        ) {
            return true;
        }

        if (
            $invitation->status === KoraPointWebAccessInvitation::STATUS_SENT
            && ($expiresAt === null || $expiresAt->isPast())
        ) {
            $invitation->update(['status' => KoraPointWebAccessInvitation::STATUS_EXPIRED]);
        }

        return false;
    }

    public function unavailableMessage(): string
    {
        return 'Cet accès provisoire est en attente, expiré ou remplacé. Demandez un nouvel accès à un administrateur depuis la liste Utilisateurs.';
    }
}
