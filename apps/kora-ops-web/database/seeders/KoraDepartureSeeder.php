<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class KoraDepartureSeeder extends Seeder
{
    public function run(): void
    {
        $line = Line::query()->where('code', 'L1')->first();
        if ($line === null) {
            return;
        }

        $line2 = Line::query()->firstOrCreate(
            ['code' => 'L2'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Cotonou - Lomé',
                'type' => 'internationale',
                'bidirectional' => true,
                'countries_crossed' => ['BJ', 'TG'],
                'distance_km' => 170,
                'estimated_duration_minutes' => 210,
                'status' => 'active',
            ],
        );

        foreach (
            [
                ['code' => 'BJ-CTN-CENTRAL', 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0],
                ['code' => 'BJ-PNR-CENTRAL', 'name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 35],
                ['code' => 'TG-LFW-CENTRAL', 'name' => 'Lome', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 3, 'km_from_origin' => 170],
            ] as $stop
        ) {
            $physical = PhysicalStop::query()->firstOrCreate(
                ['code' => $stop['code']],
                ['uuid' => (string) Str::uuid(), 'name' => $stop['name'], 'city' => $stop['city'], 'country' => $stop['country'], 'status' => 'active'],
            );
            Stop::query()->firstOrCreate(
                ['line_id' => $line2->id, 'stop_order' => $stop['stop_order']],
                Arr::except($stop, ['code']) + ['uuid' => (string) Str::uuid(), 'physical_stop_id' => $physical->id, 'status' => 'active'],
            );
        }

        $bus1 = Bus::query()->where('registration_number', 'KORA-55-001')->first();
        if ($bus1 === null) {
            return;
        }

        $bus2 = Bus::query()->where('registration_number', 'KORA-55-002')->first();
        $bus3 = Bus::query()->where('registration_number', 'KORA-55-003')->first();

        $drivers = User::query()->where('email', 'like', 'driver.%@kora.demo')->limit(2)->get();
        $conductors = User::query()->where('email', 'like', 'conductor.%@kora.demo')->limit(2)->get();

        $today = Carbon::today();
        $tomorrow = $today->copy()->addDay();
        $dayAfterTomorrow = $today->copy()->addDays(2);

        /**
         * @var list<array{
         *     line: Line,
         *     bus: Bus,
         *     date: Carbon,
         *     time: string,
         *     driver: User|null,
         *     conductor: User|null,
         *     status: DepartureStatus
         * }> $departures
         */
        $departures = [
            // Créneaux historiques sur L1 (Cotonou–Abidjan) — inchangés, jamais réassignés.
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $today,
                'time' => '16:00',
                'driver' => $drivers->get(0),
                'conductor' => $conductors->get(0),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $today,
                'time' => '19:00',
                'driver' => $drivers->get(1),
                'conductor' => $conductors->get(1),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $tomorrow,
                'time' => '07:30',
                'driver' => $drivers->get(0),
                'conductor' => $conductors->get(0),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $tomorrow,
                'time' => '14:00',
                'driver' => $drivers->get(1),
                'conductor' => $conductors->get(1),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $dayAfterTomorrow,
                'time' => '07:30',
                'driver' => $drivers->get(1),
                'conductor' => $conductors->get(1),
                'status' => DepartureStatus::Scheduled,
            ],
            // Nouveaux créneaux sur L2 (Cotonou–Lomé) — horaires distincts des créneaux
            // L1 ci-dessus, pour ne jamais entrer en collision avec `firstOrCreate` sur un
            // réensemencement partiel et ne jamais "déplacer" un départ existant d'une ligne à l'autre.
            [
                'line' => $line2,
                'bus' => $bus2 ?? $bus1,
                'date' => $today,
                'time' => '09:00',
                'driver' => $drivers->get(1),
                'conductor' => $conductors->get(1),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line2,
                'bus' => $bus3 ?? $bus1,
                'date' => $today,
                'time' => '16:00',
                'driver' => $drivers->get(0),
                'conductor' => $conductors->get(0),
                'status' => DepartureStatus::Scheduled,
            ],
        ];

        foreach ($departures as $data) {
            $departureDateTime = Carbon::parse($data['date']->format('Y-m-d').' '.$data['time']);
            $estimatedArrivalAt = $data['line']->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $data['line']->estimated_duration_minutes)
                : null;

            // `firstOrCreate` garantit que l'uuid d'un départ existant n'est jamais
            // réécrit lors d'un réensemencement : tout ticket, lien ou référence
            // externe stocké contre l'uuid reste stable. Les champs mutables
            // (conducteur, statut, heure d'arrivée estimée) sont rafraîchis
            // séparément via `update`, sans toucher à l'uuid.
            $departure = Departure::query()->firstOrCreate(
                [
                    'line_id' => $data['line']->id,
                    'bus_id' => $data['bus']->id,
                    'departure_date' => $data['date']->format('Y-m-d'),
                    'departure_time' => $data['time'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'estimated_arrival_at' => $estimatedArrivalAt,
                    'driver_id' => $data['driver']?->id,
                    'conductor_id' => $data['conductor']?->id,
                    'status' => $data['status']->value,
                ],
            );

            $departure->update([
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => $data['driver']?->id,
                'conductor_id' => $data['conductor']?->id,
                'status' => $data['status']->value,
            ]);
        }
    }
}
