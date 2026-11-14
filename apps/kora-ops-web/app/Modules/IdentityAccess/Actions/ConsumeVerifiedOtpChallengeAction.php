<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use Illuminate\Validation\ValidationException;

class ConsumeVerifiedOtpChallengeAction
{
    public function handle(string $challengeUuid, OtpPurpose $purpose, string $verificationToken, bool $lockForUpdate = false): AuthOtpChallenge
    {
        $challenge = AuthOtpChallenge::query()
            ->where('uuid', $challengeUuid)
            ->where('purpose', $purpose->value)
            ->when($lockForUpdate, fn ($query) => $query->lockForUpdate())
            ->first();

        if (
            ! $challenge
            || $challenge->status !== OtpChallengeStatus::Verified
            || $challenge->expires_at->isPast()
            || ! $challenge->verification_token_hash
            || ! hash_equals($challenge->verification_token_hash, hash('sha256', $verificationToken))
        ) {
            throw ValidationException::withMessages([
                'verification_token' => ['La vérification OTP est invalide ou expirée.'],
            ]);
        }

        $challenge->update([
            'status' => OtpChallengeStatus::Consumed,
            'consumed_at' => now(),
        ]);

        return $challenge->refresh();
    }
}
