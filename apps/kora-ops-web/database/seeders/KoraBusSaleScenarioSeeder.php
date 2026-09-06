<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Prépare un jeu de données local pour parcourir le Guichet KORA BUS v2.
 *
 * Les seeders appelés sont idempotents et peuvent être rejoués après une
 * migration ou depuis un environnement de démonstration.
 */
class KoraBusSaleScenarioSeeder extends Seeder
{
    /**
     * Seed the data needed by the four scénarios de validation du guichet v2.
     */
    public function run(): void
    {
        $this->call([
            KoraServiceClassSeeder::class,
            KoraBusReferenceSeeder::class,
            KoraBusAmenitySeeder::class,
            KoraFareSeeder::class,
        ]);

        if (! User::query()->where('email', 'passenger.koffi@kora.demo')->exists()) {
            $this->call(KoraDemoUserSeeder::class);
        }

        $this->call([
            KoraCounterSaleSeeder::class,
            KoraDepartureSeeder::class,
            KoraCompanyAccountSeeder::class,
        ]);

        $this->command->info('Scénarios Guichet KORA BUS v2 prêts : direct, groupe, aller-retour et correspondance.');
    }
}
