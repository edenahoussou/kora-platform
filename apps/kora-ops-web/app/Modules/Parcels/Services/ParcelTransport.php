<?php

namespace App\Modules\Parcels\Services;

use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Parcels\Models\Parcel;
use App\Modules\Parcels\Models\ParcelAssignment;
use App\Modules\Parcels\Models\ParcelEvent;
use App\Modules\Parcels\Models\ParcelHub;
use App\Modules\Parcels\Models\ParcelSouteBudget;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class ParcelTransport
{
    public function __construct(
        private ParcelAccess $access,
        private CrewDepartureScope $crew,
        private ParcelNotifications $notifications,
        private ParcelRouting $routing,
    ) {}

    public function ready(Parcel $parcel, User $actor): Parcel
    {
        return DB::transaction(function () use ($parcel, $actor): Parcel {
            $parcel = $this->lock($parcel);
            $hub = $parcel->corridor->originHub;
            $this->access->atHub($actor, $hub, 'parcels.intake');
            abort_unless($parcel->status === 'registered', 409);
            abort_unless($this->settledCharge($parcel, false), 409, 'The deposit charge must be settled before the parcel is ready.');
            $parcel->update(['status' => 'ready']);
            $parcel->record('ready', $actor, $hub);
            $this->notifications->milestone($parcel, 'ready');

            return $parcel;
        });
    }

    public function capacity(Departure $departure, int $grams): ParcelSouteBudget
    {
        return DB::transaction(function () use ($departure, $grams): ParcelSouteBudget {
            $departure = Departure::query()->lockForUpdate()->findOrFail($departure->id);
            abort_unless(in_array($departure->status, [DepartureStatus::Scheduled, DepartureStatus::Boarding], true), 409);
            abort_if($this->reservedGrams($departure) > $grams, 409, 'Capacity is lower than the reserved weight.');

            return ParcelSouteBudget::query()->updateOrCreate(['departure_id' => $departure->id], [
                'bus_id' => $departure->bus_id, 'capacity_grams' => $grams,
            ]);
        });
    }

    /**
     * Build the server-derived assignment options for a paid parcel.
     *
     * @return array<string, mixed>
     */
    public function assignmentOptions(Parcel $parcel, User $actor): array
    {
        $this->access->require($actor, 'parcels.assign');

        $parcel = Parcel::query()->with(['corridor.originHub.stop', 'corridor.destinationHub.stop', 'charges.settledPayment', 'assignments'])->findOrFail($parcel->id);
        $this->ensureUnblocked($parcel);
        abort_unless(in_array($parcel->status, ['ready', 'assigned', 'return_pending'], true), 409, 'The parcel must be ready before a departure can be selected.');
        $return = $parcel->status === 'return_pending';
        abort_if(! $return && ! $this->settledCharge($parcel, false), 409, 'The parcel charge must be settled before assignment.');
        [$origin, $destination] = $this->hubs($parcel, $return);
        abort_if($origin->stop === null || $destination->stop === null, 409, 'The parcel route is incomplete.');

        $current = $parcel->assignments->first(fn (ParcelAssignment $assignment): bool => $assignment->released_at === null && $assignment->unloaded_at === null);
        $today = CarbonImmutable::now()->startOfDay();
        $departures = Departure::query()
            ->where('line_id', $origin->stop->line_id)
            ->whereIn('status', [DepartureStatus::Scheduled, DepartureStatus::Boarding])
            ->whereDate('departure_date', '>=', $today->toDateString())
            ->whereDate('departure_date', '<=', $today->addDays($this->routing->horizonDays())->toDateString())
            ->with(['line.stops', 'bus'])
            ->get();

        $budgets = ParcelSouteBudget::query()->whereIn('departure_id', $departures->pluck('id'))->get()->keyBy('departure_id');
        $reserved = ParcelAssignment::query()
            ->whereIn('departure_id', $departures->pluck('id'))
            ->whereNull('released_at')
            ->whereNull('unloaded_at')
            ->where('parcel_id', '!=', $parcel->id)
            ->join('parcels', 'parcels.id', '=', 'parcel_assignments.parcel_id')
            ->selectRaw('parcel_assignments.departure_id as departure_id, COALESCE(SUM(parcels.weight_grams), 0) as grams')
            ->groupBy('parcel_assignments.departure_id')
            ->pluck('grams', 'departure_id');

        $rows = $departures->map(function (Departure $departure) use ($origin, $destination, $budgets, $reserved, $parcel, $current): ?array {
            $stops = $departure->tripStops()->pluck('id');
            $from = $stops->search($origin->stop_id, true);
            $to = $stops->search($destination->stop_id, true);
            if ($from === false || $to === false || $from >= $to) {
                return null;
            }
            $budget = $budgets->get($departure->id);
            $reservedGrams = (int) ($reserved[$departure->id] ?? 0);
            $capacity = $budget?->capacity_grams;
            $remaining = $capacity === null ? null : max(0, $capacity - $reservedGrams);
            $eligible = $remaining !== null && $remaining >= $parcel->weight_grams;
            $reason = $capacity === null ? 'capacity_not_configured' : ($eligible ? null : 'insufficient_capacity');

            return [
                'departure_uuid' => $departure->uuid,
                'line_code' => $departure->line?->code,
                'line_name' => $departure->line?->name,
                'bus_registration' => $departure->bus?->registration_number,
                'departure_date' => $departure->departure_date instanceof CarbonInterface
                    ? $departure->departure_date->toDateString()
                    : (string) $departure->departure_date,
                'departure_time' => substr((string) $departure->departure_time, 0, 5),
                'status' => $departure->status->value,
                'origin_stop' => $origin->stop->name,
                'destination_stop' => $destination->stop->name,
                'capacity_grams' => $capacity,
                'reserved_grams' => $reservedGrams,
                'remaining_grams' => $remaining,
                'required_grams' => $parcel->weight_grams,
                'eligible' => $eligible,
                'reason' => $reason,
                'is_current' => $current?->departure_id === $departure->id,
            ];
        })->filter()->sortBy(fn (array $row): string => $row['departure_date'].' '.$row['departure_time'])->values();

        return [
            'parcel_uuid' => $parcel->uuid,
            'status' => $parcel->status,
            'return_leg' => $return,
            'required_grams' => $parcel->weight_grams,
            'current_departure_uuid' => $current?->departure?->uuid,
            'departures' => $rows->all(),
        ];
    }

    public function assign(Parcel $parcel, Departure $departure, User $actor): Parcel
    {
        $this->access->require($actor, 'parcels.assign');

        return DB::transaction(function () use ($parcel, $departure, $actor): Parcel {
            $departure = Departure::query()->lockForUpdate()->findOrFail($departure->id);
            $parcel = $this->lock($parcel);
            abort_unless(in_array($parcel->status, ['ready', 'assigned', 'return_pending'], true), 409);
            abort_unless(in_array($departure->status, [DepartureStatus::Scheduled, DepartureStatus::Boarding], true), 409);
            $return = $parcel->status === 'return_pending';
            [$origin, $destination] = $this->hubs($parcel, $return);
            abort_if(! $return && ! $this->settledCharge($parcel, false), 409, 'The parcel charge must be settled before assignment.');
            $stops = $departure->tripStops()->pluck('id');
            $from = $stops->search($origin->stop_id, true);
            $to = $stops->search($destination->stop_id, true);
            abort_unless($from !== false && $to !== false && $from < $to, 409, 'The hubs must be served in the correct order.');

            $old = $parcel->assignments()->whereNull('released_at')->latest('id')->first();
            abort_if($old?->loaded_at !== null, 409, 'Unload the parcel before reassigning it.');
            if ($old !== null && $old->departure_id === $departure->id && $old->bus_id === $departure->bus_id) {
                return $parcel;
            }
            $budget = ParcelSouteBudget::query()->where('departure_id', $departure->id)->where('bus_id', $departure->bus_id)->first();
            abort_unless($budget !== null && $this->reservedGrams($departure, $parcel->id) + $parcel->weight_grams <= $budget->capacity_grams, 409, 'Insufficient parcel hold capacity or capacity has not been approved.');
            if ($old !== null) {
                $old->update(['released_at' => now()]);
                $parcel->record('assignment_released', $actor, $origin, $old);
            }
            $assignment = $parcel->assignments()->create([
                'departure_id' => $departure->id, 'bus_id' => $departure->bus_id, 'return_leg' => $return,
            ]);
            $parcel->update(['status' => 'assigned']);
            $parcel->record('assigned', $actor, $origin, $assignment, details: $old === null ? [] : [
                'previous_departure_uuid' => $old->departure?->uuid,
            ]);

            return $parcel;
        }, attempts: 3);
    }

    public function scan(
        Departure $departure,
        User $actor,
        TerminalP501 $terminal,
        string $reference,
        string $stopUuid,
        bool $loading,
        CarbonInterface $observedAt,
        string $operationUuid,
    ): Parcel {
        return DB::transaction(function () use ($departure, $actor, $terminal, $reference, $stopUuid, $loading, $observedAt, $operationUuid): Parcel {
            $departure = Departure::query()->lockForUpdate()->findOrFail($departure->id);
            abort_unless(TerminalP501::query()->whereKey($terminal->id)->where('status', TerminalP501Status::Active)->exists(), 403);
            abort_unless($this->crew->query($actor, 'parcels.scan')->whereKey($departure->id)->exists(), 403);
            $parcel = Parcel::query()->where('reference', $reference)->lockForUpdate()->firstOrFail();
            $kind = $loading ? 'loaded' : 'unloaded';
            $recorded = ParcelEvent::query()->where('operation_uuid', $operationUuid)->with(['assignment'])->first();
            if ($recorded !== null) {
                abort_unless($recorded->parcel_id === $parcel->id && $recorded->kind === $kind
                    && $recorded->actor_id === $actor->id && $recorded->terminal_id === $terminal->id
                    && $recorded->assignment?->departure_id === $departure->id
                    && $recorded->observed_at->eq($observedAt)
                    && ParcelHub::query()->whereKey($recorded->hub_id)->whereRelation('stop', 'uuid', $stopUuid)->exists(), 409);

                return $parcel;
            }
            $assignment = $parcel->assignments()->where('departure_id', $departure->id)->whereNull('released_at')->latest('id')->first();
            abort_unless($assignment !== null && $assignment->bus_id === $departure->bus_id, 409, 'Affectation de soute invalide.');
            [$origin, $destination] = $this->hubs($parcel, $assignment->return_leg);
            $hub = $loading ? $origin : $destination;
            abort_unless($hub->stop?->uuid === $stopUuid, 409, 'Incorrect scan stop.');

            $this->ensureUnblocked($parcel);
            abort_if($observedAt->isFuture() || $observedAt->lt($assignment->created_at->copy()->startOfSecond()), 409, 'Scan timestamp is outside the assignment window.');
            if ($loading) {
                abort_unless(in_array($parcel->status, ['ready', 'assigned', 'return_pending'], true)
                    && $assignment->loaded_at === null
                    && ($departure->departed_at === null || $observedAt->lte($departure->departed_at)), 409, 'Chargement incompatible avec le départ.');
                $assignment->update(['loaded_at' => $observedAt]);
                $parcel->update(['custody_hub_id' => null, 'status' => 'loaded']);
            } else {
                abort_unless($assignment->loaded_at !== null && $assignment->unloaded_at === null
                    && $departure->departed_at !== null
                    && $observedAt->gte($departure->departed_at) && $observedAt->gte($assignment->loaded_at), 409, 'Déchargement incompatible avec le chargement.');
                $assignment->update(['unloaded_at' => $observedAt]);
                $parcel->update(['custody_hub_id' => $destination->id, 'status' => $assignment->return_leg ? 'return_arrived' : 'arrived']);
                if (! $assignment->return_leg) {
                    $this->notifications->milestone($parcel, 'arrived');
                }
            }
            $parcel->record($kind, $actor, $hub, $assignment, $terminal, $observedAt, ['operation_uuid' => $operationUuid]);
            if ($loading && $departure->departed_at !== null) {
                $this->markTransit($parcel, $assignment, $departure->departed_at);
            }

            return $parcel;
        });
    }

    public function departureChanged(Departure $departure): void
    {
        if ($departure->status !== DepartureStatus::Cancelled && $departure->departed_at === null) {
            return;
        }

        ParcelAssignment::query()->where('departure_id', $departure->id)->whereNull('released_at')
            ->whereNull('unloaded_at')->with('parcel')->eachById(function (ParcelAssignment $assignment) use ($departure): void {
                $parcel = Parcel::query()->lockForUpdate()->findOrFail($assignment->parcel_id);
                if ($departure->status === DepartureStatus::Cancelled) {
                    if ($assignment->loaded_at === null) {
                        $assignment->update(['released_at' => now()]);
                        $parcel->update(['status' => $assignment->return_leg ? 'return_pending' : 'ready']);
                        $parcel->record('departure_cancelled', assignment: $assignment, channel: 'system');
                    } else {
                        $parcel->record('loaded_departure_cancelled', assignment: $assignment, channel: 'system');
                    }
                } elseif ($assignment->loaded_at !== null && $departure->departed_at !== null) {
                    $this->markTransit($parcel, $assignment, $departure->departed_at);
                }
            });
    }

    public function releaseChangedDeparture(Departure $departure): void
    {
        ParcelAssignment::query()->where('departure_id', $departure->id)->whereNull('released_at')->whereNull('loaded_at')
            ->eachById(function (ParcelAssignment $assignment): void {
                $parcel = Parcel::query()->lockForUpdate()->findOrFail($assignment->parcel_id);
                $assignment->update(['released_at' => now()]);
                $parcel->update(['status' => $assignment->return_leg ? 'return_pending' : 'ready']);
                $parcel->record('departure_changed', assignment: $assignment, channel: 'system');
            });
    }

    public function unloadAtOrigin(Parcel $parcel, User $actor, string $reason): Parcel
    {
        $this->access->require($actor, 'parcels.override');

        return DB::transaction(function () use ($parcel, $actor, $reason): Parcel {
            $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
            $assignment = $parcel->assignments()->whereNull('released_at')->whereNotNull('loaded_at')->whereNull('unloaded_at')->latest('id')->firstOrFail();
            [$origin] = $this->hubs($parcel, $assignment->return_leg);
            $this->access->atHub($actor, $origin, 'parcels.override');
            abort_unless($assignment->departure?->departed_at === null, 409);
            $assignment->update(['unloaded_at' => now(), 'released_at' => now()]);
            $parcel->update([
                'custody_hub_id' => $origin->id,
                'status' => $assignment->return_leg ? 'return_pending' : 'ready',
            ]);
            $parcel->record('unloaded_at_origin', $actor, $origin, $assignment, details: ['reason' => $reason]);

            return $parcel;
        });
    }

    public function lock(Parcel $parcel): Parcel
    {
        $parcel = Parcel::query()->lockForUpdate()->findOrFail($parcel->id);
        $this->ensureUnblocked($parcel);

        return $parcel;
    }

    private function ensureUnblocked(Parcel $parcel): void
    {
        abort_if($parcel->incidents()->where('blocking', true)->whereNull('resolved_at')->exists(), 409, 'The parcel is blocked by an unresolved incident.');
    }

    /** @return array{ParcelHub, ParcelHub} */
    private function hubs(Parcel $parcel, bool $return): array
    {
        $corridor = $parcel->corridor;

        return $return ? [$corridor->destinationHub, $corridor->originHub] : [$corridor->originHub, $corridor->destinationHub];
    }

    private function reservedGrams(Departure $departure, ?int $except = null): int
    {
        return (int) ParcelAssignment::query()->where('departure_id', $departure->id)->whereNull('released_at')
            ->whereNull('unloaded_at')->when($except !== null, fn ($query) => $query->where('parcel_id', '!=', $except))
            ->join('parcels', 'parcels.id', '=', 'parcel_assignments.parcel_id')->sum('parcels.weight_grams');
    }

    private function settledCharge(Parcel $parcel, bool $return): bool
    {
        return $parcel->charges()
            ->where('return_leg', $return)
            ->whereNull('superseded_at')
            ->whereNotNull('settled_payment_id')
            ->whereHas('settledPayment', fn ($query) => $query->where('status', 'confirmed'))
            ->exists();
    }

    private function markTransit(Parcel $parcel, ParcelAssignment $assignment, CarbonInterface $departedAt): void
    {
        $status = $assignment->return_leg ? 'return_transit' : 'in_transit';
        if ($parcel->status === $status) {
            return;
        }
        $parcel->update(['status' => $status]);
        $assignment->update(['transported_at' => $departedAt]);
        $parcel->record('in_transit', assignment: $assignment, observedAt: $departedAt, channel: 'system');
        if (! $assignment->return_leg) {
            $this->notifications->milestone($parcel, 'in_transit');
        }
    }
}
