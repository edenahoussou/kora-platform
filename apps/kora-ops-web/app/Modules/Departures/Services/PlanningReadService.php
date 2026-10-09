<?php

namespace App\Modules\Departures\Services;

use App\Models\User;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\DepartureTemplate;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\Departures\Models\ProgrammingRun;
use Carbon\CarbonImmutable;
use Spatie\Activitylog\Models\Activity;

class PlanningReadService
{
    public function __construct(private PublicationService $publication, private PlanningService $planning) {}

    /** @return array<string, mixed> */
    public function references(): array
    {
        return [
            'lines' => Line::query()->with('stops')->where('status', 'active')->orderBy('name')->get()->map(fn (Line $line): array => [
                'uuid' => $line->uuid, 'label' => $line->name, 'code' => $line->code, 'duration' => $line->estimated_duration_minutes, 'bidirectional' => (bool) $line->bidirectional,
                'stops' => $line->stops->filter(fn (Stop $stop): bool => $stop->getRawOriginal('status') === 'active')->map(fn (Stop $stop): array => ['uuid' => $stop->uuid, 'label' => $stop->name, 'country' => $stop->country])->values()->all(),
            ])->all(),
            'buses' => Bus::query()->orderBy('registration_number')->get()->map(fn (Bus $bus): array => ['uuid' => $bus->uuid, 'label' => $bus->registration_number, 'capacity' => $bus->seat_count, 'model' => $bus->model, 'status' => $bus->getRawOriginal('status')])->all(),
            'people' => User::query()->where('status', 'active')->whereHas('roles', fn ($q) => $q->whereIn('name', ['bus_driver', 'conductor', 'mechanic']))->with('roles')->orderBy('name')->get()->map(fn (User $user): array => ['uuid' => $user->uuid, 'label' => $user->name, 'roles' => $user->roles->pluck('name')->all()])->all(),
            'timezone' => config('app.timezone'),
            'timezones' => $this->westAfricanTimezones(),
        ];
    }

    /**
     * Fuseaux horaires d'Afrique de l'Ouest pertinents pour KORA.
     *
     * @return list<string>
     */
    private function westAfricanTimezones(): array
    {
        $all = \DateTimeZone::listIdentifiers();

        $wanted = [
            'Africa/Abidjan',
            'Africa/Accra',
            'Africa/Bamako',
            'Africa/Banjul',
            'Africa/Bissau',
            'Africa/Conakry',
            'Africa/Dakar',
            'Africa/Freetown',
            'Africa/Lome',
            'Africa/Monrovia',
            'Africa/Nouakchott',
            'Africa/Ouagadougou',
            'Africa/Porto-Novo',
            'Africa/Lagos',
            'Africa/Niamey',
            'Africa/Ndjamena',
        ];

        return array_values(array_filter($all, fn (string $tz): bool => in_array($tz, $wanted, true)));
    }

    /** @return array<string, mixed> */
    public function template(DepartureTemplate $template): array
    {
        $template->loadMissing('versions');

        return [
            'uuid' => $template->uuid, 'name' => $template->name, 'status' => $template->status, 'revision' => $template->revision,
            'draft' => $template->draft,
            'versions' => $template->versions->sortByDesc('number')->map(fn ($v): array => ['uuid' => $v->uuid, 'number' => $v->number, 'slots' => $v->slots, 'createdAt' => $v->created_at?->toIso8601String()])->values()->all(),
            'programmeCount' => DepartureProgramme::query()->whereIn('version_id', $template->versions->pluck('id'))->count(),
        ];
    }

    /** @return array<string, mixed> */
    public function programme(DepartureProgramme $programme): array
    {
        $programme->loadMissing('version.template');

        return [
            'uuid' => $programme->uuid, 'name' => $programme->name,
            'templateUuid' => $programme->version->template->uuid, 'templateName' => $programme->version->template->name,
            'versionUuid' => $programme->version->uuid, 'version' => $programme->version->number,
            'startsOn' => $programme->starts_on->toDateString(), 'endsOn' => $programme->ends_on->toDateString(),
            'automatic' => $programme->automatic, 'horizonDays' => $programme->horizon_days, 'revision' => $programme->revision,
            'exceptions' => $programme->exceptions,
            'prepared' => $programme->occurrences()->whereNull('departure_id')->where('state', '!=', 'excluded')->count(),
            'published' => $programme->occurrences()->whereNotNull('departure_id')->count(),
        ];
    }

