<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Ticketing\Data\TicketStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function __construct(private readonly CompanyCreditService $companyCredit) {}

    public function cancel(Reservation $reservation, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor): Reservation {
            $locked = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);
            if ($locked->status === ReservationStatus::Cancelled || $locked->status === ReservationStatus::Expired) {
                return $locked->load(['passengers', 'segments']);
            }

            $payment = $locked->payments()->where('status', PaymentStatus::Confirmed->value)->latest('id')->first();
            $amount = (int) $locked->total_amount;
            $locked->passengers()->where('status', 'active')->update(['status' => 'cancelled']);
            $locked->segments()->whereIn('status', ReservationSegmentStatus::occupyingValues())->update([
                'status' => ReservationSegmentStatus::Cancelled,
                'released_at' => now(),
            ]);
            $locked->tickets()->whereIn('status', [TicketStatus::Issued->value, TicketStatus::Used->value])->update(['status' => TicketStatus::Refunded]);
            $locked->update(['status' => ReservationStatus::Cancelled, 'draft_data' => null]);

            if ($locked->company_account_id !== null && $payment !== null) {
                $alreadyRefunded = $locked->companyAccount?->creditTransactions()->where('reservation_id', $locked->getKey())->where('type', 'refund')->exists();
                if (! $alreadyRefunded) {
                    $this->companyCredit->refund($locked, $amount, $payment);
                }
            } else {
                $this->companyCredit->release($locked);
            }

            activity()->performedOn($locked)->causedBy($actor)->withProperties(['amount' => $amount, 'scope' => 'reservation'])->log('reservation_cancelled');

            return $locked->fresh(['passengers', 'segments', 'tickets']);
        });
    }

    public function cancelPassenger(Reservation $reservation, ReservationPassenger $passenger, User $actor): Reservation
    {
        return DB::transaction(function () use ($reservation, $passenger, $actor): Reservation {
            $locked = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor);
            $lockedPassenger = $locked->passengers()->whereKey($passenger->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedPassenger->status === 'cancelled') {
                return $locked->fresh(['passengers', 'segments', 'tickets']);
            }

            $index = $locked->passengers()->orderBy('id')->pluck('id')->search($lockedPassenger->getKey());
            $allocations = $locked->total_snapshot['passenger_allocations'] ?? [];
            $refund = (int) ($allocations[$index] ?? 0);
            $payment = $locked->payments()->where('status', PaymentStatus::Confirmed->value)->latest('id')->first();
            $lockedPassenger->update(['status' => 'cancelled', 'refunded_amount' => $refund]);
            $lockedPassenger->segments()->whereIn('status', ReservationSegmentStatus::occupyingValues())->update([
                'status' => ReservationSegmentStatus::Cancelled,
                'released_at' => now(),
            ]);
            $lockedPassenger->loadMissing('reservation');
            $locked->tickets()->where('reservation_passenger_id', $lockedPassenger->getKey())->whereIn('status', [TicketStatus::Issued->value, TicketStatus::Used->value])->update(['status' => TicketStatus::Refunded]);

            if ($locked->company_account_id !== null && $payment !== null && $refund > 0) {
                $this->companyCredit->refund($locked, $refund, $payment);
            }

            $activePassengers = $locked->passengers()->where('status', 'active')->count();
            if ($activePassengers === 0) {
                $locked->update(['status' => ReservationStatus::Cancelled]);
            }
            activity()->performedOn($locked)->causedBy($actor)->withProperties(['amount' => $refund, 'scope' => 'passenger', 'passenger_uuid' => $lockedPassenger->uuid])->log('reservation_passenger_cancelled');

            return $locked->fresh(['passengers', 'segments', 'tickets']);
        });
    }

    private function authorize(Reservation $reservation, User $actor): void
    {
        if ($actor->can('bookings.cancel') || $reservation->created_by_user_id === $actor->getKey() || $reservation->payer_user_id === $actor->getKey()) {
            return;
        }

        throw ValidationException::withMessages(['reservation' => 'Vous n’êtes pas autorisé à annuler cette réservation.']);
    }
}
