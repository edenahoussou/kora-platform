<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\PlannedDeparture;
use App\Modules\Departures\Services\PlanningService;
use App\Modules\Departures\Services\PublicationService;
use App\Modules\Departures\Services\TemplateService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Programme les départs KORA BUS via le modèle Template → Version → Programme
 * → PlannedDeparture → Publication, au lieu de créer des Departure en direct.
 *
 * Chaque ligne (L1, L2, L3) reçoit un template avec des slots définissant les
 * horaires réels par arrêt, le bus et l'équipage. Un programme couvre 90 jours
 * et les occurrences sont publiées pour produire les Departure vendables avec
 * stop_times corrects.
 *
 * Les assignments bus/chauffeur/convoyeur sont répartis pour éviter tout
 * conflit de ressource (un bus ou un membre d'équipage ne peut pas être sur
 * deux départs simultanés).
 */
class KoraDepartureProgrammingSeeder extends Seeder
{
    public function run(): void
    {
        $opsDirector = User::query()
            ->where('email', 'ops.director@kora.demo')
            ->first();

        if ($opsDirector === null) {
            return;
        }

        $line1 = Line::query()->where('code', 'L1')->first();
        $line2 = Line::query()->where('code', 'L2')->first();
        $line3 = Line::query()->where('code', 'L3')->first();

        if ($line1 === null) {
            return;
        }

        // 10 bus disponibles
        $buses = [
            'L1-001' => Bus::query()->where('registration_number', 'KORA-L1-001')->first(),
            'L1-002' => Bus::query()->where('registration_number', 'KORA-L1-002')->first(),
            'L1-003' => Bus::query()->where('registration_number', 'KORA-L1-003')->first(),
            'L1-004' => Bus::query()->where('registration_number', 'KORA-L1-004')->first(),
            'L2-001' => Bus::query()->where('registration_number', 'KORA-L2-001')->first(),
            'L2-002' => Bus::query()->where('registration_number', 'KORA-L2-002')->first(),
            'L2-003' => Bus::query()->where('registration_number', 'KORA-L2-003')->first(),
            'L3-001' => Bus::query()->where('registration_number', 'KORA-L3-001')->first(),
            'L3-002' => Bus::query()->where('registration_number', 'KORA-L3-002')->first(),
            'L3-003' => Bus::query()->where('registration_number', 'KORA-L3-003')->first(),
        ];

        // 6 chauffeurs
        $drivers = [
            'jb' => User::query()->where('email', 'driver.jb@kora.demo')->first(),
            'paulin' => User::query()->where('email', 'driver.paulin@kora.demo')->first(),
            'koffi' => User::query()->where('email', 'driver.koffi@kora.demo')->first(),
            'mensah' => User::query()->where('email', 'driver.mensah@kora.demo')->first(),
            'basil' => User::query()->where('email', 'driver.basil@kora.demo')->first(),
            'herve' => User::query()->where('email', 'driver.herve@kora.demo')->first(),
        ];

        // 6 convoyeurs
        $conductors = [
            'marc' => User::query()->where('email', 'conductor.marc@kora.demo')->first(),
            'luc' => User::query()->where('email', 'conductor.luc@kora.demo')->first(),
            'aime' => User::query()->where('email', 'conductor.aime@kora.demo')->first(),
            'david' => User::query()->where('email', 'conductor.david@kora.demo')->first(),
            'serge' => User::query()->where('email', 'conductor.serge@kora.demo')->first(),
            'eric' => User::query()->where('email', 'conductor.eric@kora.demo')->first(),
        ];

        $planningService = app(PlanningService::class);

        $today = CarbonImmutable::today();
        $endsOn = $today->addDays(89);

        // --- L1 : Corridor international (aller + retour) ---
        // L1 dure 18h30, donc un bus ne fait qu'un aller par jour.
        // Aller 07:00 → arrivée 01:30 (+1j), Retour 07:00 (+1j) → arrivée 01:30 (+2j)
        // Bus différents pour aller et retour pour éviter conflit.
        $this->programmeLine(
            name: 'L1 Corridor',
            line: $line1,
            slots: [
                // Aller 07:00 — bus L1-001, driver jb, conductor marc
                $this->buildSlot(
                    line: $line1,
                    direction: 'outbound',
                    time: '07:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L1-001'],
                    driver: $drivers['jb'],
                    conductor: $conductors['marc'],
                    returnKey: null,
                ),
                // Aller 16:00 — bus L1-002, driver paulin, conductor luc
                $this->buildSlot(
                    line: $line1,
                    direction: 'outbound',
                    time: '16:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L1-002'],
                    driver: $drivers['paulin'],
                    conductor: $conductors['luc'],
                    returnKey: null,
                ),
                // Retour 07:00 (J+1) — bus L1-003, driver koffi, conductor aime
                $this->buildSlot(
                    line: $line1,
                    direction: 'return',
                    time: '07:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L1-003'],
                    driver: $drivers['koffi'],
                    conductor: $conductors['aime'],
                    returnKey: null,
                ),
                // Retour 16:00 (J+1) — bus L1-004, driver mensah, conductor david
                $this->buildSlot(
                    line: $line1,
                    direction: 'return',
                    time: '16:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L1-004'],
                    driver: $drivers['mensah'],
                    conductor: $conductors['david'],
                    returnKey: null,
                ),
            ],
            actor: $opsDirector,
            startsOn: $today,
            endsOn: $endsOn,
            planningService: $planningService,
        );

        // --- L2 : Nord-Est (aller + retour, 2 allers + 1 retour/jour) ---
        // L2 dure 10h. Aller 06:00 → 16:00, aller 14:00 → 00:00+1.
        // Retour 18:00 → 04:00+1 (après arrivée du 06:00 à 16:00).
        $this->programmeLine(
            name: 'L2 Nord-Est',
            line: $line2,
            slots: [
                // 06:00 — bus L2-001, driver basil, conductor serge
                $this->buildSlot(
                    line: $line2,
                    direction: 'outbound',
                    time: '06:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L2-001'],
                    driver: $drivers['basil'],
                    conductor: $conductors['serge'],
                    returnKey: null,
                ),
                // 14:00 — bus L2-002, driver herve, conductor eric
                $this->buildSlot(
                    line: $line2,
                    direction: 'outbound',
                    time: '14:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L2-002'],
                    driver: $drivers['herve'],
                    conductor: $conductors['eric'],
                    returnKey: null,
                ),
                // Retour 18:00 — bus L2-003, pas d'équipage (libre après L2 06:00)
                $this->buildSlot(
                    line: $line2,
                    direction: 'return',
                    time: '18:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L2-003'],
                    driver: null,
                    conductor: null,
                    returnKey: null,
                ),
            ],
            actor: $opsDirector,
            startsOn: $today,
            endsOn: $endsOn,
            planningService: $planningService,
        );

        // --- L3 : Nord-Ouest (aller + retour, 1 aller + 1 retour/jour) ---
        // L3 dure 8h. Aller 07:00 → 15:00. Retour 16:00 → 00:00.
        $this->programmeLine(
            name: 'L3 Nord-Ouest',
            line: $line3,
            slots: [
                // 07:00 — bus L3-001, pas d'équipage
                $this->buildSlot(
                    line: $line3,
                    direction: 'outbound',
                    time: '07:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L3-001'],
                    driver: null,
                    conductor: null,
                    returnKey: null,
                ),
                // Retour 16:00 — bus L3-002, pas d'équipage
                $this->buildSlot(
                    line: $line3,
                    direction: 'return',
                    time: '16:00',
                    days: [1, 2, 3, 4, 5, 6, 7],
                    intervalWeeks: 1,
                    bus: $buses['L3-002'],
                    driver: null,
                    conductor: null,
                    returnKey: null,
                ),
            ],
            actor: $opsDirector,
            startsOn: $today,
            endsOn: $endsOn,
            planningService: $planningService,
        );
    }

    /**
     * Crée un Template + Version, un Programme, génère les PlannedDeparture
     * et publie les occurrences futures (aujourd'hui + 7 jours).
     *
     * @param  list<array<string, mixed>>  $slots
     */
    private function programmeLine(
        string $name,
        Line $line,
        array $slots,
        User $actor,
        CarbonImmutable $startsOn,
        CarbonImmutable $endsOn,
        PlanningService $planningService,
    ): void {
        $templateService = app(TemplateService::class);

        // 1. Créer le Template + Version
        $template = $templateService->save([
            'name' => $name,
            'revision' => 0,
            'ready' => true,
            'slots' => $slots,
        ], $actor);

        $version = $template->versions()->latest('id')->first();
        if ($version === null) {
            return;
        }

        // 2. Créer le Programme
        $programme = $planningService->programme([
            'name' => $name.' — Programme',
            'version_uuid' => $version->uuid,
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $endsOn->toDateString(),
            'horizon_days' => 90,
            'exceptions' => [],
        ], $actor);

        // 3. Générer les PlannedDeparture sur la période
        $planningService->generate(
            $programme,
            $startsOn,
            $endsOn,
            $actor,
        );

        // 4. Publier les occurrences (aujourd'hui + 7 jours pour les tests manuels)
        $publishUntil = $startsOn->addDays(7);
        $occurrences = PlannedDeparture::query()
            ->where('programme_id', $programme->id)
            ->where('state', 'planned')
            ->whereNull('departure_id')
            ->whereDate('service_date', '>=', $startsOn->toDateString())
            ->whereDate('service_date', '<=', $publishUntil->toDateString())
            ->orderBy('scheduled_at')
            ->get();

        $publicationService = app(PublicationService::class);

        foreach ($occurrences as $occurrence) {
            try {
                $publicationService->publish($occurrence, $actor);
            } catch (\Throwable) {
                // Skip occurrences that can't be published (conflicts, missing resources, etc.)
            }
        }
    }

    /**
     * Construit un slot avec les horaires réels par arrêt.
     *
     * @param  list<int>  $days
     * @return array<string, mixed>
     */
    private function buildSlot(
        Line $line,
        string $direction,
        string $time,
        array $days,
        int $intervalWeeks,
        ?Bus $bus,
        ?User $driver,
        ?User $conductor,
        ?string $returnKey,
    ): array {
        $stops = Stop::query()
            ->where('line_id', $line->id)
            ->where('status', 'active')
            ->orderBy('stop_order')
            ->get();

        // For return trips, reverse the stop order so the circulation
        // starts from the last stop back to the first.
        if ($direction === 'return') {
            $stops = $stops->reverse()->values();
        }

        $totalDuration = (int) ($line->estimated_duration_minutes ?? 480);
        $totalDistance = abs((float) $stops->last()?->km_from_origin - (float) $stops->first()?->km_from_origin);
        $firstKm = (float) $stops->first()?->km_from_origin;

        $stopTimes = [];
        $stopCount = $stops->count();

        foreach ($stops as $index => $stop) {
            $distance = abs((float) $stop->km_from_origin - $firstKm);
            $ratio = $totalDistance > 0 ? $distance / $totalDistance : 0;
            $minutesFromOrigin = (int) round($ratio * $totalDuration);

            $isFirst = $index === 0;
            $isLast = $index === $stopCount - 1;
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
                'arrival_minutes' => $arrivalMinutes,
                'departure_minutes' => $departureMinutes,
                'timezone' => $this->timezoneForCountry($stop->country),
            ];
        }

        return [
            'key' => (string) Str::uuid(),
            'direction' => $direction,
            'line_uuid' => $line->uuid,
            'timezone' => 'Africa/Porto-Novo',
            'time' => $time,
            'days' => $days,
            'interval_weeks' => $intervalWeeks,
            'duration_minutes' => $totalDuration,
            'preparation_minutes' => 30,
            'return_key' => $returnKey,
            'return_day_offset' => 0,
            'turnaround_minutes' => 60,
            'bus_uuid' => $bus?->uuid,
            'driver_uuid' => $driver?->uuid,
            'secondary_driver_uuid' => null,
            'conductor_uuid' => $conductor?->uuid,
            'mechanic_uuid' => null,
            'stops' => $stopTimes,
        ];
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
