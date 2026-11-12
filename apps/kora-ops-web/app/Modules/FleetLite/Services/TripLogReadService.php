<?php

namespace App\Modules\FleetLite\Services;

use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Models\Expense;
use App\Modules\FleetLite\Data\OdometerReadingKind;
use App\Modules\FleetLite\Models\DepartureOdometerReading;

/**
 * Carnet de bord d'un départ tel que l'équipage le voit sur OPS Mobile :
 * relevés compteur et dépenses de voyage saisies par l'équipage (toutes,
 * pour éviter qu'un même plein soit déclaré deux fois par le chauffeur et
 * le convoyeur). Aucune donnée de billetterie ni de recette.
 */
class TripLogReadService
{
    /** @return array<string, mixed> */
    public function forDeparture(Departure $departure): array
    {
        $departure->loadMissing(['line', 'bus']);

        $readings = DepartureOdometerReading::query()
            ->with('recorder:id,uuid,name')
            ->where('departure_id', $departure->getKey())
            ->get()
            ->keyBy(fn (DepartureOdometerReading $reading): string => $reading->kind->value);

        $start = $readings->get(OdometerReadingKind::Start->value);
        $end = $readings->get(OdometerReadingKind::End->value);

        $expenses = Expense::query()
            ->with(['tripDetails.submitter:id,uuid,name', 'media'])
            ->where('departure_id', $departure->getKey())
            ->whereHas('tripDetails')
            ->latest('occurred_at')
            ->get();

        return [
            'departure_uuid' => $departure->uuid,
            'bus' => $departure->bus === null ? null : [
                'uuid' => $departure->bus->uuid,
                'registration_number' => $departure->bus->registration_number,
            ],
            'line_code' => $departure->line?->code,
            'last_known_odometer_km' => $this->lastKnownOdometer($departure),
            'odometer' => [
                'start' => $this->reading($start),
                'end' => $this->reading($end),
                'distance_km' => $start !== null && $end !== null ? $end->value_km - $start->value_km : null,
            ],
            'expenses' => $expenses->map(fn (Expense $expense): array => $this->expense($expense))->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    public function expense(Expense $expense): array
    {
        $details = $expense->tripDetails;

        return [
            'uuid' => $expense->uuid,
            'reference' => $expense->reference,
            'client_uuid' => $details?->client_uuid,
            'kind' => $details?->kind->value,
            'kind_label' => $details?->kind->label(),
            'amount' => (int) $expense->amount,
            'currency' => $expense->currency,
            'liters' => $details?->liters !== null ? (float) $details->liters : null,
            'unit_price' => $details?->unit_price,
            'station_label' => $details?->station_label,
            'checkpoint_label' => $details?->checkpoint_label,
            'country_code' => $details?->country_code,
            'odometer_km' => $details?->odometer_km,
            'payment_channel' => $expense->payment_channel->value,
            'occurred_at' => $expense->occurred_at->toISOString(),
            'status' => $expense->status->value,
            'status_label' => $expense->status->label(),
            'rejection_reason' => $expense->rejection_reason,
            'has_photo' => $expense->hasMedia('evidence'),
            'submitted_by' => $details?->submitter?->name,
        ];
    }

    /** @return array<string, mixed>|null */
    private function reading(?DepartureOdometerReading $reading): ?array
    {
        if ($reading === null) {
            return null;
        }

        return [
            'uuid' => $reading->uuid,
            'value_km' => $reading->value_km,
            'recorded_at' => $reading->recorded_at->toISOString(),
            'recorded_by' => $reading->recorder?->name,
        ];
    }

    /**
     * Dernier kilométrage connu du bus (relevé le plus récent hors ce
     * départ) — aide à la saisie et contrôle de cohérence côté mobile.
     */
    private function lastKnownOdometer(Departure $departure): ?int
    {
        $value = DepartureOdometerReading::query()
            ->where('bus_id', $departure->bus_id)
            ->where('departure_id', '!=', $departure->getKey())
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->value('value_km');

        return $value === null ? null : (int) $value;
    }
}