    /** @param array<string, mixed>|null $assessment
     * @param  array{booked: int, capacity: int|null, ratio: float|null}|null  $occupancy
     * @return array<string, mixed>
     */
    public function item(PlannedDeparture $item, bool $history = false, ?array $assessment = null, ?array $occupancy = null): array
    {
        $item->loadMissing(['programme', 'departure', 'version']);
        $result = [
            'uuid' => $item->uuid, 'revision' => $item->revision, 'date' => $item->service_date->toDateString(),
            'scheduledAt' => $item->scheduled_at->toIso8601String(), 'arrivalAt' => $item->arrival_at->toIso8601String(),
            'snapshot' => $item->snapshot, 'isException' => $item->is_exception,
            'exceptionKind' => $this->exceptionKind($item),
            'programmeUuid' => $item->programme->uuid, 'programmeName' => $item->programme->name,
            'departureUuid' => $item->departure?->uuid, 'departureStatus' => $item->departure?->getRawOriginal('status'),
            'protected' => $this->planning->protected($item), 'manual' => false,
            'editable' => ! $this->planning->resourcesLocked($item),
            'occupancy' => $occupancy ?? $this->occupancyFor($item->departure?->id, $item->snapshot['bus_uuid'] ?? null),
        ] + ($assessment ?? $this->publication->assess($item));
        if ($history) {
            $result['history'] = Activity::query()->where('subject_type', $item->getMorphClass())->where('subject_id', $item->id)->with('causer')->latest()->limit(30)->get()->map(fn (Activity $entry): array => ['event' => $entry->description, 'operation' => $entry->properties['operation'] ?? null, 'at' => $entry->created_at?->toIso8601String(), 'actor' => $entry->causer?->getAttribute('name')])->all();
        }

        return $result;
    }

    /**
     * Nature de l'écart par rapport au créneau du modèle : « resources » pour
     * une affectation manuelle (bus, équipage), « schedule » pour une vraie
     * exception d'horaire ou de calendrier.
     */
    private function exceptionKind(PlannedDeparture $item): ?string
    {
        if (! $item->is_exception) {
            return null;
        }
        $slot = collect($item->version->slots ?? [])->firstWhere('key', $item->slot_key);
        if ($slot === null) {
            return 'schedule';
        }
        $exception = collect($item->programme->exceptions ?? [])->first(
            fn (array $entry): bool => ($entry['slot_key'] ?? null) === $item->slot_key
                && ($entry['date'] ?? null) === $item->service_date->toDateString(),
        );
        if (($exception['action'] ?? null) === 'add') {
            return 'schedule';
        }
        foreach (['line_uuid', 'time', 'timezone', 'days', 'interval_weeks', 'duration_minutes', 'stops', 'direction', 'return_key', 'return_day_offset', 'turnaround_minutes', 'preparation_minutes'] as $field) {
            if (($item->snapshot[$field] ?? null) != ($slot[$field] ?? null)) {
                return 'schedule';
            }
        }

        return 'resources';
    }

    /**
     * Calcule l'occupation des sièges pour un départ publié.
     *
     * @return array{booked: int, capacity: int|null, ratio: float|null}|null
     */
    private function occupancyFor(?int $departureId, ?string $busUuid): ?array
    {
        if ($departureId === null) {
            return null;
        }

        $booked = ReservationSegment::query()
            ->where('departure_id', $departureId)
            ->whereIn('status', ReservationSegmentStatus::occupyingValues())
            ->distinct('seat_id')
            ->count('seat_id');

        $capacity = null;
        if ($busUuid !== null) {
            $capacity = Bus::query()->where('uuid', $busUuid)->value('seat_count');
        }

        $ratio = $capacity !== null && $capacity > 0 ? round($booked / $capacity, 2) : null;

        return ['booked' => $booked, 'capacity' => $capacity, 'ratio' => $ratio];
    }

