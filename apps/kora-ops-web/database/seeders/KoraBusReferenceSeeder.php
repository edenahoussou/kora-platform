<?php

namespace Database\Seeders;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Aligne les données de référence sur le CDC KORA BUS MVP :
 * 3 lignes (L1 Corridor, L2 Nord-Est, L3 Nord-Ouest), 5 bus en 55 places,
 * et des points d'arrêt physiques partagés entre lignes pour permettre
 * les correspondances (Cotonou, Allada, Bohicon entre L2 et L3).
 */
class KoraBusReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedAgency();
        $this->seedPhysicalStops();
        $this->seedLinesAndStops();
        $this->seedFleet();
    }

    private function seedAgency(): void
    {
        Agency::query()->firstOrCreate(
            ['code' => 'CTN'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Agence Cotonou',
                'city' => 'Cotonou',
                'country' => 'BJ',
                'status' => 'active',
            ],
        );
    }

    /**
     * Crée les points d'arrêt physiques partagés entre lignes.
     * Chaque ville = un PhysicalStop unique, réutilisé par toutes les lignes
     * qui la desservent. C'est le prérequis pour les correspondances.
     *
     * @return array<string, PhysicalStop> indexé par nom de ville
     */
    private function seedPhysicalStops(): array
    {
        $cities = [
            // Bénin
            ['code' => 'BJ-CTN', 'name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ'],
            ['code' => 'BJ-PNR', 'name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ'],
            ['code' => 'BJ-OUID', 'name' => 'Ouidah', 'city' => 'Ouidah', 'country' => 'BJ'],
            ['code' => 'BJ-COME', 'name' => 'Comé', 'city' => 'Comé', 'country' => 'BJ'],
            ['code' => 'BJ-GPOP', 'name' => 'Grand-Popo', 'city' => 'Grand-Popo', 'country' => 'BJ'],
            ['code' => 'BJ-ALLA', 'name' => 'Allada', 'city' => 'Allada', 'country' => 'BJ'],
            ['code' => 'BJ-BOHI', 'name' => 'Bohicon', 'city' => 'Bohicon', 'country' => 'BJ'],
            ['code' => 'BJ-DASS', 'name' => 'Dassa', 'city' => 'Dassa', 'country' => 'BJ'],
            ['code' => 'BJ-PARA', 'name' => 'Parakou', 'city' => 'Parakou', 'country' => 'BJ'],
            ['code' => 'BJ-KAND', 'name' => 'Kandi', 'city' => 'Kandi', 'country' => 'BJ'],
            ['code' => 'BJ-MALV', 'name' => 'Malanville', 'city' => 'Malanville', 'country' => 'BJ'],
            ['code' => 'BJ-DJOU', 'name' => 'Djougou', 'city' => 'Djougou', 'country' => 'BJ'],
            ['code' => 'BJ-NATI', 'name' => 'Natitingou', 'city' => 'Natitingou', 'country' => 'BJ'],
            ['code' => 'BJ-TANG', 'name' => 'Tanguiéta', 'city' => 'Tanguiéta', 'country' => 'BJ'],
            // Togo
            ['code' => 'TG-LFW', 'name' => 'Lomé', 'city' => 'Lomé', 'country' => 'TG'],
            // Ghana
            ['code' => 'GH-ACC', 'name' => 'Accra', 'city' => 'Accra', 'country' => 'GH'],
            // Côte d'Ivoire
            ['code' => 'CI-ABJ', 'name' => 'Abidjan', 'city' => 'Abidjan', 'country' => 'CI'],
        ];

        $physicals = [];
        foreach ($cities as $city) {
            $physicals[$city['name']] = PhysicalStop::query()->firstOrCreate(
                ['code' => $city['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $city['name'],
                    'city' => $city['city'],
                    'country' => $city['country'],
                    'status' => 'active',
                ],
            );
        }

        return $physicals;
    }

    private function seedLinesAndStops(): void
    {
        $physicals = PhysicalStop::query()->get()->keyBy('name');

        $lines = [
            [
                'code' => 'L1',
                'name' => 'Corridor',
                'type' => 'internationale',
                'countries_crossed' => ['CI', 'GH', 'TG', 'BJ'],
                'distance_km' => 874,
                'estimated_duration_minutes' => 1110,
                'stops' => [
                    ['name' => 'Cotonou', 'stop_order' => 1, 'km_from_origin' => 0],
                    ['name' => 'Porto-Novo', 'stop_order' => 2, 'km_from_origin' => 30],
                    ['name' => 'Ouidah', 'stop_order' => 3, 'km_from_origin' => 70],
                    ['name' => 'Comé', 'stop_order' => 4, 'km_from_origin' => 105],
                    ['name' => 'Grand-Popo', 'stop_order' => 5, 'km_from_origin' => 125],
                    ['name' => 'Lomé', 'stop_order' => 6, 'km_from_origin' => 190],
                    ['name' => 'Accra', 'stop_order' => 7, 'km_from_origin' => 390],
                    ['name' => 'Abidjan', 'stop_order' => 8, 'km_from_origin' => 904],
                ],
            ],
            [
                'code' => 'L2',
                'name' => 'Nord-Est',
                'type' => 'nationale',
                'countries_crossed' => ['BJ'],
                'distance_km' => 711,
                'estimated_duration_minutes' => 600,
                'stops' => [
                    ['name' => 'Cotonou', 'stop_order' => 1, 'km_from_origin' => 0],
                    ['name' => 'Allada', 'stop_order' => 2, 'km_from_origin' => 50],
                    ['name' => 'Bohicon', 'stop_order' => 3, 'km_from_origin' => 120],
                    ['name' => 'Dassa', 'stop_order' => 4, 'km_from_origin' => 200],
                    ['name' => 'Parakou', 'stop_order' => 5, 'km_from_origin' => 410],
                    ['name' => 'Kandi', 'stop_order' => 6, 'km_from_origin' => 580],
                    ['name' => 'Malanville', 'stop_order' => 7, 'km_from_origin' => 711],
                ],
            ],
            [
                'code' => 'L3',
                'name' => 'Nord-Ouest',
                'type' => 'nationale',
                'countries_crossed' => ['BJ'],
                'distance_km' => 600,
                'estimated_duration_minutes' => 480,
                'stops' => [
                    ['name' => 'Cotonou', 'stop_order' => 1, 'km_from_origin' => 0],
                    ['name' => 'Allada', 'stop_order' => 2, 'km_from_origin' => 50],
                    ['name' => 'Bohicon', 'stop_order' => 3, 'km_from_origin' => 120],
                    ['name' => 'Djougou', 'stop_order' => 4, 'km_from_origin' => 320],
                    ['name' => 'Natitingou', 'stop_order' => 5, 'km_from_origin' => 480],
                    ['name' => 'Tanguiéta', 'stop_order' => 6, 'km_from_origin' => 600],
                ],
            ],
        ];

        foreach ($lines as $lineData) {
            $line = Line::query()->updateOrCreate(
                ['code' => $lineData['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $lineData['name'],
                    'type' => $lineData['type'],
                    'bidirectional' => true,
                    'countries_crossed' => $lineData['countries_crossed'],
                    'distance_km' => $lineData['distance_km'],
                    'estimated_duration_minutes' => $lineData['estimated_duration_minutes'],
                    'status' => 'active',
                ],
            );

            foreach ($lineData['stops'] as $stopData) {
                $physical = $physicals->get($stopData['name']);
                if ($physical === null) {
                    throw new RuntimeException("Physical stop introuvable pour {$stopData['name']}");
                }

                Stop::query()->firstOrCreate(
                    ['line_id' => $line->id, 'stop_order' => $stopData['stop_order']],
                    [
                        'uuid' => (string) Str::uuid(),
                        'physical_stop_id' => $physical->id,
                        'name' => $stopData['name'],
                        'city' => $physical->city,
                        'country' => $physical->country,
                        'km_from_origin' => $stopData['km_from_origin'],
                        'status' => 'active',
                    ],
                );
            }
        }
    }

    private function seedFleet(): void
    {
        $model = BusModel::query()->firstOrCreate(
            ['brand' => 'KORA', 'name' => 'Coach 55'],
            ['uuid' => (string) Str::uuid(), 'default_seat_count' => 55, 'status' => 'active'],
        );

        $composition = BusComposition::query()->firstOrCreate(
            ['code' => '2+2-55'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Standard 2+2 — 55 places',
                'seat_count' => 55,
                'layout' => $this->compositionLayout(),
                'status' => 'active',
            ],
        );

        // 10 bus : 4 pour L1 (corridor international, rotations aller/retour),
        // 3 pour L2 (Nord-Est, 2 départs/jour + backup), 3 pour L3 (Nord-Ouest).
        $buses = [
            ['registration_number' => 'KORA-L1-001', 'status' => 'active'],
            ['registration_number' => 'KORA-L1-002', 'status' => 'active'],
            ['registration_number' => 'KORA-L1-003', 'status' => 'active'],
            ['registration_number' => 'KORA-L1-004', 'status' => 'active'],
            ['registration_number' => 'KORA-L2-001', 'status' => 'active'],
            ['registration_number' => 'KORA-L2-002', 'status' => 'active'],
            ['registration_number' => 'KORA-L2-003', 'status' => 'active'],
            ['registration_number' => 'KORA-L3-001', 'status' => 'active'],
            ['registration_number' => 'KORA-L3-002', 'status' => 'active'],
            ['registration_number' => 'KORA-L3-003', 'status' => 'active'],
        ];

        foreach ($buses as $busData) {
            $bus = Bus::query()->firstOrCreate(
                ['registration_number' => $busData['registration_number']],
                [
                    'uuid' => (string) Str::uuid(),
                    'bus_model_id' => $model->id,
                    'composition_id' => $composition->id,
                    'brand' => 'KORA',
                    'model' => 'Coach 55',
                    'seat_count' => 55,
                    'status' => $busData['status'],
                ],
            );

            foreach ($this->seatCodes() as $code) {
                Seat::query()->firstOrCreate(
                    ['bus_id' => $bus->id, 'code' => $code],
                    [
                        'uuid' => (string) Str::uuid(),
                        'type' => str_starts_with($code, '1') ? 'crew' : 'standard',
                        'sellable' => ! str_starts_with($code, '1'),
                        'status' => str_starts_with($code, '1') ? 'blocked' : 'active',
                    ],
                );
            }
        }
    }

    /**
     * @return list<array{type: string, codes: list<string>}>
     */
    private function compositionLayout(): array
    {
        $layout = [];
        foreach (range(1, 13) as $row) {
            $layout[] = ['type' => 'standard', 'codes' => [$row.'A', $row.'B', $row.'C', $row.'D']];
        }
        $layout[] = ['type' => 'standard', 'codes' => ['14A', '14B', '14C']];

        return $layout;
    }

    /**
     * @return list<string>
     */
    private function seatCodes(): array
    {
        $codes = [];

        foreach (range(1, 13) as $row) {
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                $codes[] = $row.$letter;
            }
        }

        foreach (['A', 'B', 'C'] as $letter) {
            $codes[] = '14'.$letter;
        }

        return $codes;
    }
}
