<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Finance\Data\RevenueRefundEvent;
use App\Modules\Payments\Data\PaymentStatus;
use Carbon\CarbonImmutable;
use Generator;
use Illuminate\Support\Facades\DB;

/**
 * Reconnaissance des annulations plafonnées par paiement, partagée entre la
 * vue Recettes (date de création) et la vue Rentabilité (période de service).
 */
class PaymentRefundRecognizer
{
    private const CURRENCY = 'XOF';

    /**
     * @return array{current: list<RevenueRefundEvent>, comparison: list<RevenueRefundEvent>}
     */
    public function forPeriod(RevenuePeriod $period): array
    {
        $events = ['current' => [], 'comparison' => []];

        foreach ($this->eventsUntil($period->endExclusiveUtc()) as $event) {
            if ($period->contains($event->date)) {
                $events['current'][] = $event;
            } elseif ($period->containsComparison($event->date)) {
                $events['comparison'][] = $event;
            }
        }

        return $events;
    }

    /**
     * @return list<RevenueRefundEvent>
     */
    public function forRange(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $events = [];

        foreach ($this->eventsUntil($end) as $event) {
            if ($event->date >= $start) {
                $events[] = $event;
            }
        }

        return $events;
    }

    /**
     * Iterate every earlier obligation because the remaining refundable balance depends on its chronological history.
     *
     * @return Generator<int, RevenueRefundEvent>
     */
    public function eventsUntil(CarbonImmutable $end): Generator
    {
        $recognizedByPayment = [];
        $rows = DB::table('payment_refunds as refunds')
            ->leftJoin('payments', 'payments.id', '=', 'refunds.payment_id')
            ->leftJoin('reservations', 'reservations.id', '=', 'refunds.reservation_id')
            ->leftJoin('agencies', 'agencies.id', '=', 'reservations.selling_agency_id')
            ->where('refunds.created_at', '<', $end)
            ->select([
                'refunds.id',
                'refunds.uuid',
                'refunds.payment_id',
                'refunds.amount',
                'refunds.status',
                'refunds.destination',
                'refunds.created_at as refund_created_at',
                'payments.uuid as payment_uuid',
                'payments.amount as payment_amount',
                'payments.status as payment_status',
                'payments.method as payment_method',
                'payments.provider as payment_provider',
                'payments.provider_reference as payment_provider_reference',
                'payments.provider_transaction_id as payment_provider_transaction_id',
                'payments.mobile_money_network as payment_network',
                'payments.currency as payment_currency',
                'reservations.reference as reservation_reference',
                'reservations.channel as reservation_channel',
                'agencies.uuid as agency_uuid',
                'agencies.name as agency_name',
            ])
            ->orderBy('refunds.payment_id')
            ->orderBy('refunds.created_at')
            ->orderBy('refunds.id')
            ->cursor();

        foreach ($rows as $row) {
            $data = (array) $row;
            $paymentId = $data['payment_id'] === null ? null : (int) $data['payment_id'];
            $eligible = $paymentId !== null
                && $data['payment_status'] === PaymentStatus::Confirmed->value
                && $data['payment_currency'] === self::CURRENCY;
            $alreadyRecognized = $paymentId === null ? 0 : ($recognizedByPayment[$paymentId] ?? 0);
            $remaining = $eligible ? max(0, (int) $data['payment_amount'] - $alreadyRecognized) : 0;
            $recognized = min((int) $data['amount'], $remaining);

            if ($paymentId !== null && $eligible) {
                $recognizedByPayment[$paymentId] = $alreadyRecognized + $recognized;
            }

            yield new RevenueRefundEvent(
                uuid: (string) $data['uuid'],
                paymentUuid: $data['payment_uuid'] === null ? null : (string) $data['payment_uuid'],
                reservationReference: $data['reservation_reference'] === null ? null : (string) $data['reservation_reference'],
                agencyUuid: $data['agency_uuid'] === null ? null : (string) $data['agency_uuid'],
                agencyName: $data['agency_name'] === null ? 'Sans agence' : (string) $data['agency_name'],
                channel: $data['reservation_channel'] === null ? 'unknown' : (string) $data['reservation_channel'],
                method: $data['payment_method'] === null ? 'unknown' : (string) $data['payment_method'],
                provider: $data['payment_provider'] === null ? null : (string) $data['payment_provider'],
                providerReference: $data['payment_provider_reference'] === null ? null : (string) $data['payment_provider_reference'],
                providerTransactionId: $data['payment_provider_transaction_id'] === null ? null : (string) $data['payment_provider_transaction_id'],
                network: $data['payment_network'] === null ? null : (string) $data['payment_network'],
                amount: (int) $data['amount'],
                recognized: $recognized,
                excess: $eligible ? max(0, (int) $data['amount'] - $recognized) : 0,
                status: (string) $data['status'],
                destination: (string) $data['destination'],
                date: CarbonImmutable::parse((string) $data['refund_created_at'], 'UTC'),
                unlinked: $paymentId === null,
                ineligiblePayment: $paymentId !== null && ! $eligible,
            );
        }
    }
}