    /**
     * Calcule l'occupation en lot pour éviter les requêtes N+1.
     *
     * @param  list<PlannedDeparture>  $occurrences
     * @return array<int, array{booked: int, capacity: int|null, ratio: float|null}|null>
     */
    private function batchOccupancy(array $occurrences): array
    {
        $departureIds = [];
        $busUuids = [];
        foreach ($occurrences as $occ) {
            if ($occ->departure_id !== null) {
                $departureIds[] = $occ->departure_id;
            }
            if (! empty($occ->snapshot['bus_uuid'])) {
                $busUuids[] = $occ->snapshot['bus_uuid'];
            }
        }

        $bookedMap = [];
        if ($departureIds !== []) {
            $bookedMap = ReservationSegment::query()
                ->whereIn('departure_id', $departureIds)
                ->whereIn('status', ReservationSegmentStatus::occupyingValues())
                ->selectRaw('departure_id, count(distinct seat_id) as booked')
                ->groupBy('departure_id')
                ->pluck('booked', 'departure_id')
                ->all();
        }

        $capacityMap = [];
        if ($busUuids !== []) {
            $capacityMap = Bus::query()->whereIn('uuid', array_unique($busUuids))->pluck('seat_count', 'uuid')->all();
        }

        return array_map(function (PlannedDeparture $occ) use ($bookedMap, $capacityMap): ?array {
            if ($occ->departure_id === null) {
                return null;
            }

            $booked = $bookedMap[$occ->departure_id] ?? 0;
            $busUuid = $occ->snapshot['bus_uuid'] ?? null;
            $capacity = $busUuid !== null ? ($capacityMap[$busUuid] ?? null) : null;
            $ratio = $capacity !== null && $capacity > 0 ? round($booked / $capacity, 2) : null;

            return ['booked' => (int) $booked, 'capacity' => $capacity !== null ? (int) $capacity : null, 'ratio' => $ratio];
        }, $occurrences);
    }

