<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VerifyOtpChallengeAction
{
    /**
     * @return array{challenge: AuthOtpChallenge, verification_token: string}
     */
    public function handle(string $challengeUuid, OtpPurpose $purpose, string $code): array
    {
        $challenge = AuthOtpChallenge::query()
            ->where('uuid', $challengeUuid)
            ->where('purpose', $purpose->value)
            ->first();

        if (! $challenge) {
            throw ValidationException::withMessages(['code' => ['Le code est incorrect ou expiré.']]);
        }

        if ($challenge->expires_at->isPast()) {
            $challenge->update(['status' => OtpChallengeStatus::Expired]);

            throw ValidationException::withMessages(['code' => ['Le code est incorrect ou expiré.']]);
        }

        if ($challenge->status !== OtpChallengeStatus::Pending || $challenge->attempts >= 5) {
            throw ValidationException::withMessages(['code' => ['Le code est incorrect ou expiré.']]);
        }

        $challenge->increment('attempts');

        if (! Hash::check($code, $challenge->code_hash)) {
            throw ValidationException::withMessages(['code' => ['Le code est incorrect ou expiré.']]);
        }

        $verificationToken = Str::random(64);

        $challenge->update([
            'verification_token_hash' => hash('sha256', $verificationToken),
            'verified_at' => now(),
            'status' => OtpChallengeStatus::Verified,
        ]);

        return [
            'challenge' => $challenge->refresh(),
            'verification_token' => $verificationToken,
        ];
    }
}
