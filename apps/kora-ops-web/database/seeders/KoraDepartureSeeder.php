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
use Illuminate\Support\Str;

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
                'uuid' => (string) Str::uuid(),
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
                $stop + ['uuid' => (string) Str::uuid(), 'status' => 'active'],
            );
        }

        $bus1 = Bus::query()->where('registration_number', 'KORA-55-001')->first();
        $bus2 = Bus::query()->where('registration_number', 'KORA-55-002')->first();
        $bus3 = Bus::query()->where('registration_number', 'KORA-55-003')->first();
        if ($bus1 === null) {
            return;
        }

        $drivers = User::query()->where('email', 'like', 'driver.%@kora.demo')->limit(2)->get();
        $conductors = User::query()->where('email', 'like', 'conductor.%@kora.demo')->limit(2)->get();

        $today = Carbon::today();
        $tomorrow = Carbon::tomorrow();

        $departures = [
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $today,
                'time' => '07:30',
                'driver' => $drivers->get(0),
                'conductor' => $conductors->get(0),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line2,
                'bus' => $bus2 ?? $bus1,
                'date' => $today,
                'time' => '10:30',
                'driver' => $drivers->get(1),
                'conductor' => $conductors->get(1),
                'status' => DepartureStatus::Boarding,
            ],
            [
                'line' => $line,
                'bus' => $bus1,
                'date' => $today,
                'time' => '14:00',
                'driver' => $drivers->get(0),
                'conductor' => $conductors->get(0),
                'status' => DepartureStatus::Scheduled,
            ],
            [
                'line' => $line2,
                'bus' => $bus3 ?? $bus1,
                'date' => $tomorrow,
                'time' => '07:30',
                'driver' => $drivers->get(1),
                'conductor' => $conductors->get(1),
                'status' => DepartureStatus::Scheduled,
            ],
        ];

        foreach ($departures as $data) {
            $departureDateTime = Carbon::parse($data['date']->format('Y-m-d').' '.$data['time']);
            $estimatedArrivalAt = $data['line']->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $data['line']->estimated_duration_minutes)
                : null;

            Departure::query()->firstOrCreate(
                [
                    'line_id' => $data['line']->id,
                    'bus_id' => $data['bus']->id,
                    'departure_date' => $data['date']->format('Y-m-d'),
                    'departure_time' => $data['time'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
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
