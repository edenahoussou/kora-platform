<?php

namespace App\Modules\Departures\Services;

use App\Casts\UtcDateTime;
use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\IdentityAccess\Models\Agency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicationService
{
    /** @var array{lines: array<array-key, Line>, buses: array<array-key, Bus>, agencies: list<int>, people: array<string, User>, fares: Collection<int, Fare>, planned: Collection<int, PlannedDeparture>, departures: Collection<int, Departure>}|null */
    private ?array $batch = null;

    public function __construct(private TemplateService $templates) {}

    /** @return array{state: string, blockers: list<string>, warnings: list<string>} */
    public function assess(PlannedDeparture $item, bool $recheck = false): array
    {
        if ($item->state === 'excluded') {
            return ['state' => 'excluded', 'blockers' => [], 'warnings' => []];
        }
        if ($item->departure_id !== null && ! $recheck) {
            return ['state' => $item->departure?->getRawOriginal('status') === 'cancelled' ? 'excluded' : 'published', 'blockers' => [], 'warnings' => []];
        }
        $blockers = [];
        $warnings = [];
        if ($item->scheduled_at->lte(now())) {
            $blockers[] = 'L’heure de départ est passée.';
        }
        $line = $this->batch !== null ? ($this->batch['lines'][$item->line_id] ?? null) : Line::query()->with('stops')->find($item->line_id);
        if ($line === null || $line->getRawOriginal('status') !== 'active') {
            $blockers[] = 'La ligne n’est plus active.';
        } elseif ($line->stops->filter(fn ($stop): bool => $stop->getRawOriginal('status') === 'active')->pluck('uuid')->values()->all() !== array_column($item->snapshot['stops'], 'stop_uuid')) {
            $blockers[] = 'Le parcours a changé. Révisez les horaires du modèle.';
        }
        if (! ($this->batch !== null ? in_array($item->agency_id, $this->batch['agencies'], true) : Agency::query()->whereKey($item->agency_id)->where('status', 'active')->exists())) {
            $blockers[] = 'L’agence de départ n’est plus active.';
        }
        $date = $item->scheduled_at->setTimezone($item->timezone)->toDateString();
        $fares = $this->batch !== null ? $this->batch['fares']->filter(fn (Fare $fare): bool => $fare->line_id === $item->line_id && $fare->effective_from->toDateString() <= $date && ($fare->effective_until === null || $fare->effective_until->toDateString() >= $date))->count() : Fare::query()->where('line_id', $item->line_id)->where('status', 'active')
            ->whereDate('effective_from', '<=', $date)->where(fn ($q) => $q->whereNull('effective_until')->orWhereDate('effective_until', '>=', $date))->count();
        if ($fares === 0) {
            $blockers[] = 'Aucun segment ne possède de tarif actif pour cette date.';
        } elseif ($line !== null && $fares < $line->stops->count() * ($line->stops->count() - 1) / 2) {
            $warnings[] = 'Certains segments n’ont pas de tarif actif.';
        }
        if ($item->bus_id !== null) {
            $bus = $this->batch !== null ? ($this->batch['buses'][$item->bus_id] ?? null) : Bus::query()->with('seats')->find($item->bus_id);
            if ($bus === null || $bus->getRawOriginal('status') !== 'active') {
                $blockers[] = 'Le bus n’est pas actif.';
            } elseif ($bus->seats->count() !== (int) $bus->seat_count || ! $bus->seats->contains(fn ($seat): bool => (bool) $seat->sellable && $seat->getRawOriginal('status') === 'active')) {
                $blockers[] = 'Le plan de sièges du bus est incomplet ou non vendable.';
            }
        }
        try {
            $this->templates->validateCrew($item->snapshot, $this->batch['people'] ?? null);
        } catch (ValidationException) {
            $blockers[] = 'Un membre d’équipage n’est plus admissible ou occupe deux postes.';
        }
        if ($item->driver_id === null || $item->secondary_driver_id === null || $item->conductor_id === null || $item->mechanic_id === null) {
            $warnings[] = 'Équipage incomplet : affectation possible avant l’exploitation.';
        }
        $blockers = array_merge($blockers, $this->conflicts($item));
        if ($item->exists) {
            try {
                $group = $this->rotation($item);
                if (count($group) === 2 && ($group[0]->bus_id !== $group[1]->bus_id || $group[1]->scheduled_at->lt($group[0]->arrival_at->addMinutes((int) $group[0]->snapshot['turnaround_minutes'])))) {
                    $blockers[] = 'La rotation exige le même bus et un délai de retournement suffisant.';
                }
            } catch (ValidationException $error) {
                $blockers[] = implode(' ', array_merge(...array_values($error->errors())));
            }
        }

        return ['state' => $blockers !== [] ? 'blocked' : ($item->bus_id === null ? 'unassigned' : 'ready'), 'blockers' => $blockers, 'warnings' => $warnings];
    }

    /**
     * @param  list<PlannedDeparture>  $items
     * @return list<array{state: string, blockers: list<string>, warnings: list<string>}>
     */
    public function assessBatch(array $items): array
    {
        if ($items === []) {
            return [];
        }
        $from = collect($items)->min('scheduled_at')->subDays(8);
        $until = collect($items)->max('arrival_at')->addDay();
        $people = [];
        foreach ($items as $item) {
            array_push($people, $item->driver_id, $item->secondary_driver_id, $item->conductor_id, $item->mechanic_id);
        }
        $lines = array_map(fn (PlannedDeparture $item): int => $item->line_id, $items);
        $this->batch = [
            'lines' => Line::query()->with('stops')->whereIn('id', $lines)->get()->keyBy('id')->all(),
            'buses' => Bus::query()->with('seats')->whereIn('id', array_filter(array_map(fn (PlannedDeparture $item): ?int => $item->bus_id, $items)))->get()->keyBy('id')->all(),
            'agencies' => array_values(Agency::query()->where('status', 'active')->pluck('id')->all()),
            'people' => User::query()->with('roles')->whereIn('id', array_filter($people))->get()->keyBy('uuid')->all(),
            'fares' => Fare::query()->whereIn('line_id', $lines)->where('status', 'active')->get(),
            'planned' => PlannedDeparture::query()->where('state', '!=', 'excluded')->whereNull('departure_id')->where('scheduled_at', '<', UtcDateTime::databaseValue($until, $items[0]))->where('arrival_at', '>', UtcDateTime::databaseValue($from, $items[0]))->get(),
            'departures' => Departure::query()->with('plannedOccurrence')->whereIn('status', ['scheduled', 'boarding', 'departed'])->whereDate('departure_date', '>=', $from->toDateString())->whereDate('departure_date', '<=', $until->toDateString())->get(),
        ];
        try {
            $results = array_map(fn (PlannedDeparture $item): array => $this->assess($item), $items);
        } finally {
            $this->batch = null;
        }
        $resources = [];
        foreach ($items as $index => $item) {
            if ($item->state === 'excluded') {
                continue;
            }
            foreach (['bus_id', 'driver_id', 'secondary_driver_id', 'conductor_id', 'mechanic_id'] as $field) {
                if ($item->$field !== null) {
                    $key = ($field === 'bus_id' ? 'bus/' : 'person/').$item->$field;
                    $resources[$key][] = $index;
                }
            }
        }
        foreach ($resources as $indexes) {
            usort($indexes, fn (int $a, int $b): int => $items[$a]->scheduled_at->subMinutes((int) ($items[$a]->snapshot['preparation_minutes'] ?? 0))->getTimestamp() <=> $items[$b]->scheduled_at->subMinutes((int) ($items[$b]->snapshot['preparation_minutes'] ?? 0))->getTimestamp());
            foreach ($indexes as $position => $a) {
                for ($n = $position + 1; $n < count($indexes); $n++) {
                    $b = $indexes[$n];
                    if ($items[$b]->scheduled_at->subMinutes((int) ($items[$b]->snapshot['preparation_minutes'] ?? 0))->gte($items[$a]->arrival_at)) {
                        break;
                    }
                    foreach ([$a, $b] as $conflict) {
                        if ($results[$conflict]['state'] !== 'published') {
                            $results[$conflict]['state'] = 'blocked';
                            $results[$conflict]['blockers'][] = 'Une ressource est affectée à plusieurs départs simultanés dans cette prévisualisation.';
                            $results[$conflict]['blockers'] = array_values(array_unique($results[$conflict]['blockers']));
                        }
                    }
                }
            }
        }

        return $results;
    }

    public function guardOperationalResources(Departure $departure): void
    {
        $item = new PlannedDeparture([
            'line_id' => $departure->line_id, 'agency_id' => $departure->agency_id, 'bus_id' => $departure->bus_id,
            'driver_id' => $departure->driver_id, 'secondary_driver_id' => $departure->secondary_driver_id,
            'conductor_id' => $departure->conductor_id, 'mechanic_id' => $departure->mechanic_id,
            'scheduled_at' => $departure->scheduledDepartureAt()->utc(),
            'arrival_at' => $departure->estimated_arrival_at ?? $departure->scheduledDepartureAt()->addDays(7),
            'departure_id' => $departure->exists ? $departure->id : null,
            'timezone' => $departure->departure_timezone ?? config('app.timezone'),
            'snapshot' => ['preparation_minutes' => 0],
        ]);
        $this->lockResources([$item]);
        $conflicts = $this->conflicts($item);
        if ($conflicts !== []) {
            throw ValidationException::withMessages(['bus_uuid' => $conflicts]);
        }
    }

    /** @param list<PlannedDeparture> $items */
    public function lockResources(array $items): void
    {
        Line::query()->whereIn('id', array_map(fn (PlannedDeparture $p): int => $p->line_id, $items))->orderBy('id')->lockForUpdate()->get();
        Bus::query()->whereIn('id', array_filter(array_map(fn (PlannedDeparture $p): ?int => $p->bus_id, $items)))->orderBy('id')->lockForUpdate()->get();
        $people = [];
        foreach ($items as $item) {
            array_push($people, $item->driver_id, $item->secondary_driver_id, $item->conductor_id, $item->mechanic_id);
        }
        User::query()->whereIn('id', array_filter($people))->orderBy('id')->lockForUpdate()->get();
    }

    /** @return list<string> */
    public function conflicts(PlannedDeparture $item): array
    {
        $from = $item->scheduled_at->subMinutes((int) ($item->snapshot['preparation_minutes'] ?? 0));
        $until = $item->arrival_at;
        $people = array_filter([$item->driver_id, $item->secondary_driver_id, $item->conductor_id, $item->mechanic_id]);
        $conflicts = [];
        if ($item->bus_id === null && $people === []) {
            return [];
        }
        $matches = function ($q) use ($item, $people): void {
            $q->whereRaw('1 = 0');
            if ($item->bus_id !== null) {
                $q->orWhere('bus_id', $item->bus_id);
            }
            foreach (['driver_id', 'secondary_driver_id', 'conductor_id', 'mechanic_id'] as $field) {
                $q->orWhereIn($field, $people);
            }
        };
        $planned = $this->batch !== null ? $this->batch['planned']->filter(fn (PlannedDeparture $other): bool => $other->id !== $item->id && $other->arrival_at->gt($from) && $other->scheduled_at->lt($until->addDay()) && $this->sharesResource($item, $other)) : PlannedDeparture::query()->where('state', '!=', 'excluded')->whereNull('departure_id')
            ->when($item->exists, fn ($q) => $q->where('id', '!=', $item->id))
            ->where('scheduled_at', '<', UtcDateTime::databaseValue($until->addDay(), $item))->where('arrival_at', '>', UtcDateTime::databaseValue($from, $item))->where($matches)->get();
        foreach ($planned as $other) {
            if ($other->scheduled_at->subMinutes((int) ($other->snapshot['preparation_minutes'] ?? 0))->lt($until)) {
                $conflicts[] = 'Ressource déjà affectée au départ du '.$other->scheduled_at->setTimezone($other->timezone)->format('d/m H:i').'.';
            }
        }
        $departures = $this->batch !== null ? $this->batch['departures']->filter(fn (Departure $other): bool => $other->id !== $item->departure_id && $this->sharesResource($item, $other)) : Departure::query()->with('plannedOccurrence')->whereIn('status', ['scheduled', 'boarding', 'departed'])
            ->when($item->departure_id !== null, fn ($q) => $q->where('id', '!=', $item->departure_id))
            ->whereDate('departure_date', '>=', $from->subDays(8)->toDateString())
            ->whereDate('departure_date', '<=', $until->addDay()->toDateString())->where($matches)->get();
        foreach ($departures as $departure) {
            $start = $departure->scheduledDepartureAt()->utc()->addMinutes((int) $departure->delay_minutes);
            $end = $departure->estimated_arrival_at === null ? $start->addDays(7) : CarbonImmutable::instance($departure->estimated_arrival_at)->utc()->addMinutes((int) $departure->delay_minutes);
            if ($start->subMinutes((int) ($departure->plannedOccurrence?->snapshot['preparation_minutes'] ?? 0))->lt($until) && $end->gt($from)) {
                $conflicts[] = 'Ressource déjà engagée sur un départ publié le '.$start->setTimezone($item->timezone)->format('d/m H:i').'.';
            }
        }

        return array_values(array_unique($conflicts));
    }

    private function sharesResource(PlannedDeparture $item, PlannedDeparture|Departure $other): bool
    {
        if ($item->bus_id !== null && $item->bus_id === $other->bus_id) {
            return true;
        }

        return array_intersect(array_filter([$item->driver_id, $item->secondary_driver_id, $item->conductor_id, $item->mechanic_id]), array_filter([$other->driver_id, $other->secondary_driver_id, $other->conductor_id, $other->mechanic_id])) !== [];
    }

    /** @return list<PlannedDeparture> */
    public function rotation(PlannedDeparture $item): array
    {
        $slots = $item->version->slots;
        $outbound = collect($slots)->first(fn (array $slot): bool => ($slot['return_key'] ?? null) === $item->slot_key);
        $key = $item->snapshot['return_key'] ?? null;
        if ($key === null && $outbound === null) {
            return [$item];
        }
        $offset = (int) ($outbound['return_day_offset'] ?? $item->snapshot['return_day_offset']);
        $date = $key !== null ? $item->service_date->addDays($offset) : $item->service_date->subDays($offset);
        $other = PlannedDeparture::query()->where('programme_id', $item->programme_id)->where('slot_key', $key ?? $outbound['key'])->whereDate('service_date', $date)->first();
        if ($other === null || $other->state === 'excluded') {
            throw ValidationException::withMessages(['rotation' => 'Le départ lié manque ou est exclu. Préparez les deux dates de la rotation.']);
        }

        return $key !== null ? [$item, $other] : [$other, $item];
    }

    /** @return list<string> */
    public function publish(PlannedDeparture $item, User $actor, ?int $expectedRevision = null): array
    {
        return DB::transaction(function () use ($item, $actor, $expectedRevision): array {
            $group = $this->rotation($item);
            $ids = array_map(fn (PlannedDeparture $p): int => $p->id, $group);
            $locked = PlannedDeparture::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $group = array_map(fn (PlannedDeparture $p): PlannedDeparture => $locked[$p->id], $group);
            $current = $locked[$item->id];
            if ($expectedRevision !== null && $current->departure_id === null && $current->revision !== $expectedRevision) {
                throw ValidationException::withMessages(['revision' => 'Le départ a changé. Rechargez sa fiche.']);
            }
            $this->lockResources($group);
            if (count($group) === 2 && ($group[0]->bus_id !== $group[1]->bus_id || $group[1]->scheduled_at->lt($group[0]->arrival_at->addMinutes((int) $group[0]->snapshot['turnaround_minutes'])))) {
                throw ValidationException::withMessages(['rotation' => 'La rotation exige le même bus et un délai de retournement suffisant.']);
            }
            $published = [];
            foreach ($group as $entry) {
                if ($entry->departure_id !== null) {
                    continue;
                }
                $assessment = $this->assess($entry);
                if ($assessment['state'] !== 'ready') {
                    throw ValidationException::withMessages(['publication' => implode(' ', $assessment['blockers']) ?: 'Un bus doit être affecté avant publication.']);
                }
                $time = $entry->scheduled_at->setTimezone(config('app.timezone'));
                $departure = Departure::query()->create([
                    'line_id' => $entry->line_id, 'agency_id' => $entry->agency_id, 'bus_id' => $entry->bus_id,
                    'driver_id' => $entry->driver_id, 'secondary_driver_id' => $entry->secondary_driver_id,
                    'conductor_id' => $entry->conductor_id, 'mechanic_id' => $entry->mechanic_id,
                    'departure_date' => $time->toDateString(), 'departure_time' => $time->format('H:i:s'),
                    'scheduled_at' => $entry->scheduled_at, 'departure_timezone' => $entry->timezone,
                    'estimated_arrival_at' => $entry->arrival_at->setTimezone(config('app.timezone')),
                    'stop_times' => $entry->snapshot['stops'], 'status' => 'scheduled', 'created_by' => $actor->id,
                ]);
                $entry->update(['departure_id' => $departure->id, 'revision' => $entry->revision + 1]);
                activity()->causedBy($actor)->performedOn($entry)->log('programming.departure.published');
                $published[] = $entry->uuid;
            }

            return $published;
        });
    }
}
