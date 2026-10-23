<?php

namespace App\Modules\Passengers\Actions;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
use App\Modules\IdentityAccess\Actions\RevokeUserAccessAction;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\Notifications\Channels\OtpCodeChannel;
use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Passengers\Models\PassengerErasureRequest;
use App\Notifications\Passengers\PassengerAccountErasedNotification;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

/**
 * Effacement définitif d'un compte passager : anonymise les PII des lignes
 * de voyage, détruit le compte utilisateur et consigne l'opération.
 */
class ErasePassengerAccountAction
{
    public function __construct(
        private readonly RevokeUserAccessAction $revokeAccess,
        private readonly PassengerIdentityResolver $identityResolver,
    ) {}

    /**
     * @return PassengerErasureRequest|null null si aucun passager pour ce téléphone
     */
    public function execute(string $phone, User $actor): ?PassengerErasureRequest
    {
        $contact = ['email' => null, 'phone' => $phone];

        $request = DB::transaction(function () use ($phone, $actor, &$contact): ?PassengerErasureRequest {
            $passengers = ReservationPassenger::query()->where('phone', $phone)->get();

            if ($passengers->isEmpty()) {
                return null;
            }

            $normalizedPhone = $this->identityResolver->normalizePhone($phone);
            $linkedUser = $this->resolveLinkedUser($passengers, $phone, $normalizedPhone);
            $contact = [
                'email' => $this->resolveContactEmail($passengers, $linkedUser),
                'phone' => $normalizedPhone ?? $phone,
            ];
            $scope = $this->buildScope($passengers);

            $this->anonymizePassengerRecords($passengers);
            $this->scrubPayerSnapshots($passengers, $linkedUser, $phone, $normalizedPhone);
            $this->anonymizeCompanyTravelers($normalizedPhone);
            $this->scrubAuthAndNotificationTraces($passengers, $linkedUser, $phone, $normalizedPhone);

            if ($linkedUser !== null) {
                $this->eraseUserAccount($linkedUser, $actor);
            }

            $request = $this->recordErasure($phone, $actor, $scope);

            activity('passengers')
                ->causedBy($actor)
                ->withProperties([
                    'phone_hash' => $request->phone_hash,
                    'scope' => $scope,
                    'linked_account_erased' => $linkedUser !== null,
                ])
                ->log('passenger_account_erased');

            return $request;
        });

        if ($request !== null) {
            $this->notifyAccountErased($contact['email'], $contact['phone']);
        }

        return $request;
    }

    /**
     * Dernier contact utilisable avant anonymisation : la confirmation
     * d'effacement part par email si joignable, sinon par WhatsApp.
     *
     * @param  EloquentCollection<int, ReservationPassenger>  $passengers
     */
    private function resolveContactEmail(EloquentCollection $passengers, ?User $linkedUser): ?string
    {
        $email = $passengers->pluck('email')
            ->push($linkedUser?->email)
            ->filter()
            ->unique()
            ->first(fn (mixed $candidate) => is_string($candidate) && OtpCodeChannel::isContactableEmail($candidate));

        return is_string($email) ? $email : null;
    }

