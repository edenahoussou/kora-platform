<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Notifications\IdentityAccess\OtpCodeNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class IssueOtpChallengeAction
{
    public function handle(string $email, OtpPurpose $purpose, Request $request): AuthOtpChallenge
    {
        $normalizedEmail = Str::lower($email);

        AuthOtpChallenge::query()
            ->where('email', $normalizedEmail)
            ->where('purpose', $purpose)
            ->where('status', OtpChallengeStatus::Pending)
            ->update(['status' => OtpChallengeStatus::Expired]);

        $user = User::query()->where('email', $normalizedEmail)->first();
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
