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
 * Une seule classe de service au lancement : standard.
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
        $standard = ServiceClass::query()->where('code', ServiceClass::STANDARD_CODE)->first();

        if (! $standard) {
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
                    $price = (int) max(500, round($distance * $ratePerKm / 100) * 100);

                    $this->firstOrCreateFare($line, $standard, $origin, $destination, $price);
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
