<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\CompanyAccounts\Data\CompanyCreditHoldStatus;
use App\Modules\CompanyAccounts\Services\CompanyCreditService;
use App\Modules\DynamicPricing\Actions\RecordPromotionRedemptionAction;
use App\Modules\Payments\Data\PaymentMethod;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Jobs\IssueReservationTicketsJob;
use App\Modules\Wallet\Actions\CaptureWalletHoldAction;
use App\Modules\Wallet\Actions\ConfirmWalletTopUpAction;
use App\Modules\Wallet\Actions\ReleaseWalletHoldAction;
use App\Modules\Wallet\Models\WalletHold;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmPaymentAction
{
    public function __construct(
        private readonly CompanyCreditService $companyCredit,
        private readonly RecordPromotionRedemptionAction $recordPromotionRedemption,
        private readonly ConfirmWalletTopUpAction $confirmWalletTopUp,
        private readonly CaptureWalletHoldAction $captureWalletHold,
        private readonly ReleaseWalletHoldAction $releaseWalletHold,
    ) {}

    /**
     * @param  array<string, mixed>  $providerPayload
     */
    public function handle(
        Payment $payment,
        string $status,
        ?string $providerReference = null,
        array $providerPayload = [],
    ): Payment {
        $reservationId = null;

        $payment = DB::transaction(function () use ($payment, $status, $providerReference, $providerPayload, &$reservationId): Payment {
            $lockedPayment = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedPayment->load(['reservation.segments']);

            if ($lockedPayment->status->isTerminal()) {
                return $lockedPayment->fresh(['reservation.segments.departure']);
            }

            $isSuccess = in_array(strtolower($status), ['success', 'confirmed'], true);
            $isPending = strtolower($status) === 'pending';

            $mergedPayload = array_merge($lockedPayment->provider_payload ?? [], $providerPayload);

            if ($isSuccess) {
                $lockedPayment->update([
                    'status' => PaymentStatus::Confirmed,
                    'confirmed_at' => now(),
                    'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                    'provider_payload' => $mergedPayload,
                ]);

                $reservation = $lockedPayment->reservation;
                if ($reservation?->origin_kora_point_id !== null) {
                    throw ValidationException::withMessages(['payment' => 'Une réservation KORA Point se règle au guichet partenaire.']);
                }

                $this->confirmWalletTopUp->confirm($lockedPayment);

                $hold = WalletHold::query()
                    ->where('payment_id', $lockedPayment->getKey())
                    ->where('status', 'active')
                    ->first();
                if ($hold !== null) {
                    $this->captureWalletHold->handle($hold, $lockedPayment);
                }

                if ($reservation) {
                    if ($lockedPayment->method === PaymentMethod::CompanyCredit) {
                        $hold = $reservation->companyCreditHolds()
                            ->where('status', CompanyCreditHoldStatus::Reserved->value)
                            ->lockForUpdate()
                            ->first();
                        if ($hold === null) {
                            throw ValidationException::withMessages(['payment' => 'Le crédit entreprise n’est plus réservé.']);
                        }
                        $this->companyCredit->convert($hold, $lockedPayment);
                    }
                    $reservation->update(['status' => ReservationStatus::Paid]);
                    $reservation->segments()->update(['status' => ReservationSegmentStatus::Paid]);
                    $this->recordPromotionRedemption->execute($reservation);
                    $reservationId = $reservation->id;
                }
            } elseif ($isPending) {
                // A still-pending provider answer refreshes the audit payload
                // without failing the payment.
                $lockedPayment->update([
                    'provider_payload' => $mergedPayload,
                ]);
            } else {
                $lockedPayment->update([
                    'status' => PaymentStatus::Failed,
                    'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                    'provider_payload' => $mergedPayload,
                ]);

                $reservation = $lockedPayment->reservation;
                if ($reservation) {
                    if ($lockedPayment->method === PaymentMethod::CompanyCredit) {
                        $this->companyCredit->release($reservation);
                    }
                    $reservation->update(['status' => ReservationStatus::Expired]);
                    $reservation->segments()->update([
                        'status' => ReservationSegmentStatus::Expired,
                        'released_at' => now(),
                    ]);
                }

                $this->confirmWalletTopUp->fail($lockedPayment);
                $this->releaseWalletHold->releaseForPayment((int) $lockedPayment->getKey());
            }

            return $lockedPayment->fresh(['reservation.segments.departure']);
        });

        // Dispatch after the transaction commits so the job sees the Paid status.
        if ($reservationId !== null) {
            IssueReservationTicketsJob::dispatch($reservationId);
        }

        return $payment;
    }
}
