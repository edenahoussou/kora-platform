<?php

namespace App\Modules\Departures\Services;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\DepartureProgramme;
use App\Modules\Departures\Models\DepartureTemplateVersion;
use App\Modules\IdentityAccess\Models\Agency;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class RecurrenceService
{
    /** @return list<array<string, mixed>> */
    public function expand(DepartureProgramme $programme, CarbonImmutable $from, CarbonImmutable $until, ?DepartureTemplateVersion $version = null): array
    {
        $forcedVersion = $version;
        $version ??= $programme->version;
        $from = $from->max($programme->starts_on)->startOfDay();
        $until = $until->min($programme->ends_on)->startOfDay();
        abort_if($from->diffInDays($until, true) > 90, 422, 'Préparez au maximum 90 jours par opération.');
        $result = [];
        $references = [];
        $versions = [$version->id => $version];
        $exceptions = [];
        foreach ($programme->exceptions as $exception) {
            $key = $exception['slot_key'].'/'.$exception['date'];
            if (isset($exceptions[$key])) {
                throw ValidationException::withMessages(['exceptions' => 'Une seule exception par date et créneau ; le créneau doit appartenir au modèle.']);
            }
            $exceptions[$key] = $exception;
        }
        for ($date = $from; $date->lte($until); $date = $date->addDay()) {
            if ($forcedVersion === null) {
                foreach ($programme->version_schedule as $entry) {
                    if ($entry['from'] <= $date->toDateString()) {
                        $version = $versions[$entry['version_id']] ??= DepartureTemplateVersion::query()->findOrFail($entry['version_id']);
                    }
                }
            }
            $weeks = (int) ($programme->starts_on->startOfWeek()->diffInDays($date->startOfWeek()) / 7);
            foreach ($version->slots as $slot) {
                $key = $slot['key'].'/'.$date->toDateString();
                $exception = $exceptions[$key] ?? null;
                $matches = in_array($date->isoWeekday(), $slot['days'], true) && $weeks % (int) $slot['interval_weeks'] === 0;
                if (! $matches && ($exception['action'] ?? null) !== 'add') {
                    continue;
                }
                $snapshot = $slot;
                if ($exception !== null) {
                    foreach (['time', 'bus_uuid', 'driver_uuid', 'secondary_driver_uuid', 'conductor_uuid', 'mechanic_uuid'] as $field) {
                        if (array_key_exists($field, $exception) && ($field !== 'time' || $exception[$field] !== null)) {
                            $snapshot[$field] = $exception[$field];
                        }
                    }
                }
                $referenceKey = json_encode($snapshot, JSON_THROW_ON_ERROR);
                $references[$referenceKey] ??= $this->attributes($snapshot, $date->toDateString());
                $result[] = array_replace($references[$referenceKey], $this->timing($snapshot, $date->toDateString())) + [
                    'programme_id' => $programme->id,
                    'version_id' => $version->id,
                    'slot_key' => $slot['key'],
                    'service_date' => $date->toDateString(),
                    'state' => ($exception['action'] ?? null) === 'exclude' ? 'excluded' : 'planned',
                    'is_exception' => $exception !== null,
                ];
            }
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $slot
     * @return array<string, mixed>
     */
    public function attributes(array $slot, string $date): array
    {
        $attributes = $this->timing($slot, $date) + [
            'timezone' => $slot['timezone'], 'snapshot' => $slot,
            'line_id' => Line::withTrashed()->where('uuid', $slot['line_uuid'])->firstOrFail()->id,
            'agency_id' => Agency::withTrashed()->where('uuid', $slot['agency_uuid'])->firstOrFail()->id,
            'bus_id' => empty($slot['bus_uuid']) ? null : Bus::withTrashed()->where('uuid', $slot['bus_uuid'])->firstOrFail()->id,
        ];
        foreach (['driver', 'secondary_driver', 'conductor', 'mechanic'] as $role) {
            $attributes[$role.'_id'] = empty($slot[$role.'_uuid']) ? null : User::query()->where('uuid', $slot[$role.'_uuid'])->first()?->id;
        }

        return $attributes;
    }

    /** @param array<string, mixed> $slot
     * @return array{scheduled_at: CarbonImmutable, arrival_at: CarbonImmutable}
     */
    private function timing(array $slot, string $date): array
    {
        $scheduled = CarbonImmutable::parse($date.' '.$slot['time'], $slot['timezone']);
        if ($scheduled->format('Y-m-d H:i') !== $date.' '.$slot['time']) {
            throw ValidationException::withMessages(['time' => 'Cette heure locale n’existe pas dans le fuseau choisi.']);
        }

        return ['scheduled_at' => $scheduled->utc(), 'arrival_at' => $scheduled->utc()->addMinutes((int) $slot['duration_minutes'])];
    }
}
