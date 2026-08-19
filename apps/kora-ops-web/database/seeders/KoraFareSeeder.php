<?php

namespace Database\Seeders;

use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\ServiceClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraFareSeeder extends Seeder
{
    public function run(): void
    {
        $line = Line::query()->where('code', 'L1')->first();
        if (! $line) {
            return;
        }

        $stops = Stop::query()
            ->where('line_id', $line->id)
            ->orderBy('stop_order')
            ->get();

        if ($stops->count() < 2) {
            return;
        }

        $confort = ServiceClass::query()->where('code', 'confort')->first();
        $premium = ServiceClass::query()->where('code', 'premium')->first();

        if (! $confort || ! $premium) {
            return;
        }

        $grille = [
            // Cotonou → ...
            ['Cotonou', 'Porto-Novo', 1500, 2500],
            ['Cotonou', 'Lome', 7000, 10000],
            ['Cotonou', 'Accra', 14000, 18000],
            ['Cotonou', 'Abidjan', 28000, 35000],
            // Porto-Novo → ...
            ['Porto-Novo', 'Lome', 6000, 9000],
            ['Porto-Novo', 'Accra', 13000, 17000],
            ['Porto-Novo', 'Abidjan', 27000, 34000],
            // Lome → ...
            ['Lome', 'Accra', 8000, 11000],
            ['Lome', 'Abidjan', 22000, 28000],
            // Accra → ...
            ['Accra', 'Abidjan', 15000, 20000],
        ];

        foreach ($grille as [$originName, $destinationName, $confortPrice, $premiumPrice]) {
            $origin = $stops->firstWhere('name', $originName);
            $destination = $stops->firstWhere('name', $destinationName);

            if (! $origin || ! $destination) {
                continue;
            }

            $this->firstOrCreateFare($line, $confort, $origin, $destination, $confortPrice);
            $this->firstOrCreateFare($line, $premium, $origin, $destination, $premiumPrice);
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
                'base_amount' => $amount,
                'currency' => 'XOF',
                'status' => 'active',
            ],
        );
    }
}
