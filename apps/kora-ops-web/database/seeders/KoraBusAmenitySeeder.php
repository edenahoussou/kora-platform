<?php

namespace Database\Seeders;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusAmenity;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraBusAmenitySeeder extends Seeder
{
    public function run(): void
    {
        $amenities = [
            ['code' => 'wifi', 'label' => 'Wi-Fi à bord', 'icon' => 'wifi'],
            ['code' => 'air_conditioning', 'label' => 'Climatisation', 'icon' => 'snowflake'],
            ['code' => 'reclining_seats', 'label' => 'Sièges inclinables', 'icon' => 'armchair'],
            ['code' => 'extra_legroom', 'label' => 'Espace supplémentaire pour les jambes', 'icon' => 'expand'],
            ['code' => 'individual_seat', 'label' => 'Siège individuel', 'icon' => 'user'],
            ['code' => 'headrest', 'label' => 'Appuie-tête', 'icon' => 'headphones'],

            ['code' => 'usb_charging', 'label' => 'Prises USB', 'icon' => 'usb'],
            ['code' => 'power_outlet', 'label' => 'Prises électriques', 'icon' => 'plug'],
            ['code' => 'reading_light', 'label' => 'Lampe de lecture individuelle', 'icon' => 'lightbulb'],

            ['code' => 'tv_screen', 'label' => 'Écran TV', 'icon' => 'tv'],
            ['code' => 'entertainment', 'label' => 'Système de divertissement', 'icon' => 'play'],
            ['code' => 'headphones', 'label' => 'Écouteurs fournis', 'icon' => 'headphones'],

            ['code' => 'onboard_toilet', 'label' => 'Toilettes à bord', 'icon' => 'toilet'],
            ['code' => 'onboard_snack', 'label' => 'Collation à bord', 'icon' => 'utensils'],
            ['code' => 'bottled_water', 'label' => 'Eau minérale offerte', 'icon' => 'droplets'],
            ['code' => 'meal_service', 'label' => 'Repas à bord', 'icon' => 'utensils'],

            ['code' => 'priority_luggage', 'label' => 'Bagage prioritaire', 'icon' => 'briefcase'],
            ['code' => 'luggage_storage', 'label' => 'Compartiment à bagages', 'icon' => 'luggage'],
            ['code' => 'extra_luggage', 'label' => 'Bagage supplémentaire', 'icon' => 'suitcase'],

            ['code' => 'seat_belt', 'label' => 'Ceinture de sécurité', 'icon' => 'shield-check'],
            ['code' => 'gps_tracking', 'label' => 'Suivi GPS du bus', 'icon' => 'map-pin'],
            ['code' => 'security_camera', 'label' => 'Caméras de sécurité', 'icon' => 'camera'],

            ['code' => 'wheelchair_accessible', 'label' => 'Accessible aux fauteuils roulants', 'icon' => 'accessibility'],
            ['code' => 'accessible_seat', 'label' => 'Places accessibles', 'icon' => 'accessibility'],

            ['code' => 'priority_boarding', 'label' => 'Embarquement prioritaire', 'icon' => 'log-in'],
            ['code' => 'premium_seat', 'label' => 'Siège Premium', 'icon' => 'star'],
            ['code' => 'blanket', 'label' => 'Couverture disponible', 'icon' => 'bed'],
            ['code' => 'pillow', 'label' => 'Oreiller disponible', 'icon' => 'moon'],
        ];

        foreach ($amenities as $amenity) {
            BusAmenity::query()->firstOrCreate(
                ['code' => $amenity['code']],
                ['uuid' => (string) Str::uuid()] + $amenity + ['status' => 'active'],
            );
        }

        $this->attachAmenitiesToBuses();
    }

    /**
     * Associate a realistic subset of amenities to each seeded bus.
     */
    private function attachAmenitiesToBuses(): void
    {
        $commonAmenities = [
            'air_conditioning',
            'reclining_seats',
            'usb_charging',
            'reading_light',
            'luggage_storage',
            'seat_belt',
            'gps_tracking',
            'security_camera',
            'headrest',
        ];

        $premiumAmenities = [
            'wifi',
            'power_outlet',
            'extra_legroom',
            'onboard_snack',
            'bottled_water',
            'blanket',
            'pillow',
            'premium_seat',
            'tv_screen',
            'entertainment',
            'priority_boarding',
        ];

        $longHaulAmenities = [
            'onboard_toilet',
            'meal_service',
            'individual_seat',
        ];

        $buses = Bus::all();

        foreach ($buses as $bus) {
            $codes = array_merge(
                $commonAmenities,
                $premiumAmenities,
                $longHaulAmenities,
            );

            $amenityIds = BusAmenity::query()
                ->whereIn('code', $codes)
                ->pluck('id');

            $bus->amenities()->sync($amenityIds);
        }
    }
}
