<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ReleaseSeatHoldAction
{
    public function __construct(private readonly CompanyCreditService $companyCredit) {}

    /**
     * Release a user's temporary reservation and its held segments atomically.
     */
    public function handle(Reservation $reservation, User $user): void
    {
        DB::transaction(function () use ($reservation, $user): void {
            $lockedReservation = Reservation::query()
                ->whereKey($reservation->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $user->can('manageCounterSale', $lockedReservation) && ! $this->isCompanyPortalOwner($lockedReservation, $user)) {
                throw new AccessDeniedHttpException;
            }

            if ($lockedReservation->status !== ReservationStatus::Held) {
                return;
            }

            $lockedReservation->update([
                'status' => ReservationStatus::Cancelled,
                'draft_data' => null,
            ]);

            $lockedReservation->segments()
                ->where('status', ReservationSegmentStatus::Held->value)
                ->update([
                    'status' => ReservationSegmentStatus::Cancelled,
                    'released_at' => now(),
                ]);
            $this->companyCredit->release($lockedReservation);
        });
    }

    private function isCompanyPortalOwner(Reservation $reservation, User $user): bool
    {
        return $reservation->channel === ReservationChannel::CompanyPortal
            && $reservation->payer_type === PayerType::Company
            && $reservation->created_by_user_id === $user->getKey();
    }
}
