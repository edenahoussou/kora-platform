<?php

namespace Database\Seeders;

use App\Modules\BusNetwork\Models\BusAmenity;
use Illuminate\Database\Seeder;

class KoraBusAmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['code' => 'wifi', 'label' => 'Wi-Fi à bord', 'icon' => 'wifi'],
            ['code' => 'air_conditioning', 'label' => 'Climatisation', 'icon' => 'snowflake'],
            ['code' => 'usb_charging', 'label' => 'Prises USB', 'icon' => 'usb'],
            ['code' => 'reclining_seats', 'label' => 'Sièges inclinables', 'icon' => 'armchair'],
            ['code' => 'onboard_toilet', 'label' => 'Toilettes à bord', 'icon' => 'toilet'],
            ['code' => 'tv_screen', 'label' => 'Écran TV', 'icon' => 'tv'],
            ['code' => 'onboard_snack', 'label' => 'Collation à bord', 'icon' => 'utensils'],
            ['code' => 'priority_luggage', 'label' => 'Bagage prioritaire', 'icon' => 'briefcase'],
        ];

        foreach ($amenities as $amenity) {
            BusAmenity::query()->firstOrCreate(
                ['code' => $amenity['code']],
                $amenity + ['status' => 'active'],
            );
        }
    }
}
