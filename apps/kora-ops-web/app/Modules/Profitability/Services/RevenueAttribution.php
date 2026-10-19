<?php

namespace App\Modules\Profitability\Services;

use App\Casts\UtcDateTime;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Departures\Models\Departure;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Finance\Data\RevenueRefundEvent;
use App\Modules\Finance\Services\PaymentRefundRecognizer;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Profitability\Support\IntegerAllocator;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Recette nette de service par départ : paiements confirmés des réservations
 * rattachées aux départs de la période, ventilés par passager puis par jambe,
 * diminués des annulations reconnues.
 */
class RevenueAttribution
{
    private const CURRENCY = 'XOF';

    /** @var list<RevenueRefundEvent>|null */
    private ?array $recognizedEvents = null;

    public function __construct(private readonly PaymentRefundRecognizer $refundRecognizer) {}

    /**
     * @return array<string, mixed>
     */
    public function build(RevenuePeriod $period): array
    {
        $departures = $this->departures($period);
        $departureMeta = $departures->mapWithKeys(fn (Departure $departure): array => [
            $departure->getKey() => [
                'id' => (int) $departure->getKey(),
                'uuid' => (string) $departure->uuid,
                'busId' => $this->nullableId($departure->getAttribute('bus_id')),
                'lineId' => $this->nullableId($departure->getAttribute('line_id')),
                'scheduledAt' => $departure->scheduledDepartureAt()->toIso8601String(),
                'status' => (string) $departure->status->value,
            ],
        ])->all();

        $segments = ReservationSegment::query()
            ->whereIn('departure_id', array_keys($departureMeta))
            ->get(['id', 'reservation_id', 'leg_order', 'passenger_index', 'reservation_passenger_id', 'departure_id', 'seat_id', 'status']);

        $reservationIds = array_values(array_unique(array_map(
            static fn (mixed $id): int => (int) $id,
            $segments->pluck('reservation_id')->all(),
        )));

        $reservations = $reservationIds === []
            ? collect()
            : Reservation::query()
                ->with('sellingAgency:id,uuid,name')
                ->whereIn('id', $reservationIds)
                ->get(['id', 'uuid', 'reference', 'channel', 'selling_agency_id', 'total_amount', 'total_snapshot']);

        $reservationsById = $reservations->keyBy('id');
        $segmentsByReservation = $segments->groupBy('reservation_id');

        $payments = $reservationIds === []
            ? collect()
            : Payment::query()
                ->whereIn('reservation_id', $reservationIds)
                ->where('status', PaymentStatus::Confirmed->value)
                ->where('currency', self::CURRENCY)
                ->get(['id', 'uuid', 'reservation_id', 'amount']);

        $paymentUuids = array_values(array_map(
            static fn (mixed $uuid): string => (string) $uuid,
            $payments->pluck('uuid')->all(),
        ));

        $recognizedByPayment = $this->recognizedRefunds();
        $postPeriodDeductions = $this->postPeriodDeductions($period, $paymentUuids);

        /** @var array<int, array{gross: int, deductions: int, net: int, seatsSold: int}> $byDeparture */
        $byDeparture = [];
        foreach ($departureMeta as $departureId => $meta) {
            $byDeparture[$departureId] = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'seatsSold' => 0];
        }

