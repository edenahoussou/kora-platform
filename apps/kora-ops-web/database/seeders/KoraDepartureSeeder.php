<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Crée des départs sur les 3 lignes du CDC avec des horaires réalistes.
 * Les bus sont assignés selon le CDC : 3 bus L1, 1 bus L2, 1 bus L3.
 *
 * Chaque départ reçoit des stop_times calculés proportionnellement à la
 * distance de chaque arrêt par rapport à l'origine, de façon à ce que
 * l'origine (stop_order = 1) et la destination (dernier stop) soient
 * toujours définies et que le resolver puisse produire les horaires
 * absolus de chaque segment.
 */
class KoraDepartureSeeder extends Seeder
{
    public function run(): void
    {
        $line1 = Line::query()->where('code', 'L1')->first();
        $line2 = Line::query()->where('code', 'L2')->first();
        $line3 = Line::query()->where('code', 'L3')->first();

        if ($line1 === null) {
            return;
        }

        $busL1a = Bus::query()->where('registration_number', 'KORA-L1-001')->first();
        $busL1b = Bus::query()->where('registration_number', 'KORA-L1-002')->first();
        $busL1c = Bus::query()->where('registration_number', 'KORA-L1-003')->first();
        $busL2 = Bus::query()->where('registration_number', 'KORA-L2-001')->first();
        $busL3 = Bus::query()->where('registration_number', 'KORA-L3-001')->first();

        $drivers = User::query()->where('email', 'like', 'driver.%@kora.demo')->limit(2)->get();
        $conductors = User::query()->where('email', 'like', 'conductor.%@kora.demo')->limit(2)->get();

        $today = Carbon::today('Africa/Porto-Novo');
        $tomorrow = $today->copy()->addDay();
        $dayAfterTomorrow = $today->copy()->addDays(2);

        /**
         * @var list<array{
         *     line: Line|null,
         *     bus: Bus|null,
         *     date: Carbon,
         *     time: string,
         *     driver: User|null,
         *     conductor: User|null,
         *     status: DepartureStatus
         * }> $departures
         */
        $departures = [
            // L1 — Corridor (Cotonou → Abidjan), 2 départs/jour
            ['line' => $line1, 'bus' => $busL1a, 'date' => $today, 'time' => '07:00', 'driver' => $drivers->get(0), 'conductor' => $conductors->get(0), 'status' => DepartureStatus::Scheduled],
            ['line' => $line1, 'bus' => $busL1b, 'date' => $today, 'time' => '16:00', 'driver' => $drivers->get(1), 'conductor' => $conductors->get(1), 'status' => DepartureStatus::Scheduled],
            ['line' => $line1, 'bus' => $busL1c, 'date' => $tomorrow, 'time' => '07:00', 'driver' => $drivers->get(0), 'conductor' => $conductors->get(0), 'status' => DepartureStatus::Scheduled],
            ['line' => $line1, 'bus' => $busL1a, 'date' => $tomorrow, 'time' => '16:00', 'driver' => $drivers->get(1), 'conductor' => $conductors->get(1), 'status' => DepartureStatus::Scheduled],
            ['line' => $line1, 'bus' => $busL1b, 'date' => $dayAfterTomorrow, 'time' => '07:00', 'driver' => $drivers->get(0), 'conductor' => $conductors->get(0), 'status' => DepartureStatus::Scheduled],

            // L2 — Nord-Est (Cotonou → Malanville), 2 départs/jour
            ['line' => $line2, 'bus' => $busL2, 'date' => $today, 'time' => '06:00', 'driver' => $drivers->get(1), 'conductor' => $conductors->get(1), 'status' => DepartureStatus::Scheduled],
            ['line' => $line2, 'bus' => $busL2, 'date' => $today, 'time' => '14:00', 'driver' => $drivers->get(0), 'conductor' => $conductors->get(0), 'status' => DepartureStatus::Scheduled],
            ['line' => $line2, 'bus' => $busL2, 'date' => $tomorrow, 'time' => '06:00', 'driver' => $drivers->get(1), 'conductor' => $conductors->get(1), 'status' => DepartureStatus::Scheduled],

            // L3 — Nord-Ouest (Cotonou → Tanguiéta), 1 départ/jour
            ['line' => $line3, 'bus' => $busL3, 'date' => $today, 'time' => '07:00', 'driver' => $drivers->get(0), 'conductor' => $conductors->get(0), 'status' => DepartureStatus::Scheduled],
            ['line' => $line3, 'bus' => $busL3, 'date' => $tomorrow, 'time' => '07:00', 'driver' => $drivers->get(1), 'conductor' => $conductors->get(1), 'status' => DepartureStatus::Scheduled],
        ];

        foreach ($departures as $data) {
            if ($data['line'] === null || $data['bus'] === null) {
                continue;
            }

            // Les horaires saisis sont en heure locale de Porto-Novo :
            // on les convertit en instants UTC pour scheduled_at et
            // estimated_arrival_at (timestamps relus en UTC).
            $departureDateTime = CarbonImmutable::parse($data['date']->format('Y-m-d').' '.$data['time'], 'Africa/Porto-Novo');
            $estimatedArrivalAt = $data['line']->estimated_duration_minutes
                ? $departureDateTime->addMinutes((int) $data['line']->estimated_duration_minutes)->utc()
                : null;

            $stopTimes = $this->buildStopTimes($data['line']);

            // `firstOrCreate` garantit que l'uuid d'un départ existant n'est jamais
            // réécrit lors d'un réensemencement. Les champs mutables sont rafraîchis
            // via `update`, sans toucher à l'uuid.
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
                    'scheduled_at' => $departureDateTime,
                    'departure_timezone' => 'Africa/Porto-Novo',
                    'stop_times' => $stopTimes,
                ],
            );

