<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Exceptions\UserNotFoundException;
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

        // Ancien code : le user était récupéré après l'expiration des défis
        // en attente, sans jamais vérifier son existence — un email inconnu
        // recevait quand même le mail OTP via Notification::route('mail', ...).
        $user = User::query()->where('email', $normalizedEmail)->first();

        // La demande d'inscription est le seul cas où l'utilisateur n'existe
        // pas encore en base par définition. Pour tous les autres motifs
        // (connexion, réinitialisation de PIN, déverrouillage), l'email doit
        // correspondre à un compte existant, sinon on ne doit ni créer de
        // défi ni envoyer de mail.
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
