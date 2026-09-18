<?php

namespace App\Policies;

use App\Models\User;
use App\Modules\AssistedBooking\Data\AssistedBookingStatus;
use App\Modules\AssistedBooking\Models\AssistedBooking;
use App\Policies\Concerns\AuthorizesPermissions;

class AssistedBookingPolicy
{
    use AuthorizesPermissions;

    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'assisted-bookings.view-agency')
            || $this->allows($user, 'assisted-bookings.view-network');
    }

    public function view(User $user, AssistedBooking $assistedBooking): bool
    {
        return $this->allows($user, 'assisted-bookings.view-network')
            || ($this->allows($user, 'assisted-bookings.view-agency')
                && $user->agency_id === $assistedBooking->preferred_agency_id);
    }

    public function claim(User $user, AssistedBooking $assistedBooking): bool
    {
        return $this->allows($user, 'assisted-bookings.claim')
            && $user->agency_id === $assistedBooking->preferred_agency_id
            && $assistedBooking->status === AssistedBookingStatus::Pending;
    }

    public function release(User $user, AssistedBooking $assistedBooking): bool
    {
        $ownsAssignment = $assistedBooking->assigned_user_id === $user->id
            && $this->allows($user, 'assisted-bookings.claim');
        $managesAgency = $user->agency_id === $assistedBooking->preferred_agency_id
            && $this->allows($user, 'assisted-bookings.manage-agency');

        return $ownsAssignment || $managesAgency || $this->allows($user, 'assisted-bookings.reassign-network');
    }

    public function reassign(User $user, AssistedBooking $assistedBooking): bool
    {
        return $this->allows($user, 'assisted-bookings.reassign-network')
            || ($this->allows($user, 'assisted-bookings.manage-agency')
                && $user->agency_id === $assistedBooking->preferred_agency_id);
    }

    public function refuse(User $user, AssistedBooking $assistedBooking): bool
    {
        return $this->allows($user, 'assisted-bookings.refuse')
            && ($this->allows($user, 'assisted-bookings.view-network')
                || $user->agency_id === $assistedBooking->preferred_agency_id);
    }

    public function prepareSale(User $user, AssistedBooking $assistedBooking): bool
    {
        return $assistedBooking->status === AssistedBookingStatus::InProgress
            && $assistedBooking->assigned_user_id === $user->id
            && $user->agency_id === $assistedBooking->preferred_agency_id
            && $this->allows($user, 'counter-sales.create');
    }
}
