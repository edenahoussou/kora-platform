<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            KoraBusRolePermissionSeeder::class,
            KoraServiceClassSeeder::class,
            KoraBusReferenceSeeder::class,
            KoraBusAmenitySeeder::class,
            KoraFareSeeder::class,
            KoraDemoUserSeeder::class,
            KoraTerminalP501Seeder::class,
            KoraCounterSaleSeeder::class,
            KoraDepartureProgrammingSeeder::class,
            KoraTerminalP501DemoCrewSeeder::class,
            Mez57GroupPurchaseSeeder::class,
            KoraCompanyAccountSeeder::class,
            KoraExpenseCategorySeeder::class,
            WalletLoyaltyRuleVersionSeeder::class,
        ]);
    }
}
