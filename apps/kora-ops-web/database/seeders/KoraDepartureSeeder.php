<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class KoraDepartureSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->where('code', 'CTN')->first();
        if ($agency === null) {
            return;
        }

        $line = Line::query()->where('code', 'L1')->first();
        if ($line === null) {
            return;
        }

        $line2 = Line::query()->firstOrCreate(
            ['code' => 'L2'],
            [
                'uuid' => (string) \Str::uuid(),
                'name' => 'Cotonou - Lomé',
                'type' => 'internationale',
                'countries_crossed' => ['BJ', 'TG'],
                'distance_km' => 170,
                'estimated_duration_minutes' => 210,
                'status' => 'active',
            ],
        );

        foreach ([
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0],
            ['name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 35],
            ['name' => 'Lome', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 3, 'km_from_origin' => 170],
        ] as $stop) {
            Stop::query()->firstOrCreate(
                ['line_id' => $line2->id, 'stop_order' => $stop['stop_order']],
                $stop + ['uuid' => (string) \Str::uuid(), 'status' => 'active'],
            );
        }

        $bus1 = Bus::query()->where('registration_number', 'KORA-55-001')->first();
        if ($bus1 === null) {
            return;
        }

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

            Departure::query()->updateOrCreate(
                [
                    'line_id' => $data['line']->id,
                    'bus_id' => $data['bus']->id,
                    'departure_date' => $data['date']->format('Y-m-d'),
                    'departure_time' => $data['time'],
                ],
                [
                    'agency_id' => $agency->id,
                    'estimated_arrival_at' => $estimatedArrivalAt,
                    'driver_id' => $data['driver']?->id,
                    'conductor_id' => $data['conductor']?->id,
                    'status' => $data['status']->value,
                ],
            );
        }
    }
}
