<?php

namespace Database\Seeders;

use App\Modules\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class KoraExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['code' => 'fuel', 'label' => 'Carburant'],
            ['code' => 'maintenance', 'label' => 'Maintenance'],
            ['code' => 'tolls', 'label' => 'Péages'],
            ['code' => 'agency_supplies', 'label' => 'Fournitures agence'],
            ['code' => 'services', 'label' => 'Services'],
            ['code' => 'other', 'label' => 'Autres'],
        ] as $category) {
            ExpenseCategory::query()->updateOrCreate(
                ['code' => $category['code']],
                ['label' => $category['label'], 'is_active' => true],
            );
        }
    }
}
