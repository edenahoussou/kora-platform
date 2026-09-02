<?php

namespace Database\Seeders;

use App\Modules\Booking\Models\GroupBookingSetting;
use App\Modules\DynamicPricing\Data\GroupDiscountRuleStatus;
use App\Modules\DynamicPricing\Models\GroupDiscountRule;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class Mez57GroupPurchaseSeeder extends Seeder
{
    public function run(): void
    {
        GroupBookingSetting::query()->firstOrCreate([], [
            'minimum_passengers' => 5,
            'maximum_passengers' => 10,
        ]);

        GroupDiscountRule::query()->firstOrCreate([
            'passenger_threshold' => 10,
            'percentage' => 10,
        ], [
            'uuid' => Str::uuid()->toString(),
            'status' => GroupDiscountRuleStatus::Active,
            'starts_at' => null,
            'ends_at' => null,
        ]);
    }
}