        /** @var array<string, array{key: string, uuid: string|null, name: string, gross: int, deductions: int, net: int, seatsSold: int}> $byAgency */
        $byAgency = [];
        $byAgencyDeparture = [];
        $unattributed = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'paymentCount' => 0];
        $total = $this->metric();
        $seatsByDeparture = $this->seatsByDeparture($segments);

        foreach ($payments as $payment) {
            $reservation = $reservationsById->get($payment->reservation_id);
            if (! $reservation instanceof Reservation) {
                continue;
            }

            $agencyKey = $this->agencyKey($reservation);
            if (! isset($byAgency[$agencyKey])) {
                $byAgency[$agencyKey] = $this->agencyMetric($agencyKey, $reservation);
            }
            $recognized = min((int) $payment->amount, $recognizedByPayment[(string) $payment->uuid] ?? 0);

            $reservationSegments = $segmentsByReservation->get($reservation->getKey(), collect());
            $shares = $reservationSegments->isEmpty()
                ? []
                : $this->paymentShares((int) $payment->amount, $reservation, $reservationSegments, $departureMeta);

            if ($shares === []) {
                $unattributed['gross'] += (int) $payment->amount;
                $unattributed['deductions'] += $recognized;
                $unattributed['paymentCount']++;
            } else {
                foreach ($shares as $departureId => $amount) {
                    if (! isset($byDeparture[$departureId])) {
                        $byDeparture[$departureId] = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'seatsSold' => 0];
                    }
                    $byDeparture[$departureId]['gross'] += $amount;
                    $byAgencyDeparture[$agencyKey][$departureId] = ($byAgencyDeparture[$agencyKey][$departureId] ?? 0) + $amount;
                }

                if ($recognized > 0) {
                    $deductionShares = $this->paymentShares($recognized, $reservation, $reservationSegments, $departureMeta);
                    foreach ($deductionShares as $departureId => $amount) {
                        if (! isset($byDeparture[$departureId])) {
                            $byDeparture[$departureId] = ['gross' => 0, 'deductions' => 0, 'net' => 0, 'seatsSold' => 0];
                        }
                        $byDeparture[$departureId]['deductions'] += $amount;
                    }
                }
            }

            $byAgency[$agencyKey]['gross'] += (int) $payment->amount;
            $byAgency[$agencyKey]['deductions'] += $recognized;
            $total['gross'] += (int) $payment->amount;
            $total['deductions'] += $recognized;
        }

        foreach ($byDeparture as $departureId => $metric) {
            $byDeparture[$departureId]['net'] = $metric['gross'] - $metric['deductions'];
            $byDeparture[$departureId]['seatsSold'] = $seatsByDeparture[$departureId] ?? 0;
        }

        foreach ($byAgency as $key => $metric) {
            $byAgency[$key]['net'] = $metric['gross'] - $metric['deductions'];
            $byAgency[$key]['seatsSold'] = $this->agencySeats($segments, $reservationsById, $key);
        }

        $unattributed['net'] = $unattributed['gross'] - $unattributed['deductions'];
        $total['net'] = $total['gross'] - $total['deductions'];

        return [
            'currency' => self::CURRENCY,
            'departureMeta' => $departureMeta,
            'total' => $total,
            'byDeparture' => $byDeparture,
            'byBus' => $this->aggregateBy($byDeparture, $departureMeta, 'busId'),
            'byLine' => $this->aggregateBy($byDeparture, $departureMeta, 'lineId'),
            'byAgency' => $byAgency,
            'byAgencyDeparture' => $byAgencyDeparture,
            'unattributed' => $unattributed,
            'postPeriodDeductions' => $postPeriodDeductions,
            'nonXofPaymentCount' => $this->nonXofPaymentCount($reservationIds),
        ];
    }

    /**
     * @return Collection<int, Departure>
     */
    private function departures(RevenuePeriod $period): Collection
    {
        $model = new Departure;

        return Departure::query()
            ->withTrashed()
            ->where(function (Builder $query) use ($period, $model): void {
                $query->where(function (Builder $inner) use ($period, $model): void {
                    $inner->where('scheduled_at', '>=', UtcDateTime::databaseValue($period->startUtc(), $model))
                        ->where('scheduled_at', '<', UtcDateTime::databaseValue($period->endExclusiveUtc(), $model));
                })->orWhere(function (Builder $inner) use ($period): void {
                    $inner->whereNull('scheduled_at')
                        ->whereBetween('departure_date', [$period->localStart->toDateString(), $period->localEnd->toDateString()]);
                });
            })
            ->get(['id', 'uuid', 'bus_id', 'line_id', 'scheduled_at', 'departure_date', 'departure_time', 'departure_timezone', 'status']);
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @param  array<int, array<string, mixed>>  $departureMeta
     * @return array<int, int>
     */
    private function paymentShares(
        int $amount,
        Reservation $reservation,
        Collection $segments,
        array $departureMeta,
    ): array {
        if ($amount <= 0) {
            return [];
        }

        $segmentsByPassenger = $segments->groupBy(
            fn (ReservationSegment $segment): string => $segment->passenger_index === null
                ? 'segment-'.$segment->getKey()
                : 'passenger-'.$segment->passenger_index,
        );

        $snapshot = $reservation->total_snapshot ?? [];
        $allocations = is_array($snapshot['passenger_allocations'] ?? null) ? $snapshot['passenger_allocations'] : [];
        $passengerWeights = [];

        foreach ($segmentsByPassenger as $passengerKey => $passengerSegments) {
            $index = $passengerSegments->first()->passenger_index;
            $passengerWeights[$passengerKey] = $index !== null && isset($allocations[$index])
                ? (int) $allocations[$index]
                : 0;
        }

        if (array_sum($passengerWeights) <= 0) {
            $passengerWeights = array_fill_keys(array_keys($passengerWeights), 1);
        }

        $shares = [];

        foreach (IntegerAllocator::distribute($amount, $passengerWeights) as $passengerKey => $passengerAmount) {
            $legWeights = [];

            foreach ($segmentsByPassenger->get($passengerKey) as $segment) {
                if (! isset($departureMeta[$segment->departure_id])) {
                    continue;
                }

                $legWeights[$segment->getKey()] = $this->legWeight($snapshot, $segment, $departureMeta);
            }

            if ($legWeights === []) {
                continue;
            }

            if (array_sum($legWeights) <= 0) {
                $legWeights = array_fill_keys(array_keys($legWeights), 1);
            }

            foreach (IntegerAllocator::distribute($passengerAmount, $legWeights) as $segmentId => $segmentAmount) {
                $segment = $segments->firstWhere('id', $segmentId);
                if ($segment === null) {
                    continue;
                }

                $shares[$segment->departure_id] = ($shares[$segment->departure_id] ?? 0) + $segmentAmount;
            }
        }

        return array_filter($shares, static fn (int $share): bool => $share !== 0);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  array<int, array<string, mixed>>  $departureMeta
     */
    private function legWeight(array $snapshot, ReservationSegment $segment, array $departureMeta): int
    {
        $legs = is_array($snapshot['legs'] ?? null) ? $snapshot['legs'] : [];
        $departureUuid = $departureMeta[$segment->departure_id]['uuid'] ?? null;

        foreach ($legs as $leg) {
            if (! is_array($leg)) {
                continue;
            }

            if ((int) ($leg['leg_order'] ?? 0) === (int) $segment->leg_order
                && ($leg['departure_uuid'] ?? null) === $departureUuid) {
                return (int) ($leg['final_amount'] ?? 0);
            }
        }

        return 0;
    }

    /** @return array<string, int> */
    private function recognizedRefunds(): array
    {
        $recognized = [];

        foreach ($this->recognizedEvents() as $event) {
            if ($event->paymentUuid === null || $event->recognized <= 0) {
                continue;
            }

            $recognized[$event->paymentUuid] = ($recognized[$event->paymentUuid] ?? 0) + $event->recognized;
        }

        return $recognized;
    }

    /** @return list<RevenueRefundEvent> */
    private function recognizedEvents(): array
    {
        if ($this->recognizedEvents === null) {
            $this->recognizedEvents = iterator_to_array(
                $this->refundRecognizer->eventsUntil(CarbonImmutable::now('UTC')->addSecond()),
                false,
            );
        }

        return $this->recognizedEvents;
    }

    /** @param list<string> $paymentUuids */
    private function postPeriodDeductions(RevenuePeriod $period, array $paymentUuids): int
    {
        if ($paymentUuids === []) {
            return 0;
        }

        $uuids = array_flip($paymentUuids);
        $total = 0;

        foreach ($this->recognizedEvents() as $event) {
            if ($event->paymentUuid !== null
                && isset($uuids[$event->paymentUuid])
                && $event->date >= $period->endExclusiveUtc()) {
                $total += $event->recognized;
            }
        }

        return $total;
    }

    /** @param list<int> $reservationIds */
    private function nonXofPaymentCount(array $reservationIds): int
    {
        if ($reservationIds === []) {
            return 0;
        }

        return Payment::query()
            ->whereIn('reservation_id', $reservationIds)
            ->where('status', PaymentStatus::Confirmed->value)
            ->where('currency', '!=', self::CURRENCY)
            ->count();
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @return array<int, int>
     */
    private function seatsByDeparture(Collection $segments): array
    {
        $occupying = ReservationSegmentStatus::occupyingValues();

        return $segments
            ->filter(fn (ReservationSegment $segment): bool => in_array($segment->status->value, $occupying, true))
            ->groupBy('departure_id')
            ->map(fn (Collection $group): int => $group->pluck('seat_id')->filter()->unique()->count())
            ->all();
    }

    /**
     * @param  Collection<int, ReservationSegment>  $segments
     * @param  Collection<int, Reservation>  $reservationsById
     */
    private function agencySeats(Collection $segments, Collection $reservationsById, string $agencyKey): int
    {
        $occupying = ReservationSegmentStatus::occupyingValues();

        return $segments
            ->filter(function (ReservationSegment $segment) use ($reservationsById, $agencyKey, $occupying): bool {
                $reservation = $reservationsById->get($segment->reservation_id);

                return $reservation instanceof Reservation
                    && in_array($segment->status->value, $occupying, true)
                    && $this->agencyKey($reservation) === $agencyKey;
            })
            ->count();
    }

    private function agencyKey(Reservation $reservation): string
    {
        if ($reservation->selling_agency_id !== null) {
            return 'agency-'.$reservation->selling_agency_id;
        }

        return in_array($reservation->channel?->value, ['mobile', 'company_portal'], true)
            ? 'digital_without_agency'
            : 'unqualified';
    }

    /** @return array{key: string, uuid: string|null, name: string, gross: int, deductions: int, net: int, seatsSold: int} */
    private function agencyMetric(string $key, Reservation $reservation): array
    {
        $agency = $reservation->getRelation('sellingAgency');

        return [
            'key' => $key,
            'uuid' => $agency instanceof Agency ? (string) $agency->uuid : null,
            'name' => $agency instanceof Agency ? (string) $agency->name : match ($key) {
                'digital_without_agency' => 'Application passager — hors agence',
                default => 'Attribution à qualifier',
            },
            'gross' => 0,
            'deductions' => 0,
            'net' => 0,
            'seatsSold' => 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $byDeparture
     * @param  array<int, array<string, mixed>>  $departureMeta
     * @return array<int, array<string, int>>
     */
    private function aggregateBy(array $byDeparture, array $departureMeta, string $dimension): array
    {
        $aggregated = [];

        foreach ($byDeparture as $departureId => $metric) {
            $key = $departureMeta[$departureId][$dimension] ?? null;
            if ($key === null) {
                continue;
            }

            $aggregated[$key] ??= $this->metric() + ['seatsSold' => 0];
            $aggregated[$key]['gross'] += $metric['gross'];
            $aggregated[$key]['deductions'] += $metric['deductions'];
            $aggregated[$key]['net'] += $metric['net'];
            $aggregated[$key]['seatsSold'] += $metric['seatsSold'];
        }

        return $aggregated;
    }

    /** @return array{gross: int, deductions: int, net: int} */
    private function metric(): array
    {
        return ['gross' => 0, 'deductions' => 0, 'net' => 0];
    }

    private function nullableId(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
