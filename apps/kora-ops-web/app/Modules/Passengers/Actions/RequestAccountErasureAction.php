<?php

namespace App\Modules\Passengers\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Passengers\Models\PassengerErasureRequest;
use App\Notifications\Passengers\PassengerDeletionRequestedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Enregistre une demande de suppression de compte initiée in-app (canal asynchrone OPS).
 */
class RequestAccountErasureAction
{
    public function __construct(
        private readonly PassengerIdentityResolver $identityResolver,
    ) {}

    public function execute(User $user, string $pin, ?string $reason, ?string $ipAddress): PassengerErasureRequest
    {
        if ($user->pin_hash === null || $user->pin_hash === '') {
            throw ValidationException::withMessages([
                'pin' => 'pin_required',
            ]);
        }

        if (! Hash::check($pin, $user->pin_hash)) {
            throw ValidationException::withMessages([
                'pin' => __('Le code PIN est incorrect.'),
            ]);
        }

        $normalizedPhone = $this->identityResolver->normalizePhone($user->phone, $user->country_code);
        $identity = $normalizedPhone ?? 'email:'.(string) $user->email;
        $phoneHash = hash_hmac('sha256', $identity, (string) config('app.key'));

        $request = PassengerErasureRequest::query()->updateOrCreate(
            ['phone_hash' => $phoneHash],
            [
                'uuid' => (string) Str::uuid(),
                'requested_by_user_id' => $user->getKey(),
                'actor_name' => $user->name,
                'requester_phone' => $normalizedPhone,
                'requester_email' => $user->email,
                'status' => PassengerErasureStatus::Requested,
                'scope' => array_filter([
                    'channel' => 'in_app',
                    'reason' => $reason,
                ]),
                'executed_at' => null,
                'export_checksum' => null,
                'ip_address' => $ipAddress,
            ],
        );

        try {
            Notification::route('mail', (string) config('legal.support_email'))
                ->notify(new PassengerDeletionRequestedNotification(
                    $normalizedPhone ?? 'email:'.(string) $user->email,
                    $user->name,
                    $user->email,
                    $reason,
                ));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $request;
    }
}
