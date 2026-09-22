<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Data\UserStatus;
use Illuminate\Http\Request;

class RequestUserPinResetAction
{
    public function __construct(
        private readonly IssueOtpChallengeAction $issueOtpChallenge,
        private readonly RevokeUserAccessAction $revokeAccess,
    ) {}

    public function execute(User $user, User $actor, Request $request): string
    {
        $status = $user->status === UserStatus::Locked ? UserStatus::Active : $user->status;

        $user->forceFill([
            'pin_hash' => null,
            'failed_pin_attempts' => 0,
            'locked_until' => null,
            'status' => $status,
        ])->save();

        $this->revokeAccess->execute($user, $actor);

        $challenge = $this->issueOtpChallenge->handle(
            $user->email,
            OtpPurpose::PinReset,
            $request,
        );

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties(['otp_challenge_uuid' => $challenge->uuid])
            ->log('user_pin_reset_requested');

        return $challenge->uuid;
    }
}