            $departure->update([
                'estimated_arrival_at' => $estimatedArrivalAt,
                'driver_id' => $data['driver']?->id,
                'conductor_id' => $data['conductor']?->id,
                'status' => $data['status']->value,
                'scheduled_at' => $departureDateTime,
                'departure_timezone' => 'Africa/Porto-Novo',
                'stop_times' => $stopTimes,
            ]);
        }
    }

    /**
     * Construit les stop_times d'une ligne en répartissant la durée estimée
     * proportionnellement à la distance de chaque arrêt.
     *
     * @return list<array{stop_uuid: string, departure_minutes: int|null, arrival_minutes: int|null, timezone: string}>
     */
    private function buildStopTimes(Line $line): array
    {
        $stops = Stop::query()
            ->where('line_id', $line->id)
            ->where('status', 'active')
            ->orderBy('stop_order')
            ->get();

        if ($stops->isEmpty()) {
            return [];
        }

        $totalDistance = (float) $stops->last()->km_from_origin;
        $totalDuration = (int) ($line->estimated_duration_minutes ?? 0);

        if ($totalDistance <= 0 || $totalDuration <= 0) {
            /** @var list<array{stop_uuid: string, departure_minutes: int|null, arrival_minutes: int|null, timezone: string}> $fallback */
            $fallback = $stops->values()->map(fn (Stop $stop) => [
                'stop_uuid' => $stop->uuid,
                'departure_minutes' => $stop->stop_order === 1 ? 0 : null,
                'arrival_minutes' => null,
                'timezone' => 'Africa/Porto-Novo',
            ])->values()->all();

            return $fallback;
        }

        $stopTimes = [];
        $stopCount = $stops->count();

        foreach ($stops as $index => $stop) {
            $distance = (float) $stop->km_from_origin;
            $ratio = $totalDistance > 0 ? $distance / $totalDistance : 0;
            $minutesFromOrigin = (int) round($ratio * $totalDuration);

            $isFirst = $index === 0;
            $isLast = $index === $stopCount - 1;

            // Arrêt intermédiaire : on laisse une marge d'arrêt de 10 minutes.
            // Le bus arrive à minutesFromOrigin - 10 et repart à minutesFromOrigin.
            $dwellMinutes = 10;

            if ($isFirst) {
                $departureMinutes = 0;
                $arrivalMinutes = null;
            } elseif ($isLast) {
                $departureMinutes = null;
                $arrivalMinutes = $minutesFromOrigin;
            } else {
                $arrivalMinutes = max(0, $minutesFromOrigin - $dwellMinutes);
                $departureMinutes = $minutesFromOrigin;
            }

            $stopTimes[] = [
                'stop_uuid' => $stop->uuid,
                'departure_minutes' => $departureMinutes,
                'arrival_minutes' => $arrivalMinutes,
                'timezone' => $this->timezoneForCountry($stop->country),
            ];
        }

        return $stopTimes;
    }

    private function timezoneForCountry(string $country): string
    {
        return match ($country) {
            'CI' => 'Africa/Abidjan',
            'GH' => 'Africa/Accra',
            'TG' => 'Africa/Lome',
            default => 'Africa/Porto-Novo',
        };
    }
}
