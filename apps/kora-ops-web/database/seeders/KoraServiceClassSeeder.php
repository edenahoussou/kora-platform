<?php

namespace Database\Seeders;

use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraServiceClassSeeder extends Seeder
{
    public function run(): void
    {
        ServiceClass::query()->firstOrCreate(
            ['code' => ServiceClass::STANDARD_CODE],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Standard',
                'description' => 'Classe de service unique KORA au lancement.',
                'sort_order' => 0,
                'status' => 'active',
            ],
        );
    }
}
