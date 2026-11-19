<?php

namespace App\Modules\Ticketing\Actions;

use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Booking\Services\DepartureStopTimeResolver;
use Carbon\CarbonImmutable;

final class BuildTicketLegDataAction
{
    public function __construct(private readonly DepartureStopTimeResolver $stopTimeResolver) {}

    /** @return array<string, mixed> */
    public function execute(ReservationSegment $segment): array
    {
        $departure = $segment->departure;
        $schedule = $this->stopTimeResolver->displaySchedule($departure, $segment->originStop, $segment->destinationStop);
        $boarding = $schedule['departureAt'] !== null ? CarbonImmutable::parse($schedule['departureAt']) : null;
        $arrival = $schedule['arrivalAt'] !== null ? CarbonImmutable::parse($schedule['arrivalAt']) : null;
        $unavailable = 'Horaire non disponible';

        return [
            ...$schedule,
            'directionLabel' => $segment->direction === 'inbound' ? 'Retour' : 'Aller',
            'originCity' => $segment->originStop->city ?? $segment->originStop->name ?? '—',
            'destinationCity' => $segment->destinationStop->city ?? $segment->destinationStop->name ?? '—',
            'dateLabel' => $boarding?->format('d/m/Y') ?? $unavailable,
            'departureTime' => $boarding?->format('H:i') ?? $unavailable,
            'arrivalDateLabel' => $arrival?->format('d/m/Y') ?? $unavailable,
            'arrivalTime' => $arrival?->format('H:i') ?? $unavailable,
            'isInternational' => count($departure->line->countries_crossed ?? []) > 1,
            'lineCode' => $departure->line->code ?? '—',
            'busRegistration' => $departure->bus->registration_number ?? '—',
            'seatCode' => $segment->seat->code ?? '—',
        ];
    }
}
