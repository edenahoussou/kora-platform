<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Data\PaymentRefundDestination;
use App\Modules\Payments\Data\PaymentRefundStatus;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Models\PaymentRefund;
use App\Modules\Ticketing\Data\TicketStatus;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CancelReservationAction
{
    public function __construct(private readonly CompanyCreditService $companyCredit) {}

    public function cancel(Reservation $reservation, User $actor, bool $pointRefund = false): Reservation
    {
        return DB::transaction(function () use ($reservation, $actor, $pointRefund): Reservation {
            $locked = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor, $pointRefund);
            if ($locked->status === ReservationStatus::Cancelled || $locked->status === ReservationStatus::Expired) {
                if ($pointRefund && $locked->status === ReservationStatus::Cancelled) {
                    $paid = $locked->payments()->where('status', PaymentStatus::Confirmed->value)->latest('id')->first();
                    if ($paid !== null) {
                        $owed = max(0, (int) $paid->amount - (int) $paid->refunds()->whereNotIn('status', [PaymentRefundStatus::Failed->value])->sum('amount'));
                        if ($owed > 0) {
                            $this->recordRefund($locked, null, $paid, $owed, false);
                        }
                    }
                }

                return $locked->load(['passengers', 'segments']);
            }

            $payment = $locked->payments()->where('status', PaymentStatus::Confirmed->value)->latest('id')->first();
            $amount = (int) $locked->total_amount;
            if ($pointRefund && $payment !== null) {
                $amount = max(0, (int) $payment->amount - (int) $payment->refunds()->whereNotIn('status', [PaymentRefundStatus::Failed->value])->sum('amount'));
            }
            $locked->passengers()->where('status', 'active')->update(['status' => 'cancelled']);
            $locked->segments()->whereIn('status', ReservationSegmentStatus::occupyingValues())->update([
                'status' => ReservationSegmentStatus::Cancelled,
                'released_at' => now(),
            ]);
            $locked->tickets()->whereIn('status', [TicketStatus::Issued->value, TicketStatus::Used->value])->update(['status' => TicketStatus::Refunded]);
            $locked->update(['status' => ReservationStatus::Cancelled, 'draft_data' => null]);

            if ($locked->company_account_id !== null && $payment !== null) {
                $refund = $this->recordRefund($locked, null, $payment, $amount, true);
                if ($refund->wasRecentlyCreated) {
                    $this->companyCredit->refund($locked, $amount, $payment);
                }
            } else {
                $this->companyCredit->release($locked);
                if ($payment !== null && $amount > 0) {
                    $this->recordRefund($locked, null, $payment, $amount, false);
                }
            }

            activity()->performedOn($locked)->causedBy($actor)->withProperties(['amount' => $amount, 'scope' => 'reservation'])->log('reservation_cancelled');

            return $locked->fresh(['passengers', 'segments', 'tickets']);
        });
    }

    public function cancelPassenger(Reservation $reservation, ReservationPassenger $passenger, User $actor, bool $pointRefund = false): Reservation
    {
        return DB::transaction(function () use ($reservation, $passenger, $actor, $pointRefund): Reservation {
            $locked = Reservation::query()->whereKey($reservation->getKey())->lockForUpdate()->firstOrFail();
            $this->authorize($locked, $actor, $pointRefund);
            $lockedPassenger = $locked->passengers()->whereKey($passenger->getKey())->lockForUpdate()->firstOrFail();
            if ($lockedPassenger->status === 'cancelled') {
                return $locked->fresh(['passengers', 'segments', 'tickets']);
            }

            $allocations = $locked->total_snapshot['passenger_allocations'] ?? [];
            $refund = (int) ($allocations[$lockedPassenger->passenger_index ?? 0] ?? 0);
            $payment = $locked->payments()->where('status', PaymentStatus::Confirmed->value)->latest('id')->first();
            $lockedPassenger->update(['status' => 'cancelled', 'refunded_amount' => $refund]);
            $lockedPassenger->segments()->whereIn('status', ReservationSegmentStatus::occupyingValues())->update([
                'status' => ReservationSegmentStatus::Cancelled,
                'released_at' => now(),
            ]);
            $lockedPassenger->loadMissing('reservation');
            $locked->tickets()->where('reservation_passenger_id', $lockedPassenger->getKey())->whereIn('status', [TicketStatus::Issued->value, TicketStatus::Used->value])->update(['status' => TicketStatus::Refunded]);

            if ($locked->company_account_id !== null && $payment !== null && $refund > 0) {
                $refundRecord = $this->recordRefund($locked, $lockedPassenger, $payment, $refund, true);
                if ($refundRecord->wasRecentlyCreated) {
                    $this->companyCredit->refund($locked, $refund, $payment);
                }
            } elseif ($payment !== null && $refund > 0) {
                $this->recordRefund($locked, $lockedPassenger, $payment, $refund, false);
            }

            $activePassengers = $locked->passengers()->where('status', 'active')->count();
            if ($activePassengers === 0) {
                $locked->update(['status' => ReservationStatus::Cancelled]);
            }
            activity()->performedOn($locked)->causedBy($actor)->withProperties(['amount' => $refund, 'scope' => 'passenger', 'passenger_uuid' => $lockedPassenger->uuid])->log('reservation_passenger_cancelled');

            return $locked->fresh(['passengers', 'segments', 'tickets']);
        });
    }

    private function recordRefund(
        Reservation $reservation,
        ?ReservationPassenger $passenger,
        Payment $payment,
        int $amount,
        bool $companyCredit,
    ): PaymentRefund {
        $scope = $passenger === null ? 'full' : 'passenger:'.$passenger->uuid;
        $idempotencyKey = 'reservation:'.$reservation->uuid.':'.$scope;

        return PaymentRefund::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'reservation_id' => $reservation->getKey(),
                'reservation_passenger_id' => $passenger?->getKey(),
                'payment_id' => $payment->getKey(),
                'amount' => $amount,
                'currency' => $reservation->currency,
                'destination' => $companyCredit ? PaymentRefundDestination::CompanyCredit : (KoraPointSale::query()->where('reservation_id', $reservation->getKey())->exists() ? PaymentRefundDestination::PartnerManual : PaymentRefundDestination::WalletCash),
                'status' => $companyCredit ? PaymentRefundStatus::Completed : PaymentRefundStatus::Pending,
                'completed_at' => $companyCredit ? now() : null,
                'reference' => 'REF-'.strtoupper(substr(hash('sha256', $idempotencyKey), 0, 20)),
                'metadata' => ['source' => 'reservation_cancellation'],
            ],
        );
    }

    private function authorize(Reservation $reservation, User $actor, bool $pointRefund = false): void
    {
        $pointSale = KoraPointSale::query()->where('reservation_id', $reservation->getKey())->exists();
        if ($pointSale || $actor->isKoraPointUser()) {
            if ($pointSale && $pointRefund && ! $actor->isKoraPointUser() && $actor->can('kora-points.finance')) {
                return;
            }
            throw new AuthorizationException;
        }
        if ($actor->can('bookings.cancel') || $reservation->created_by_user_id === $actor->getKey() || $reservation->payer_user_id === $actor->getKey()) {
            return;
        }

        throw ValidationException::withMessages(['reservation' => 'Vous n’êtes pas autorisé à annuler cette réservation.']);
    }
}
