<?php

namespace Database\Seeders;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\BusModel;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraBusReferenceSeeder extends Seeder
{
    public function run(): void
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

        $line = Line::query()->firstOrCreate(
            ['code' => 'L1'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Cotonou - Abidjan',
                'type' => 'internationale',
                'countries_crossed' => ['BJ', 'TG', 'GH', 'CI'],
                'distance_km' => 1020,
                'estimated_duration_minutes' => 1260,
                'status' => 'active',
            ],
        );

        $stops = [
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0],
            ['name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 35],
            ['name' => 'Lome', 'city' => 'Lome', 'country' => 'TG', 'stop_order' => 3, 'km_from_origin' => 170],
            ['name' => 'Accra', 'city' => 'Accra', 'country' => 'GH', 'stop_order' => 4, 'km_from_origin' => 370],
            ['name' => 'Abidjan', 'city' => 'Abidjan', 'country' => 'CI', 'stop_order' => 5, 'km_from_origin' => 1020],
        ];

        foreach ($stops as $stop) {
            Stop::query()->firstOrCreate(
                ['line_id' => $line->id, 'stop_order' => $stop['stop_order']],
                $stop + ['uuid' => (string) Str::uuid(), 'status' => 'active'],
            );
        }

        $agency = Agency::query()->where('code', 'CTN')->firstOrFail();
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

        $bus = Bus::query()->firstOrCreate(
            ['registration_number' => 'KORA-55-001'],
            [
                'uuid' => (string) Str::uuid(),
                'bus_model_id' => $model->id,
                'composition_id' => $composition->id,
                'agency_id' => $agency->id,
                'brand' => 'KORA',
                'model' => 'Coach 55',
                'seat_count' => 55,
                'status' => 'active',
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

    /**
     * @return array<int, string>
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
