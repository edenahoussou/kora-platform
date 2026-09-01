<?php

namespace App\Modules\Departures\Services;

use App\Models\User;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\DepartureTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TemplateService
{
    /** @param array<string, mixed> $data */
    public function save(array $data, User $actor, ?DepartureTemplate $template = null): DepartureTemplate
    {
        return DB::transaction(function () use ($data, $actor, $template): DepartureTemplate {
            if ($template !== null) {
                $template = DepartureTemplate::query()->lockForUpdate()->findOrFail($template->id);
                abort_unless((int) $template->revision === (int) $data['revision'], 409, 'Le modèle a changé. Rechargez-le avant de sauvegarder.');
                abort_if($template->status === 'archived', 422, 'Ce modèle est archivé.');
            } else {
                $template = DepartureTemplate::query()->create(['name' => $data['name'], 'revision' => 0, 'created_by' => $actor->id]);
            }
            if ($data['ready']) {
                $this->validateSlots($data['slots']);
                $number = (int) $template->versions()->max('number') + 1;
                $template->versions()->create(['number' => $number, 'slots' => $data['slots']]);
            }
            $template->update([
                'name' => $data['name'], 'revision' => $template->revision + 1,
                'draft' => $data['ready'] ? null : ['slots' => $data['slots']],
                'status' => $data['ready'] || $template->versions()->exists() ? 'available' : 'draft',
            ]);
            activity()->causedBy($actor)->performedOn($template)->log($data['ready'] ? 'programming.template.versioned' : 'programming.template.draft');

            return $template;
        });
    }

    /** @param list<array<string, mixed>> $slots */
    public function validateSlots(array $slots): void
    {
        $errors = [];
        if ($slots === []) {
            $errors['slots'] = 'Ajoutez au moins un horaire.';
        }
        $seen = [];
        $lines = [];
        $returnTargets = [];
        foreach ($slots as $index => $slot) {
            $path = 'slots.'.$index;
            $line = Line::query()->with('stops')->where('uuid', $slot['line_uuid'] ?? null)->where('status', 'active')->first();
            if ($line === null || empty($slot['days'])) {
                $errors[$path.'.line_uuid'] = 'Choisissez une ligne active et des jours de circulation.';

                continue;
            }
            $lines[$slot['direction']][$line->uuid] = true;
            $stops = $slot['stops'];
            $expected = $line->stops->where('status.value', 'active')->values();
            if (count($stops) < 2 || array_column($stops, 'stop_uuid') !== $expected->pluck('uuid')->all()) {
                $errors[$path.'.stops'] = 'Renseignez tous les arrêts actifs dans leur ordre de circulation.';
            }
            $previous = 0;
            foreach ($stops as $n => $stop) {
                $arrival = $stop['arrival_minutes'] ?? null;
                $departure = $stop['departure_minutes'] ?? null;
                if (($n > 0 && ($arrival === null || $arrival < $previous)) || ($n < count($stops) - 1 && ($departure === null || $departure < ($arrival ?? 0))) || ($n === 0 && $departure !== 0)) {
                    $errors[$path.'.stops.'.$n] = 'Les heures doivent suivre le parcours ; précisez le changement de jour si nécessaire.';
                }
                $previous = (int) ($departure ?? $arrival ?? 0);
            }
            if ($previous !== (int) $slot['duration_minutes']) {
                $errors[$path.'.duration_minutes'] = 'La durée doit correspondre à l’arrivée au dernier arrêt.';
            }
            foreach ($slot['days'] as $day) {
                $key = $slot['direction'].'/'.$day.'/'.$slot['time'];
                if (isset($seen[$key])) {
                    $errors[$path.'.time'] = 'Cet horaire existe déjà pour ce sens et ce jour.';
                }
                $seen[$key] = true;
            }
            if (! empty($slot['return_key'])) {
                $target = collect($slots)->firstWhere('key', $slot['return_key']);
                if ($target === null || $slot['direction'] !== 'outbound' || $target['direction'] !== 'return' || ! empty($target['return_key']) || isset($returnTargets[$slot['return_key']])) {
                    $errors[$path.'.return_key'] = 'Associez un créneau retour distinct à un seul aller.';
                }
                $returnTargets[$slot['return_key']] = true;
            }
            $this->validateCrew($slot);
        }
        foreach ($lines as $directionLines) {
            if (count($directionLines) > 1) {
                $errors['slots'] = 'Un modèle regroupe une seule ligne par sens.';
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param array<string, mixed> $slot
     * @param  array<string, User>|null  $people
     */
    public function validateCrew(array $slot, ?array $people = null): void
    {
        $seen = [];
        foreach (['driver_uuid' => 'bus_driver', 'secondary_driver_uuid' => 'bus_driver', 'conductor_uuid' => 'conductor', 'mechanic_uuid' => 'mechanic'] as $field => $role) {
            if (empty($slot[$field])) {
                continue;
            }
            $person = $people[$slot[$field]] ?? null;
            $eligible = $people === null ? User::query()->role($role)->where('uuid', $slot[$field])->where('status', 'active')->exists() : ($person !== null && $person->getRawOriginal('status') === 'active' && $person->hasRole($role));
            if (in_array($slot[$field], $seen, true) || ! $eligible) {
                throw ValidationException::withMessages([$field => 'Choisissez une personne active et habilitée, distincte pour chaque poste.']);
            }
            $seen[] = $slot[$field];
        }
    }
}
