<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Exceptions\UserNotFoundException;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Services\AuthIdentifierResolver;
use App\Modules\IdentityAccess\Services\StoreReviewGate;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class IssueOtpChallengeAction
{
    public function __construct(
        private readonly AuthIdentifierResolver $identifierResolver,
        private readonly StoreReviewGate $storeReviewGate,
    ) {}

    public function handle(User|string $identifier, OtpPurpose $purpose, Request $request): AuthOtpChallenge
    {
        $user = $identifier instanceof User
            ? $identifier
            : $this->identifierResolver->findUser($identifier);

        // Hors inscription, l'identifiant doit désigner un compte existant :
        // pas de compte, pas de destinataire à qui envoyer le code.
        if (! $user && $purpose !== OtpPurpose::Registration) {
            throw new UserNotFoundException;
        }

        // Le challenge porte la cible de livraison : le téléphone du compte
        // quand il existe (canal SMS prioritaire), son email sinon ;
        // en inscription, l'identifiant normalisé tel que saisi.
        $normalizedIdentifier = $user !== null
            ? (string) ($user->phone ?: Str::lower((string) $user->email))
            : $this->identifierResolver->normalize((string) $identifier);

        AuthOtpChallenge::query()
            ->where('identifier', $normalizedIdentifier)
            ->where('purpose', $purpose)
            ->where('status', OtpChallengeStatus::Pending)
            ->update(['status' => OtpChallengeStatus::Expired]);

        $code = (string) random_int(100000, 999999);

        $challenge = AuthOtpChallenge::query()->create([
            'identifier' => $normalizedIdentifier,
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'status' => OtpChallengeStatus::Pending,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        // Compte de revue store (MEZ-152) : le code fixe sera accepté à la
        // vérification. Aucun envoi réel — le reviewer ne peut pas recevoir
        // WhatsApp/SMS/email, et on ne notifie pas une boîte réelle.
        if ($this->storeReviewGate->isReviewChallenge($challenge)) {
            return $challenge;
        }

        $notification = new OtpCodeNotification($code, $purpose);
        $notification->challengeUuid = (string) $challenge->uuid;
        $notification->queuedAt = microtime(true);

        if ($user) {
            $user->notify($notification);
        } else {
            Notification::route(
                $this->isEmail($normalizedIdentifier) ? 'mail' : 'sms',
                $normalizedIdentifier,
            )->notify($notification);
        }

        return $challenge;
    }

    private function isEmail(string $identifier): bool
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
    }
}