    /** @return array<string, mixed> */
    public function departureSlot(Departure $departure): array
    {
        $departure->loadMissing(['line.stops', 'bus', 'driver', 'conductor']);
        $start = $departure->scheduledDepartureAt();
        $timezone = $departure->departure_timezone ?? config('app.timezone');
        $duration = $departure->estimated_arrival_at === null ? ($departure->line->estimated_duration_minutes ?? 180) : (int) $start->diffInMinutes($departure->estimated_arrival_at);
        $stops = $departure->line->stops->values();

        return [
            'key' => $departure->uuid, 'direction' => $departure->isReturnTrip() ? 'return' : 'outbound',
            'line_uuid' => $departure->line->uuid,
            'bus_uuid' => $departure->bus?->uuid, 'driver_uuid' => $departure->driver?->uuid, 'conductor_uuid' => $departure->conductor?->uuid,
            'secondary_driver_uuid' => $departure->secondary_driver_id === null ? null : User::query()->find($departure->secondary_driver_id)?->uuid,
            'mechanic_uuid' => $departure->mechanic_id === null ? null : User::query()->find($departure->mechanic_id)?->uuid,
            'time' => $start->setTimezone($timezone)->format('H:i'), 'timezone' => $timezone,
            'days' => [$start->setTimezone($timezone)->isoWeekday()], 'interval_weeks' => 1,
            'duration_minutes' => max(1, $duration), 'preparation_minutes' => 0,
            'return_key' => null, 'return_day_offset' => 0, 'turnaround_minutes' => 30,
            'stops' => $departure->stop_times ?? $stops->map(fn (Stop $stop, int $index): array => [
                'stop_uuid' => $stop->uuid, 'timezone' => $timezone,
                'arrival_minutes' => $index === $stops->count() - 1 ? $duration : null,
                'departure_minutes' => $index === 0 ? 0 : null,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function manualItem(Departure $departure): array
    {
        $slot = $this->departureSlot($departure);

        return [
            'uuid' => $departure->uuid, 'revision' => 0, 'date' => $departure->scheduledDepartureAt()->setTimezone($slot['timezone'])->toDateString(),
            'scheduledAt' => $departure->scheduledDepartureAt()->toIso8601String(),
            'arrivalAt' => $departure->estimated_arrival_at?->toIso8601String() ?? $departure->scheduledDepartureAt()->addMinutes($slot['duration_minutes'])->toIso8601String(),
            'snapshot' => $slot, 'isException' => false, 'exceptionKind' => null, 'programmeUuid' => '', 'programmeName' => 'Départ ponctuel',
            'departureUuid' => $departure->uuid, 'departureStatus' => $departure->getRawOriginal('status'),
            'protected' => true, 'editable' => false, 'manual' => true, 'state' => $departure->getRawOriginal('status') === 'cancelled' ? 'excluded' : 'published',
            'blockers' => [], 'warnings' => [],
            'occupancy' => $this->occupancyFor($departure->id, $slot['bus_uuid'] ?? null),
        ];
    }

    /** @return array<string, mixed> */
    public function run(ProgrammingRun $run): array
    {
        return ['uuid' => $run->uuid, 'programmeUuid' => $run->programme->uuid, 'operation' => $run->operation, 'status' => $run->status, 'processed' => $run->processed, 'result' => array_diff_key($run->result, array_flip(['selection', 'changes'])), 'at' => $run->updated_at?->toIso8601String()];
    }

    /** @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function workspace(array $filters): array
    {
        $date = CarbonImmutable::parse($filters['date'] ?? now(config('app.timezone'))->toDateString());
        $view = $filters['view'] ?? 'week';
        $from = $view === 'week' ? $date->startOfWeek() : ($view === 'month' ? $date->startOfMonth()->startOfWeek() : $date);
        $until = $view === 'week' ? $date->endOfWeek() : ($view === 'month' ? $date->endOfMonth()->endOfWeek() : $date);
        $query = PlannedDeparture::query()->with(['programme', 'departure', 'version'])->whereDate('service_date', '>=', $from->toDateString())->whereDate('service_date', '<=', $until->toDateString())->orderBy('scheduled_at');
        if (! empty($filters['programme'])) {
            $query->whereHas('programme', fn ($q) => $q->where('uuid', $filters['programme']));
        }
        $occurrences = $query->get();
        $assessments = $this->publication->assessBatch(array_values($occurrences->all()));
        $occupancies = $this->batchOccupancy(array_values($occurrences->all()));
        $items = $occurrences->map(fn (PlannedDeparture $item, int $index): array => $this->item($item, false, $assessments[$index], $occupancies[$index]));
        if (empty($filters['programme'])) {
            $manual = Departure::query()->with(['line.stops', 'bus', 'driver', 'conductor'])->whereNotIn('id', PlannedDeparture::query()->whereNotNull('departure_id')->select('departure_id'))
                ->whereDate('departure_date', '>=', $from->subDay()->toDateString())->whereDate('departure_date', '<=', $until->addDay()->toDateString())->get()
                ->map(fn (Departure $departure): array => $this->manualItem($departure))
                ->filter(fn (array $item): bool => $item['date'] >= $from->toDateString() && $item['date'] <= $until->toDateString());
            $items = $items->concat($manual)->sortBy('scheduledAt');
        }
        $items = $items->filter(function (array $item) use ($filters): bool {
            foreach (['line' => 'line_uuid', 'bus' => 'bus_uuid', 'direction' => 'direction'] as $filter => $field) {
                if (! empty($filters[$filter]) && ($item['snapshot'][$field] ?? null) !== $filters[$filter]) {
                    return false;
                }
            }
            if (! empty($filters['person']) && ! in_array($filters['person'], array_intersect_key($item['snapshot'], array_flip(['driver_uuid', 'secondary_driver_uuid', 'conductor_uuid', 'mechanic_uuid'])), true)) {
                return false;
            }

            return empty($filters['exceptions']) || $item['isException'];
        })->values();
        $counts = ['all' => $items->where('state', '!=', 'excluded')->count()];
        foreach (['ready', 'blocked', 'unassigned', 'published', 'excluded'] as $state) {
            $counts[$state] = $items->where('state', $state)->count();
        }
        $items = $items->filter(fn (array $item): bool => ! empty($filters['state']) ? $item['state'] === $filters['state'] : $item['state'] !== 'excluded')->values();

        return [
            'items' => $items->all(), 'counts' => $counts,
            'filters' => $filters + ['date' => $date->toDateString(), 'view' => $view, 'tab' => 'planning'],
            'from' => $from->toDateString(), 'until' => $until->toDateString(),
            'templates' => DepartureTemplate::query()->with('versions')->latest()->get()->map(fn ($t): array => $this->template($t))->all(),
            'programmes' => DepartureProgramme::query()->with('version.template')->latest()->get()->map(fn ($p): array => $this->programme($p))->all(),
            'runs' => ProgrammingRun::query()->with('programme')->latest('updated_at')->limit(30)->get()->map(fn ($r): array => $this->run($r))->all(),
            'references' => $this->references(),
        ];
    }
}
