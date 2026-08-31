<?php

namespace App\Modules\Departures\Services;

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\DepartureTemplateVersion;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\Departures\Models\ProgrammingRun;
use App\Modules\DynamicPricing\Models\PriceQuote;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlanningService
{
    public function __construct(private RecurrenceService $recurrence, private PublicationService $publication, private TemplateService $templates) {}

    /** @param array<string, mixed> $data */
    public function programme(array $data, User $actor, bool $save = true): DepartureProgramme
    {
        $version = DepartureTemplateVersion::query()->with('template')->where('uuid', $data['version_uuid'])->firstOrFail();
        abort_if($version->template->status === 'archived', 422, 'Ce modèle est archivé.');
        $programme = new DepartureProgramme([
            'name' => $data['name'], 'version_id' => $version->id,
            'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
            'horizon_days' => $data['horizon_days'], 'automatic' => false,
            'exceptions' => $data['exceptions'], 'revision' => 1, 'created_by' => $actor->id,
            'version_schedule' => [['from' => $data['starts_on'], 'version_id' => $version->id]],
        ]);
        $programme->setRelation('version', $version);
        foreach ($data['exceptions'] as $exception) {
            if (! in_array($exception['slot_key'], array_column($version->slots, 'key'), true)) {
                throw ValidationException::withMessages(['exceptions' => 'Un créneau d’exception n’appartient pas à cette version.']);
            }
        }
        $this->recurrence->expand($programme, $programme->starts_on, $programme->starts_on->addDays(89));
        if ($save) {
            $programme->save();
            activity()->causedBy($actor)->performedOn($programme)->log('programming.programme.created');
        }

        return $programme;
    }

    /** @return array{token: string, rows: list<array<string, mixed>>, counts: array<string, int>} */
    public function preview(DepartureProgramme $programme, CarbonImmutable $from, CarbonImmutable $until): array
    {
        $existing = $programme->exists ? $programme->occurrences()->get()->keyBy(fn (PlannedDeparture $p): string => $p->slot_key.'/'.$p->service_date->toDateString()) : collect();
        $rows = [];
        $candidates = [];
        $counts = ['create' => 0, 'existing' => 0, 'excluded' => 0];
        foreach ($this->recurrence->expand($programme, $from, $until) as $attributes) {
            $key = $attributes['slot_key'].'/'.$attributes['service_date'];
            $item = $existing->get($key) ?? new PlannedDeparture($attributes);
            $kind = $existing->has($key) ? 'existing' : ($attributes['state'] === 'excluded' ? 'excluded' : 'create');
            $counts[$kind]++;
            $candidates[] = $item;
            $rows[] = ['key' => $key, 'date' => $attributes['service_date'], 'time' => $attributes['snapshot']['time'], 'direction' => $attributes['snapshot']['direction'], 'kind' => $kind, 'revision' => $item->revision ?? 0];
        }
        foreach ($this->publication->assessBatch($candidates) as $index => $assessment) {
            $rows[$index] += $assessment;
        }
        $token = hash('sha256', json_encode([$programme->revision, $programme->version_id, $from->toDateString(), $until->toDateString(), $rows], JSON_THROW_ON_ERROR));

        return ['token' => $token, 'rows' => $rows, 'counts' => $counts];
    }

    public function generate(DepartureProgramme $programme, CarbonImmutable $from, CarbonImmutable $until, User $actor, ?string $token = null): ProgrammingRun
    {
        return DB::transaction(function () use ($programme, $from, $until, $actor, $token): ProgrammingRun {
            $programme = DepartureProgramme::query()->lockForUpdate()->findOrFail($programme->id);
            if ($token !== null) {
                abort_unless(hash_equals($this->preview($programme, $from, $until)['token'], $token), 409, 'Le planning a changé. Actualisez la prévisualisation.');
            }
            $created = 0;
            foreach ($this->recurrence->expand($programme, $from, $until) as $attributes) {
                $item = PlannedDeparture::query()->where('programme_id', $programme->id)->where('slot_key', $attributes['slot_key'])->whereDate('service_date', $attributes['service_date'])->first();
                $item ??= PlannedDeparture::query()->create($attributes);
                if ($item->wasRecentlyCreated) {
                    $created++;
                }
            }
            $run = $programme->runs()->create(['operation' => 'generate', 'status' => 'completed', 'processed' => $created, 'result' => ['created' => $created], 'created_by' => $actor->id]);
            activity()->causedBy($actor)->performedOn($programme)->withProperties(['created' => $created])->log('programming.generated');

            return $run;
        });
    }

    public function protected(PlannedDeparture $item): bool
    {
        if ($item->scheduled_at->lte(now())) {
            return true;
        }
        if ($item->departure_id === null) {
            return false;
        }
        $departure = $item->departure;

        return $departure === null || $departure->getRawOriginal('status') !== 'scheduled' || $departure->reservations()->whereNotIn('status', ['expired', 'cancelled'])->exists()
            || $departure->reservations()->whereHas('tickets')->exists() || $departure->reservations()->whereHas('payments', fn ($query) => $query->whereIn('status', ['pending', 'confirmed', 'refunded']))->exists();
    }

    /**
     * @param  list<array{uuid: string, revision: int}>  $items
     * @param  array<string, mixed>  $changes
     * @return array{processed: int, errors: array<string, string>}
     */
    public function mutate(array $items, string $operation, array $changes, User $actor, ?CarbonImmutable $from = null, ?CarbonImmutable $until = null): array
    {
        $processed = 0;
        $errors = [];
        foreach ($items as $input) {
            try {
                DB::transaction(function () use ($input, $operation, $changes, $actor, $from, $until, &$processed): void {
                    if ($operation === 'publish') {
                        $item = PlannedDeparture::query()->where('uuid', $input['uuid'])->firstOrFail();
                        if ($from !== null && $until !== null) {
                            foreach ($this->publication->rotation($item) as $linked) {
                                if ($linked->service_date->lt($from) || $linked->service_date->gt($until)) {
                                    throw ValidationException::withMessages(['rotation' => 'La rotation dépasse l’horizon autorisé. Complétez la période avant publication.']);
                                }
                            }
                        }
                        $processed += count($this->publication->publish($item, $actor, (int) $input['revision']));

                        return;
                    }
                    $item = PlannedDeparture::query()->where('uuid', $input['uuid'])->lockForUpdate()->firstOrFail();
                    if ((int) $item->revision !== (int) $input['revision']) {
                        throw ValidationException::withMessages(['revision' => 'Le départ a changé. Rechargez sa fiche.']);
                    }
                    if ($item->departure_id !== null) {
                        Departure::query()->whereKey($item->departure_id)->lockForUpdate()->firstOrFail();
                        $item->unsetRelation('departure');
                    }
                    if ($item->state === 'excluded' || $this->protected($item)) {
                        throw ValidationException::withMessages(['departure' => 'Départ protégé : réservation, paiement ou exploitation en cours.']);
                    }
                    if ($operation === 'exclude') {
                        if ($item->departure !== null) {
                            $item->departure->update(['status' => 'cancelled']);
                        }
                        $item->update(['state' => 'excluded', 'is_exception' => true, 'revision' => $item->revision + 1]);
                    } else {
                        $snapshot = array_replace($item->snapshot, $changes);
                        $this->templates->validateCrew($snapshot);
                        $item->fill($this->recurrence->attributes($snapshot, $item->service_date->toDateString()));
                        $this->publication->lockResources([$item]);
                        $conflicts = $this->publication->conflicts($item);
                        if ($conflicts !== []) {
                            throw ValidationException::withMessages(['resources' => $conflicts]);
                        }
                        $this->syncPublished($item);
                        $item->fill(['is_exception' => true, 'revision' => $item->revision + 1])->save();
                    }
                    activity()->causedBy($actor)->performedOn($item)->withProperties(['operation' => $operation])->log('programming.exception');
                    $processed++;
                });
            } catch (ValidationException $error) {
                $errors[$input['uuid']] = implode(' ', array_merge(...array_values($error->errors())));
            }
        }

        return ['processed' => $processed, 'errors' => $errors];
    }

    public function syncPublished(PlannedDeparture $item): void
    {
        if ($item->departure_id === null) {
            return;
        }
        if ($item->bus_id === null) {
            throw ValidationException::withMessages(['bus_uuid' => 'Un départ publié doit conserver un bus.']);
        }
        $this->publication->lockResources([$item]);
        $assessment = $this->publication->assess($item, true);
        if ($assessment['state'] !== 'ready') {
            throw ValidationException::withMessages(['publication' => $assessment['blockers'] ?: ['Les conditions de publication ne sont plus réunies.']]);
        }
        $time = $item->scheduled_at->setTimezone(config('app.timezone'));
        $item->departure->update([
            'line_id' => $item->line_id, 'agency_id' => $item->agency_id, 'bus_id' => $item->bus_id,
            'driver_id' => $item->driver_id, 'secondary_driver_id' => $item->secondary_driver_id,
            'conductor_id' => $item->conductor_id, 'mechanic_id' => $item->mechanic_id,
            'scheduled_at' => $item->scheduled_at, 'departure_timezone' => $item->timezone,
            'departure_date' => $time->toDateString(), 'departure_time' => $time->format('H:i:s'),
            'estimated_arrival_at' => $item->arrival_at->setTimezone(config('app.timezone')), 'stop_times' => $item->snapshot['stops'],
        ]);
        PriceQuote::query()->where('departure_id', $item->departure_id)->update(['expires_at' => now()]);
    }

    /** @return array<string, mixed> */
    public function revise(DepartureProgramme $programme, DepartureTemplateVersion $version, CarbonImmutable $from, bool $apply, ?string $token, User $actor): array
    {
        return DB::transaction(function () use ($programme, $version, $from, $apply, $token, $actor): array {
            $programme = DepartureProgramme::query()->lockForUpdate()->findOrFail($programme->id);
            abort_unless($version->template_id === $programme->version->template_id, 422, 'Choisissez une version du même modèle.');
            abort_if($from->isBefore(today()) || $from->gt($programme->ends_on), 422, 'Choisissez une date future dans la période.');
            $items = $programme->occurrences()->whereDate('service_date', '>=', $from)->orderBy('id')->lockForUpdate()->get();
            $targets = [];
            $until = $items->max('service_date') ?? $from;
            for ($chunk = $from; $chunk->lte($until); $chunk = $chunk->addDays(90)) {
                foreach ($this->recurrence->expand($programme, $chunk, $chunk->addDays(89)->min($until), $version) as $target) {
                    $targets[$target['slot_key'].'/'.$target['service_date']] = $target;
                }
            }
            $rows = [];
            $synchronize = [];
            foreach ($items as $item) {
                if ($item->departure_id !== null) {
                    Departure::query()->whereKey($item->departure_id)->lockForUpdate()->first();
                }
                $key = $item->slot_key.'/'.$item->service_date->toDateString();
                $target = $targets[$key] ?? null;
                $kind = $this->protected($item) ? 'protected' : ($item->is_exception ? 'exception' : ($target === null ? 'remove' : 'update'));
                $rows[] = ['uuid' => $item->uuid, 'date' => $item->service_date->toDateString(), 'before' => $item->snapshot['time'], 'after' => $target['snapshot']['time'] ?? null, 'kind' => $kind, 'revision' => $item->revision];
                if ($apply && in_array($kind, ['remove', 'update'], true)) {
                    if ($target === null) {
                        abort_if($item->departure_id !== null && ! $actor->can('departures.cancel'), 403);
                        $item->departure?->update(['status' => 'cancelled']);
                        $item->fill(['state' => 'excluded']);
                    } else {
                        $item->fill($target);
                        $synchronize[] = $item;
                    }
                    $item->fill(['revision' => $item->revision + 1])->save();
                }
                unset($targets[$key]);
            }
            foreach ($targets as $target) {
                $rows[] = ['uuid' => null, 'date' => $target['service_date'], 'before' => null, 'after' => $target['snapshot']['time'], 'kind' => 'add', 'revision' => 0];
            }
            $digest = hash('sha256', json_encode([$programme->revision, $version->uuid, $from->toDateString(), $rows], JSON_THROW_ON_ERROR));
            if ($apply) {
                abort_unless($token !== null && hash_equals($digest, $token), 409, 'Le planning a changé. Actualisez les impacts.');
                foreach ($targets as $target) {
                    PlannedDeparture::query()->create($target);
                }
                $this->publication->lockResources($synchronize);
                foreach ($synchronize as $item) {
                    $this->syncPublished($item);
                }
                $schedule = array_values(array_filter($programme->version_schedule, fn (array $entry): bool => $entry['from'] < $from->toDateString()));
                $schedule[] = ['from' => $from->toDateString(), 'version_id' => $version->id];
                $programme->update(['version_id' => $version->id, 'version_schedule' => $schedule, 'revision' => $programme->revision + 1, 'automatic' => false]);
                activity()->causedBy($actor)->performedOn($programme)->log('programming.revision.applied');
            }

            return ['rows' => $rows, 'token' => $digest, 'applied' => $apply];
        });
    }
}
