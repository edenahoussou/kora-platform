<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Exceptions\UserNotFoundException;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Services\AuthIdentifierResolver;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class IssueOtpChallengeAction
{
    public function __construct(
        private readonly AuthIdentifierResolver $identifierResolver,
    ) {}

    public function handle(User|string $identifier, OtpPurpose $purpose, Request $request): AuthOtpChallenge
    {
        $user = $identifier instanceof User
            ? $identifier
            : $this->identifierResolver->findUser($identifier);
        $normalizedEmail = $user?->email !== null
            ? Str::lower($user->email)
            : $this->identifierResolver->normalize($identifier);

        if (! $user && $purpose !== OtpPurpose::Registration) {
            throw new UserNotFoundException;
        }

        AuthOtpChallenge::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->where('status', OtpChallengeStatus::Pending)
            ->update(['status' => OtpChallengeStatus::Expired]);

        $code = (string) random_int(100000, 999999);

        $challenge = AuthOtpChallenge::query()->create([
            'email' => $normalizedEmail,
            'user_id' => $user?->id,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes(5),
            'status' => OtpChallengeStatus::Pending,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000, ''),
        ]);

        if ($user) {
            $user->notify(new OtpCodeNotification($code, $purpose));
        } else {
            Notification::route('mail', $normalizedEmail)
                ->notify(new OtpCodeNotification($code, $purpose));
        }

        return $challenge;
    }
}
