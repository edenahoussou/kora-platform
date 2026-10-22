<?php

namespace Database\Seeders;

use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'fuel', 'label' => 'Carburant'],
            ['code' => 'maintenance', 'label' => 'Maintenance'],
            ['code' => 'tolls', 'label' => 'Péages'],
            ['code' => 'tires', 'label' => 'Pneus'],
            ['code' => 'insurance', 'label' => 'Assurance'],
            ['code' => 'leasing', 'label' => 'Leasing'],
            ['code' => 'agency_supplies', 'label' => 'Fournitures agence'],
            ['code' => 'services', 'label' => 'Services'],
            ['code' => 'other', 'label' => 'Autres'],
        ] as $category) {
            ExpenseCategory::query()->firstOrCreate(
                ['code' => $category['code']],
                [
                    'uuid' => (string) Str::uuid(),
                    'label' => $category['label'],
                    'is_active' => true,
                ],
            );
        }
    }
}