    private function notifyAccountErased(?string $email, string $phone): void
    {
        try {
            Notification::route('mail', $email)
                ->route('whatsapp', $phone)
                ->notify(new PassengerAccountErasedNotification);
        } catch (Throwable $exception) {
            Log::warning('Confirmation d’effacement du compte passager non envoyée.', [
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @param  EloquentCollection<int, ReservationPassenger>  $passengers
     */
    private function resolveLinkedUser(EloquentCollection $passengers, string $phone, ?string $normalizedPhone): ?User
    {
        $userIds = $passengers->pluck('user_id')->filter()->unique()->all();
        $phones = array_filter(array_unique([$phone, $normalizedPhone]));

        return User::query()
            ->whereIn('id', $userIds)
            ->orWhereIn('phone', $phones)
            ->first();
    }

    /**
     * @param  EloquentCollection<int, ReservationPassenger>  $passengers
     * @return array<string, int>
     */
    private function buildScope(EloquentCollection $passengers): array
    {
        return [
            'records' => $passengers->count(),
            'reservations' => $passengers->pluck('reservation_id')->unique()->count(),
            'tickets' => DB::table('tickets')
                ->whereIn('reservation_passenger_id', $passengers->pluck('id'))
                ->count(),
            'documents' => $passengers->pluck('document_number')->filter()->unique()->count(),
        ];
    }

    /**
     * @param  EloquentCollection<int, ReservationPassenger>  $passengers
     */
    private function anonymizePassengerRecords(EloquentCollection $passengers): void
    {
        $now = now();

        foreach ($passengers as $passenger) {
            $passenger->forceFill([
                'first_name' => 'Passager',
                'last_name' => 'Supprimé',
                'phone' => null,
                'email' => null,
                'document_type' => null,
                'document_number' => null,
                'nationality' => null,
                'birth_date' => null,
                'yellow_fever_vaccinated' => null,
                'user_id' => null,
                'anonymized_at' => $now,
            ])->save();
        }
    }

    /**
     * @param  EloquentCollection<int, ReservationPassenger>  $passengers
     */
    private function scrubPayerSnapshots(EloquentCollection $passengers, ?User $linkedUser, string $phone, ?string $normalizedPhone): void
    {
        if ($linkedUser !== null) {
            Reservation::query()->where('payer_user_id', $linkedUser->getKey())
                ->update(['payer_user_id' => null]);
        }

        $phones = array_filter(array_unique([$phone, $normalizedPhone]));

        Reservation::query()
            ->whereIn('id', $passengers->pluck('reservation_id')->unique())
            ->when($linkedUser !== null, fn ($query) => $query->orWhere('payer_snapshot->account_uuid', $linkedUser->uuid))
            ->whereNotNull('payer_snapshot')
            ->get()
            ->each(function (Reservation $reservation) use ($linkedUser, $phones): void {
                $snapshot = $reservation->payer_snapshot;
                if (! is_array($snapshot) || ($snapshot['type'] ?? null) !== 'personal') {
                    return;
                }

                $matchesUser = $linkedUser !== null
                    && ($snapshot['account_uuid'] ?? null) === $linkedUser->uuid;
                $matchesPhone = in_array($snapshot['phone'] ?? null, $phones, true);

                if (! $matchesUser && ! $matchesPhone) {
                    return;
                }

                $snapshot['display_name'] = 'Compte supprimé';
                $snapshot['phone'] = null;
                $snapshot['account_uuid'] = null;

                $reservation->forceFill(['payer_snapshot' => $snapshot])->save();
            });
    }

    private function anonymizeCompanyTravelers(?string $normalizedPhone): void
    {
        if ($normalizedPhone === null) {
            return;
        }

        CompanyTraveler::query()->get()
            ->filter(fn (CompanyTraveler $traveler) => $this->identityResolver->normalizePhone($traveler->phone) === $normalizedPhone)
            ->each(function (CompanyTraveler $traveler): void {
                $traveler->forceFill([
                    'first_name' => 'Voyageur',
                    'last_name' => 'Supprimé',
                    'phone' => 'erased-'.$traveler->getKey(),
                    'email' => null,
                    'document_type' => null,
                    'document_number' => null,
                    'nationality' => null,
                    'birth_date' => null,
                    'yellow_fever_vaccinated' => null,
                    'status' => CompanyTravelerStatus::Inactive,
                ])->save();
            });
    }

    /**
     * @param  EloquentCollection<int, ReservationPassenger>  $passengers
     */
    private function scrubAuthAndNotificationTraces(EloquentCollection $passengers, ?User $linkedUser, string $phone, ?string $normalizedPhone): void
    {
        $emails = $passengers->pluck('email')->filter()->unique();
        if ($linkedUser?->email !== null) {
            $emails->push($linkedUser->email);
        }

        $identifiers = $emails->merge(array_filter([$phone, $normalizedPhone]))->unique();
        if ($identifiers->isEmpty()) {
            return;
        }

        DB::table('auth_otp_challenges')->whereIn('identifier', $identifiers)->delete();
        DB::table('notification_logs')->whereIn('recipient', $identifiers)
            ->update(['recipient' => 'anonymized']);
    }

    /**
     * Anonymise puis soft-delete le compte : des FK restrictOnDelete (achats
     * groupés, ventes comptoir) peuvent empêcher la suppression physique.
     */
    private function eraseUserAccount(User $user, User $actor): void
    {
        $this->revokeAccess->execute($user, $actor);

        $user->passkeys()->delete();
        $user->socialAccounts()->delete();
        $user->preference()->delete();
        $user->notifications()->delete();
        $user->clearMediaCollection('avatar');
        $user->syncRoles([]);
        $user->syncPermissions([]);

        $user->forceFill([
            'name' => 'Compte supprimé',
            'email' => 'erased+'.$user->uuid.'@accounts.kora.invalid',
            'phone' => null,
            'country_code' => null,
            'job_title' => null,
            'department' => null,
            'location' => null,
            'birth_date' => null,
            'password' => Str::random(64),
            'pin_hash' => null,
            'failed_pin_attempts' => 0,
            'locked_until' => null,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
            'email_verified_at' => null,
            'must_change_password' => false,
            'remember_token' => null,
            'status' => UserStatus::Suspended,
        ])->save();

        $user->delete();
    }

    /**
     * @param  array<string, int>  $scope
     */
    private function recordErasure(string $phone, User $actor, array $scope): PassengerErasureRequest
    {
        return PassengerErasureRequest::query()->updateOrCreate(
            ['phone_hash' => hash_hmac('sha256', $phone, (string) config('app.key'))],
            [
                'requested_by_user_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'requester_phone' => null,
                'requester_email' => null,
                'status' => PassengerErasureStatus::Executed,
                'scope' => $scope,
                'executed_at' => now(),
                'ip_address' => request()->ip(),
            ],
        );
    }
}
