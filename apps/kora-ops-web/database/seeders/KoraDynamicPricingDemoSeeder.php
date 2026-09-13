<?php

namespace Database\Seeders;

use App\Modules\DynamicPricing\Data\PricingRuleType;
use App\Modules\DynamicPricing\Models\PricingRule;
use App\Modules\DynamicPricing\Models\PricingSetting;
use Illuminate\Database\Seeder;

/**
 * Crée deux règles de tarification dynamique simples pour tester
 * manuellement l'affichage des majorations et réductions dans la
 * vente au guichet et l'API mobile.
 *
 * - Règle 1 : réduction de 15 % quand le bus est peu rempli
 *   (fill_rate < 30 %), pour tout départ à plus de 48 h.
 * - Règle 2 : majoration de 25 % quand le bus est bien rempli
 *   (fill_rate >= 70 %) et le départ dans moins de 24 h.
 *
 * Les deux règles sont génériques (line_id = NULL) donc s'appliquent
 * à toutes les lignes. Lancez avec :
 *
 *   php artisan db:seed --class=KoraDynamicPricingDemoSeeder
 */
class KoraDynamicPricingDemoSeeder extends Seeder
{
    public function run(): void
    {
        PricingSetting::query()->firstOrCreate(
            [],
            [
                'floor_coefficient' => 0.50,
                'ceiling_coefficient' => 2.00,
            ],
        );

        // UUIDs fixes pour garantir l'idempotence du seeder.
        $discountUuid = '11111111-1111-4111-8111-111111111111';
        $surgeUuid = '22222222-2222-4222-8222-222222222222';

        $this->upsertRule(
            uuid: $discountUuid,
            type: PricingRuleType::Decrease,
            percentage: 15.0,
            priority: 20,
            conditions: [
                'fill_rate_max' => 30,
                'hours_before_departure_min' => 48,
            ],
        );

        $this->upsertRule(
            uuid: $surgeUuid,
            type: PricingRuleType::Increase,
            percentage: 25.0,
            priority: 10,
            conditions: [
                'fill_rate_min' => 70,
                'hours_before_departure_max' => 24,
            ],
        );

        $this->command->info('Règles de tarification dynamique de démonstration créées.');
        $this->command->info('  - Réduction 15 % si remplissage < 30 % et départ > 48 h');
        $this->command->info('  - Majoration 25 % si remplissage >= 70 % et départ < 24 h');
    }

    /**
     * Crée ou met à jour une règle de tarification par UUID.
     *
     * @param  array<string, mixed>  $conditions
     */
    private function upsertRule(
        string $uuid,
        PricingRuleType $type,
        float $percentage,
        int $priority,
        array $conditions,
    ): void {
        $existing = PricingRule::query()->where('uuid', $uuid)->withTrashed()->first();

        if ($existing !== null) {
            $existing->forceFill([
                'line_id' => null,
                'type' => $type->value,
                'percentage' => $percentage,
                'priority' => $priority,
                'conditions' => $conditions,
                'status' => 'active',
                'starts_at' => now(),
                'ends_at' => null,
            ])->save();

            if ($existing->trashed()) {
                $existing->restore();
            }

            return;
        }

        PricingRule::query()->create([
            'uuid' => $uuid,
            'line_id' => null,
            'type' => $type->value,
            'percentage' => $percentage,
            'priority' => $priority,
            'conditions' => $conditions,
            'status' => 'active',
            'starts_at' => now(),
        ]);
    }
}
