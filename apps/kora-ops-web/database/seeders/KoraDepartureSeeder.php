<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
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
