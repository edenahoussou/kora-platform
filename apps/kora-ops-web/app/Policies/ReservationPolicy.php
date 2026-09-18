<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Policies\Concerns\AuthorizesPermissions;

class ReservationPolicy
{
    use AuthorizesPermissions;

    public function view(User $user, Reservation $reservation): bool
    {
        return $this->allows($user, 'bookings.view')
            || ($reservation->created_by_user_id === $user->id && $this->allows($user, 'bookings.view-own'))
            || ($reservation->payer_user_id === $user->id && $this->allows($user, 'bookings.view-own'));
    }

    public function cancel(User $user, Reservation $reservation): bool
    {
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
