<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Policies\Concerns\AuthorizesPermissions;

class ReservationPolicy
{
    use AuthorizesPermissions;

    public function view(User $user, Reservation $reservation): bool
    {
        if ($user->isKoraPointUser()) {
            if (! $user->hasRole('kora_point_agent') || $user->agency_id === null) {
                return false;
            }

            return $reservation->selling_agency_id === $user->agency_id
                || ($reservation->selling_agency_id === null && $reservation->created_by_user_id === $user->getKey() && KoraPoint::query()->whereKey($reservation->origin_kora_point_id ?? 0)->where('agency_id', $user->agency_id)->exists());
        }

        return $this->allows($user, 'bookings.view')
            || ($reservation->created_by_user_id === $user->id && $this->allows($user, 'bookings.view-own'))
            || ($reservation->payer_user_id === $user->id && $this->allows($user, 'bookings.view-own'));
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
        if ($user->isKoraPointUser() || KoraPointSale::query()->where('reservation_id', $reservation->getKey())->exists()) {
            return false;
        }

        return $this->allows($user, 'bookings.cancel')
            || $reservation->created_by_user_id === $user->id
            || $reservation->payer_user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $this->allows($user, 'bookings.create') || $this->allows($user, 'counter-sales.create');
    }

    public function createCounterSale(User $user): bool
    {
        return $this->allows($user, 'counter-sales.create');
    }

    /**
     * Appliquer/retirer un code promo sur la réservation : réservé au
     * voyageur propriétaire (créateur ou payeur), comme l'annulation.
     */
    public function updatePromoCode(User $user, Reservation $reservation): bool
    {
        return ! $user->isKoraPointUser() && $reservation->channel !== ReservationChannel::CompanyPortal
            && ($reservation->created_by_user_id === $user->id
                || $reservation->payer_user_id === $user->id);
    }

    public function requestAssistance(User $user, Reservation $reservation): bool
    {
        return ($reservation->created_by_user_id === $user->id || $reservation->payer_user_id === $user->id)
            && $this->allows($user, 'bookings.create');
    }

    public function resumeOnline(User $user, Reservation $reservation): bool
    {
        return $this->requestAssistance($user, $reservation);
    }

    public function manageCounterSale(User $user, Reservation $reservation): bool
    {
        if (! $this->allows($user, 'counter-sales.create')) {
            return false;
        }

        if ($user->isKoraPointUser() && (! $user->hasRole('kora_point_agent') || ! KoraPoint::query()->whereKey($reservation->origin_kora_point_id ?? 0)->where('agency_id', $user->agency_id ?? 0)->exists())) {
            return false;
        }

        if ($reservation->created_by_user_id === $user->id) {
            return true;
        }

        $assistance = $reservation->assistedBooking;

        return $assistance !== null
            && in_array($assistance->status->value, ['in_progress', 'finalized'], true)
            && $assistance->assigned_user_id === $user->id
            && $assistance->preferred_agency_id === $user->agency_id;
    }
}
