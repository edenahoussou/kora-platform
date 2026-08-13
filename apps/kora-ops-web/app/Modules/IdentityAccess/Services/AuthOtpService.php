<?php

namespace App\Modules\IdentityAccess\Services;

use App\Modules\IdentityAccess\Actions\IssueOtpChallengeAction;
use App\Modules\IdentityAccess\Actions\VerifyOtpChallengeAction;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use Illuminate\Http\Request;

class AuthOtpService
{
    public function __construct(
        private IssueOtpChallengeAction $issueOtpChallenge,
        private VerifyOtpChallengeAction $verifyOtpChallenge,
    ) {}

    public function request(string $email, string $purpose, Request $request): AuthOtpChallenge
    {
        return $this->issueOtpChallenge->handle($email, OtpPurpose::from($purpose), $request);
    }

    /**
     * @return array{challenge: AuthOtpChallenge, verification_token: string}
     */
    public function verify(string $challengeUuid, string $purpose, string $code): array
    {
        return $this->verifyOtpChallenge->handle($challengeUuid, OtpPurpose::from($purpose), $code);
    }
}
