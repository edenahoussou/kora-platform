<?php

namespace App\Modules\Notifications\Observers;

use App\Models\User;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Services\OpsNotifier;
use App\Modules\Notifications\Services\PassengerNotificationPublisher;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Publie « départ affecté » et « départ modifié » quel que soit le chemin
 * qui écrit le départ (création, modification, programmation, annulation).
 *
 * Les différences sont calculées tout de suite (les valeurs d'origine ne
 * survivent pas à la sauvegarde) ; seule la publication attend la fin de la
 * transaction en cours.
 */
class DepartureOpsNotificationObserver
{
    private const array CREW_COLUMNS = ['driver_id', 'secondary_driver_id', 'conductor_id', 'mechanic_id'];

    public function __construct(private OpsNotifier $notifier, private PassengerNotificationPublisher $passengerNotifications) {}

    public function created(Departure $departure): void
    {
        $assigned = $this->crewIds($departure);

        if ($assigned === []) {
            return;
        }

        $this->afterCommit(function () use ($departure, $assigned): void {
            foreach (User::query()->whereIn('id', $assigned)->get() as $user) {
                $this->notifier->departureAssigned($departure, $user);
            }
        });
    }

    public function updated(Departure $departure): void
    {
        $assigned = [];
        $removed = [];

        foreach (self::CREW_COLUMNS as $column) {
            if (! $departure->wasChanged($column)) {
                continue;
            }

            $previous = $departure->getOriginal($column);
            $current = $departure->getAttribute($column);

            if (is_int($current)) {
                $assigned[] = $current;
            }

            if (is_int($previous)) {
                $removed[] = $previous;
            }
        }

        // Un membre déplacé d'un rôle à l'autre reste dans l'équipage : il n'est pas « retiré ».
        $removed = array_values(array_diff(array_unique($removed), $this->crewIds($departure)));
        $assigned = array_values(array_unique($assigned));

        $changes = [];

        foreach (['departure_date' => 'date', 'departure_time' => 'time', 'bus_id' => 'bus', 'line_id' => 'line'] as $column => $code) {
            if ($departure->wasChanged($column)) {
                $changes[] = $code;
            }
        }

        if ($departure->wasChanged('status') && $departure->status === DepartureStatus::Cancelled) {
            $changes[] = 'cancelled';
        }

        if ($removed !== []) {
            $changes[] = 'crew_removed';
        }

        // Le bus vient de partir (convoyeur ou OPS Web) : information dédiée.
        $departed = $departure->wasChanged('status') && $departure->status === DepartureStatus::Departed;

        if ($assigned === [] && $changes === [] && ! $departed) {
            return;
        }

        $this->afterCommit(function () use ($departure, $assigned, $changes, $removed, $departed): void {
            if ($departed) {
                $this->notifier->departureDeparted($departure);
            }

            foreach (User::query()->whereIn('id', $assigned)->get() as $user) {
                $this->notifier->departureAssigned($departure, $user);
            }

            if ($changes !== []) {
                /** @var Collection<int, User> $removedCrew */
                $removedCrew = $removed === [] ? new Collection : User::query()->whereIn('id', $removed)->get();

                $this->notifier->departureModified($departure, $changes, $removedCrew);
                if (in_array('cancelled', $changes, true)) {
                    $this->passengerNotifications->departureChanged($departure, 'cancelled');
                } elseif (array_intersect($changes, ['date', 'time', 'bus', 'line']) !== []) {
                    $this->passengerNotifications->departureChanged($departure, 'modified');
                }
                if (array_intersect($changes, ['date', 'time']) !== []) {
                    $this->passengerNotifications->scheduleDepartureReminders($departure);
                }
            }
        });
    }

    /**
     * @return list<int>
     */
    private function crewIds(Departure $departure): array
    {
        $ids = [];

        foreach (self::CREW_COLUMNS as $column) {
            $value = $departure->getAttribute($column);

            if (is_int($value)) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    private function afterCommit(callable $callback): void
    {
        DB::afterCommit(function () use ($callback): void {
            try {
                $callback();
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
