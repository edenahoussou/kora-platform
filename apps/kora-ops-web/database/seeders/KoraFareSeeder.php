<?php

namespace Database\Seeders;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Génère la matrice tarifaire O/D complète pour les 3 lignes du CDC.
 * Tarifs réalistes en XOF basés sur la distance :
 * - L1 (internationale) : ~35 FCFA/km
 * - L2, L3 (nationale) : ~22 FCFA/km
 * Deux classes de service : confort et premium (premium = +40%).
 *
 * Les fares sont créés avec direction='both' par défaut, ce qui permet
 * au moteur de tarification de les utiliser pour l'aller ET le retour.
 * Le client peut ensuite créer des fares directionnels spécifiques
 * (outbound/return) avec des montants différents si besoin.
 */
class KoraFareSeeder extends Seeder
{
    public function run(): void
    {
        $confort = ServiceClass::query()->where('code', 'confort')->first();
        $premium = ServiceClass::query()->where('code', 'premium')->first();

        if (! $confort || ! $premium) {
            return;
        }

        foreach (['L1', 'L2', 'L3'] as $lineCode) {
            $line = Line::query()->where('code', $lineCode)->first();
            if (! $line) {
                continue;
            }

            $stops = Stop::query()
                ->where('line_id', $line->id)
                ->orderBy('stop_order')
                ->get();

            if ($stops->count() < 2) {
                continue;
            }

            $ratePerKm = $lineCode === 'L1' ? 35 : 22;

            // Toutes les paires O/D valides (origine avant destination dans l'ordre des arrêts)
            for ($i = 0; $i < $stops->count(); $i++) {
                for ($j = $i + 1; $j < $stops->count(); $j++) {
                    $origin = $stops[$i];
                    $destination = $stops[$j];

                    $distance = (float) $destination->km_from_origin - (float) $origin->km_from_origin;
                    $confortPrice = (int) max(500, round($distance * $ratePerKm / 100) * 100);
                    $premiumPrice = (int) round($confortPrice * 1.4 / 100) * 100;

                    $this->firstOrCreateFare($line, $confort, $origin, $destination, $confortPrice);
                    $this->firstOrCreateFare($line, $premium, $origin, $destination, $premiumPrice);
                }
            }
        }
    }

    private function firstOrCreateFare(Line $line, ServiceClass $serviceClass, Stop $origin, Stop $destination, int $amount): void
    {
        Fare::query()->firstOrCreate(
            [
                'line_id' => $line->id,
                'service_class_id' => $serviceClass->id,
                'origin_stop_id' => $origin->id,
                'destination_stop_id' => $destination->id,
                'effective_from' => '2026-08-01',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'direction' => 'both',
                'base_amount' => $amount,
                'currency' => 'XOF',
                'status' => 'active',
            ],
        );
    }
}
