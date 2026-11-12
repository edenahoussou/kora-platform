<?php

namespace App\Modules\FleetLite\Services;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Models\Expense;
use App\Modules\FleetLite\Data\OdometerReadingKind;
use App\Modules\FleetLite\Data\TripExpenseKind;
use App\Modules\FleetLite\Models\DepartureOdometerReading;
use Illuminate\Support\Collection;

/**
 * Fiche bus (OPS Web, onglets « Carburant » et « Kilométrage ») : données du
 * carnet de bord saisies par l'équipage sur OPS Mobile. Les montants ne sont
 * renvoyés qu'aux comptes autorisés à consulter les dépenses.
 */
class BusTripLogReadService
{
    private const int LIMIT = 30;

    private const string TIMEZONE = 'Africa/Porto-Novo';

    /** @return array<string, mixed> */
    public function forBus(Bus $bus, User $viewer): array
    {
        $canViewAmounts = $viewer->can('expenses.view-network')
            || $viewer->can('expenses.view-agency')
            || $viewer->can('profitability.view');

        $mileage = $this->mileage($bus);
        $fuel = $this->fuel($bus, $canViewAmounts);

        $liters = (float) collect($fuel['items'])->sum('liters');
        $fuel['stats']['averageConsumption'] = $mileage['stats']['totalDistanceKm'] > 0 && $liters > 0
            ? round($liters * 100 / $mileage['stats']['totalDistanceKm'], 1)
            : null;

        return [
            'canViewAmounts' => $canViewAmounts,
            'mileage' => $mileage,
            'fuel' => $fuel,
        ];
    }

    /** @return array<string, mixed> */
    private function mileage(Bus $bus): array
    {
        /** @var Collection<int, DepartureOdometerReading> $readings */
        $readings = DepartureOdometerReading::query()
            ->with(['recorder:id,name', 'departure' => fn ($query) => $query->with('line:id,code,name')])
            ->where('bus_id', $bus->getKey())
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT * 2)
            ->get();

        $latest = $readings->first();

        $trips = $readings
            ->groupBy('departure_id')
            ->map(function (Collection $group): array {
                /** @var DepartureOdometerReading|null $start */
                $start = $group->first(fn (DepartureOdometerReading $reading): bool => $reading->kind === OdometerReadingKind::Start);
                /** @var DepartureOdometerReading|null $end */
                $end = $group->first(fn (DepartureOdometerReading $reading): bool => $reading->kind === OdometerReadingKind::End);
                /** @var DepartureOdometerReading $any */
                $any = $start ?? $end ?? $group->first();
                $departure = $any->departure;

                return [
                    'departureUuid' => $departure?->uuid,
                    'departureLabel' => $this->departureLabel($departure),
                    'departureAt' => $departure?->scheduledDepartureAt()->toIso8601String(),
                    'startKm' => $start?->value_km,
                    'startAt' => $start?->recorded_at->toIso8601String(),
                    'startBy' => $start?->recorder?->name,
                    'endKm' => $end?->value_km,
                    'endAt' => $end?->recorded_at->toIso8601String(),
                    'endBy' => $end?->recorder?->name,
                    'distanceKm' => $start !== null && $end !== null ? $end->value_km - $start->value_km : null,
                    'anomaly' => null,
                    'sortKey' => ($start ?? $any)->recorded_at->getTimestamp(),
                ];
            })
            ->sortByDesc('sortKey')
            ->values()
            ->take(self::LIMIT);

        // Contrôle de cohérence : un départ qui commence sous l'arrivée du
        // voyage précédent du même bus (compteur mal relevé).
        $items = $trips->all();
        foreach ($items as $index => $trip) {
            $previous = $items[$index + 1] ?? null;
            if ($previous !== null && $trip['startKm'] !== null && $previous['endKm'] !== null && $trip['startKm'] < $previous['endKm']) {
                $items[$index]['anomaly'] = sprintf(
                    'Départ inférieur à l’arrivée du voyage précédent (%s km).',
                    number_format((int) $previous['endKm'], 0, ',', ' '),
                );
            }
            unset($items[$index]['sortKey']);
        }

        $complete = collect($items)->whereNotNull('distanceKm');

        return [
            'stats' => [
                'currentKm' => $latest?->value_km,
                'lastReadingAt' => $latest?->recorded_at->toIso8601String(),
                'lastReadingBy' => $latest?->recorder?->name,
                'totalDistanceKm' => (int) $complete->sum('distanceKm'),
                'tripsCount' => $complete->count(),
                'averageTripKm' => $complete->isNotEmpty() ? (int) round($complete->avg('distanceKm')) : null,
                'anomalies' => collect($items)->whereNotNull('anomaly')->count(),
            ],
            'items' => array_values($items),
        ];
    }

    /** @return array{stats: array<string, mixed>, items: list<array<string, mixed>>} */
    private function fuel(Bus $bus, bool $canViewAmounts): array
    {
        $expenses = Expense::query()
            ->with(['tripDetails.submitter:id,name', 'departure.line:id,code,name', 'media'])
            ->where('bus_id', $bus->getKey())
            ->whereHas('tripDetails', fn ($query) => $query->where('kind', TripExpenseKind::Fuel->value))
            ->whereIn('status', ['pending', 'approved'])
            ->latest('occurred_at')
            ->limit(self::LIMIT)
            ->get();

        $items = array_values($expenses->map(fn (Expense $expense): array => [
            'uuid' => $expense->uuid,
            'reference' => $expense->reference,
            'occurredAt' => $expense->occurred_at->toIso8601String(),
            'liters' => $expense->tripDetails?->liters !== null ? (float) $expense->tripDetails->liters : null,
            'unitPrice' => $canViewAmounts ? $expense->tripDetails?->unit_price : null,
            'amount' => $canViewAmounts ? (int) $expense->amount : null,
            'station' => $expense->tripDetails?->station_label,
            'odometerKm' => $expense->tripDetails?->odometer_km,
            'status' => $expense->status->value,
            'statusLabel' => $expense->status->label(),
            'submittedBy' => $expense->tripDetails?->submitter?->name,
            'departureLabel' => $this->departureLabel($expense->departure),
            'hasEvidence' => $expense->hasMedia('evidence'),
            'url' => route('expenses.show', $expense->uuid),
        ])->all());

        $last = $items[0] ?? null;

        return [
            'stats' => [
                'lastFillAt' => $last['occurredAt'] ?? null,
                'lastStation' => $last['station'] ?? null,
                'lastLiters' => $last['liters'] ?? null,
                'lastAmount' => $last['amount'] ?? null,
                'totalLiters' => round((float) collect($items)->sum('liters'), 2),
                'totalAmount' => $canViewAmounts ? (int) collect($items)->sum('amount') : null,
                'pendingCount' => collect($items)->where('status', 'pending')->count(),
            ],
            'items' => $items,
        ];
    }

    private function departureLabel(?Departure $departure): ?string
    {
        if ($departure === null) {
            return null;
        }

        return trim(($departure->line->code ?? 'Ligne').' · '.$departure->scheduledDepartureAt()->setTimezone(self::TIMEZONE)->format('d/m/Y H:i'));
    }
}
