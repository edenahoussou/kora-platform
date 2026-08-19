<?php

namespace Database\Seeders;

use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraServiceClassSeeder extends Seeder
{
    public function run(): void
    {
        $classes = [
            [
                'code' => 'confort',
                'name' => 'KORA Confort',
                'description' => 'Service standard KORA — sièges inclinables 2+2, climatisation, bagage cabine.',
                'sort_order' => 1,
            ],
            [
                'code' => 'premium',
                'name' => 'KORA Premium',
                'description' => 'Service premium KORA — sièges larges 2+1, snack à bord, Wi-Fi, bagage prioritaire.',
                'sort_order' => 2,
            ],
        ];

        foreach ($classes as $class) {
            ServiceClass::query()->firstOrCreate(
                ['code' => $class['code']],
                $class + ['uuid' => (string) Str::uuid(), 'status' => 'active'],
            );
        }
    }
}
