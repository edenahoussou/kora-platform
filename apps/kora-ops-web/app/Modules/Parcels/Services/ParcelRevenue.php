<?php

namespace App\Modules\Parcels\Services;

use App\Modules\Departures\Models\Departure;
use App\Modules\Finance\Data\RevenuePeriod;
use App\Modules\Parcels\Models\ParcelCharge;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Payments\Data\PaymentRefundStatus;
use Generator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class ParcelRevenue
{
    /**
     * @param  list<int>  $departureIds
     * @return array{enabled: bool, net: int, unassigned: int, byDeparture: array<int, int>}
     */
    public function profitability(RevenuePeriod $period, array $departureIds, ?int $agencyId = null, bool $includeUnassigned = true): array
    {
        $rows = $this->entries($period, $departureIds);
        $byDeparture = [];
        $unassigned = 0;
        foreach ($rows as $row) {
            if ($agencyId !== null && $row['agencyId'] !== $agencyId) {
                continue;
            }
            if ($row['departureId'] === null && $includeUnassigned) {
                $unassigned += $row['net'];
            } elseif (in_array($row['departureId'], $departureIds, true)) {
                $id = $row['departureId'];
                $byDeparture[$id] = ($byDeparture[$id] ?? 0) + $row['net'];
            }
        }

        return [
            'enabled' => $byDeparture !== [] || $unassigned !== 0 || ParcelCorridor::query()->where('enabled', true)->exists(),
            'net' => array_sum($byDeparture) + $unassigned,
            'unassigned' => $unassigned,
            'byDeparture' => $byDeparture,
        ];
    }

    /** @return array{transported: int, net: int} */
    public function departure(Departure $departure): array
    {
        $assignments = DB::table('parcel_assignments')->where('departure_id', $departure->id)->whereNotNull('transported_at');
        $transported = $departure->departed_at === null ? 0 : (int) (clone $assignments)->count();
        $net = $transported === 0 ? 0 : (int) $assignments->join('parcel_charges', function ($join): void {
            $join->on('parcel_charges.parcel_id', '=', 'parcel_assignments.parcel_id')
                ->on('parcel_charges.return_leg', '=', 'parcel_assignments.return_leg');
        })->whereNotNull('parcel_charges.settled_payment_id')->sum('parcel_charges.net');

        return ['transported' => $transported, 'net' => $net];
    }

    /** @return array{net: int, gross: int, unassigned: int, entryCount: int} */
    public function totals(RevenuePeriod $period): array
    {
        $totals = ['net' => 0, 'gross' => 0, 'unassigned' => 0, 'entryCount' => 0];
        foreach ($this->entries($period) as $row) {
            $totals['entryCount']++;
            $totals['net'] += $row['net'];
            $totals['gross'] += $row['gross'];
            if ($row['departureId'] === null) {
                $totals['unassigned'] += $row['net'];
            }
        }

        return $totals;
    }

    /** @return Generator<int, array<int, string|int|null>> */
    public function export(RevenuePeriod $period): Generator
    {
        foreach ($this->entries($period) as $row) {
            yield [$row['date'], $row['type'], $row['uuid'], $row['reference'], $row['hub'],
                $row['origin'], $row['destination'], $row['departureUuid'], $row['method'], $row['net'], $row['gross']];
        }
    }

    /**
     * @param  list<int>  $departureIds
     * @return Generator<int, array{date: ?string, type: string, uuid: string, reference: ?string, hub: ?string, origin: ?string, destination: ?string, departureUuid: ?string, departureId: ?int, agencyId: ?int, method: ?string, net: int, gross: int}>
     */
    private function entries(RevenuePeriod $period, array $departureIds = []): Generator
    {
        $confirmed = fn (Builder $query) => $query->where('confirmed_at', '>=', $period->startUtc())->where('confirmed_at', '<', $period->endExclusiveUtc());
        $completed = fn (Builder $query) => $query->where('status', PaymentRefundStatus::Completed)
            ->where('completed_at', '>=', $period->startUtc())->where('completed_at', '<', $period->endExclusiveUtc());
        $transported = fn (Builder $query) => $query->whereNotNull('transported_at')->whereIn('departure_id', $departureIds);
        $charges = ParcelCharge::query()
            ->whereHas('settledPayment')
            ->where(fn (Builder $query) => $query
                ->whereHas('settledPayment', fn (Builder $payments) => $payments->where($confirmed)->orWhereHas('refunds', $completed))
                ->when($departureIds !== [], fn (Builder $charges) => $charges->orWhereHas('parcel.assignments', $transported)))
            ->with(['settledPayment.refunds' => fn (HasMany $refunds) => $departureIds === []
                ? $completed($refunds->getQuery()) : $refunds->where('status', PaymentRefundStatus::Completed), 'parcel.corridor.originHub.agency', 'parcel.corridor.destinationHub.agency',
                'parcel.corridor.originHub.stop', 'parcel.corridor.destinationHub.stop', 'parcel.assignments.departure'])
            ->lazyById(250);
        foreach ($charges as $charge) {
            $parcel = $charge->parcel;
            $payment = $charge->settledPayment;
            if ($parcel === null || $payment === null) {
                continue;
            }
            $assignment = $parcel->assignments->first(fn ($row) => $row->return_leg === $charge->return_leg
                && $row->transported_at !== null);
            $shared = [
                'reference' => $parcel->reference,
                'hub' => $parcel->corridor?->originHub?->agency?->name,
                'origin' => $parcel->corridor?->originHub?->stop?->city,
                'destination' => $parcel->corridor?->destinationHub?->stop?->city,
                'departureUuid' => $assignment?->departure?->uuid,
                'departureId' => $assignment === null ? null : (int) $assignment->departure_id,
                'agencyId' => $parcel->corridor?->originHub?->agency_id,
                'method' => $payment->method?->value,
            ];
            $withinDepartures = $assignment !== null && in_array((int) $assignment->departure_id, $departureIds, true);
            $confirmedAt = $payment->confirmed_at?->toImmutable();
            if ($confirmedAt !== null && ($withinDepartures || $period->contains($confirmedAt))) {
                yield $shared + [
                    'date' => $confirmedAt->toIso8601String(), 'type' => $charge->return_leg ? 'Retour colis' : 'Vente colis',
                    'uuid' => $payment->uuid, 'net' => (int) $charge->net, 'gross' => (int) $charge->total,
                ];
            }
            foreach ($payment->refunds as $refund) {
                if (! $withinDepartures && ($refund->completed_at === null || ! $period->contains($refund->completed_at->toImmutable()))) {
                    continue;
                }
                yield $shared + [
                    'date' => $refund->completed_at?->toIso8601String(), 'type' => 'Restitution colis',
                    'uuid' => $refund->uuid, 'net' => -(int) round($charge->net * $refund->amount / $charge->total), 'gross' => -$refund->amount,
                ];
            }
        }
    }
}
