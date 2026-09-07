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
}
