<?php

namespace App\Modules\Payments\Actions;

use App\Models\User;
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
use App\Modules\Wallet\Actions\FindOrCreateWalletAction;
use App\Modules\Wallet\Actions\PlaceWalletHoldAction;
use App\Modules\Wallet\Actions\ReleaseWalletHoldAction;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletHoldStatus;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletHold;
use App\Modules\Wallet\Services\WalletLedgerService;
use App\Modules\Wallet\Services\WalletNotificationPublisher;
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
        private readonly PlaceWalletHoldAction $placeWalletHold,
        private readonly WalletLedgerService $walletLedger,
        private readonly FindOrCreateWalletAction $findOrCreateWallet,
        private readonly WalletNotificationPublisher $walletNotifications,
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

            $isSuccess = in_array(strtolower($status), ['success', 'confirmed'], true);
            $isPending = strtolower($status) === 'pending';

            if ($lockedPayment->status->isTerminal()) {
                // Si le paiement avait été marqué 'failed' uniquement suite à l'expiration de la réservation,
                // mais que l'opérateur confirme finalement un encaissement réussi,
                // on ne retourne pas immédiatement : on doit régulariser cet encaissement tardif vers le Wallet.
                $isLateExpiredSettlement = $isSuccess
                    && $lockedPayment->status === PaymentStatus::Failed
                    && $lockedPayment->failure_code === 'reservation_expired';

                if (! $isLateExpiredSettlement) {
                    return $lockedPayment->fresh(['reservation.segments.departure']);
                }
            }

            $mergedPayload = array_merge($lockedPayment->provider_payload ?? [], $providerPayload);

            if ($isSuccess && $lockedPayment->reservation_id !== null) {
                $funding = WalletPurchaseFunding::fromPayment($lockedPayment);
                if ($funding->walletCash > 0 || $funding->points > 0) {
                    $hold = WalletHold::query()->where('payment_id', $lockedPayment->getKey())->lockForUpdate()->first();
                    $holdExpiredOrUnavailable = $hold === null
                        || $hold->status === WalletHoldStatus::Released
                        || ($hold->status === WalletHoldStatus::Active && $hold->expires_at->isPast());

                    if ($holdExpiredOrUnavailable) {
                        $reservation = $lockedPayment->reservation;
                        $owner = $reservation?->payer_user_id !== null
                            ? User::query()->find($reservation->payer_user_id)
                            : null;
                        $wallet = $owner !== null ? Wallet::query()->where('user_id', $owner->getKey())->first() : null;

                        $canRecoverWallet = $wallet !== null
                            && $this->walletLedger->availableBalance($wallet, WalletBalanceType::Cash) >= $funding->walletCash
                            && $this->walletLedger->availableBalance($wallet, WalletBalanceType::Points) >= $funding->points;

                        $canRecoverReservation = $reservation !== null
                            && $reservation->status === ReservationStatus::Held
                            && ($reservation->expires_at === null || $reservation->expires_at->isFuture());

                        if ($canRecoverWallet && $canRecoverReservation) {
                            if ($hold !== null && $hold->status === WalletHoldStatus::Active) {
                                $this->releaseWalletHold->handle($hold);
                            }

                            $this->placeWalletHold->handle(
                                wallet: $wallet,
                                cashAmount: $funding->walletCash,
                                pointsAmount: $funding->points,
                                idempotencyKey: 'payment:'.$lockedPayment->uuid.':late-hold',
                                reservation: $reservation,
                                payment: $lockedPayment,
                            );

                            activity('payments')
                                ->performedOn($lockedPayment)
                                ->withProperties([
                                    'payment_uuid' => $lockedPayment->uuid,
                                    'reservation_reference' => $reservation->reference,
                                ])
                                ->log('payment.late_wallet_hold_recovered');
                        } else {
                            $lockedPayment->update([
                                'status' => PaymentStatus::Confirmed,
                                'confirmed_at' => now(),
                                'provider_reference' => $providerReference ?? $lockedPayment->provider_reference,
                                'provider_payload' => $mergedPayload,
                                'failure_code' => 'booking_expired_settled_to_wallet',
                                'failure_message' => 'Réservation non confirmée. Complément externe crédité sur le Wallet.',
                            ]);

                            if ($reservation !== null) {
                                if ($lockedPayment->method === PaymentMethod::CompanyCredit) {
                                    $this->companyCredit->release($reservation);
                                }
                                $reservation->update(['status' => ReservationStatus::Expired]);
                                $reservation->segments()->update([
                                    'status' => ReservationSegmentStatus::Expired,
                                    'released_at' => now(),
                                ]);
                            }

                            $this->releaseWalletHold->releaseForPayment((int) $lockedPayment->getKey());

                            $providerAmount = (int) ($lockedPayment->provider_amount ?? $funding->providerAmount);
                            if ($providerAmount > 0) {
                                $targetWallet = $wallet ?? $this->findOrCreateWallet->handle(phone: $lockedPayment->payer_phone);
                                $this->walletLedger->credit(
                                    wallet: $targetWallet,
                                    type: WalletBalanceType::Cash,
                                    amount: $providerAmount,
                                    reason: WalletTransactionReason::UnconfirmedBookingSettlement,
                                    idempotencyKey: 'settlement:'.$lockedPayment->uuid.':wallet-credit',
                                    meta: [
                                        'payment_uuid' => $lockedPayment->uuid,
                                        'reservation_reference' => $reservation?->reference,
                                        'reason' => 'unconfirmed_booking_settlement',
                                    ],
                                    reference: $lockedPayment,
                                );

                                if ($reservation !== null) {
                                    $this->walletNotifications->bookingSettlementCredited($targetWallet, $providerAmount, $reservation->reference);
                                }
                            }

                            activity('payments')
                                ->performedOn($lockedPayment)
                                ->withProperties([
                                    'payment_uuid' => $lockedPayment->uuid,
                                    'credited_amount' => $providerAmount,
                                    'reservation_reference' => $reservation?->reference,
                                ])
                                ->log('payment.unconfirmed_booking_settled_to_wallet');

                            return $lockedPayment->fresh(['reservation.segments.departure']);
                        }
                    }
                }
            }

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
