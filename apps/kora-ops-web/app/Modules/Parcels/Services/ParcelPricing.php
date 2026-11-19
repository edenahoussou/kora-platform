<?php

namespace App\Modules\Parcels\Services;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Data\TaxServiceType;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelTariff;
use Illuminate\Validation\ValidationException;

class ParcelPricing
{
    public function ensureOpen(ParcelCorridor $corridor): void
    {
        $corridor->loadMissing(['originHub.agency', 'destinationHub.agency']);

        abort_unless($corridor->enabled
            && $corridor->originHub?->enabled && $corridor->originHub->archived_at === null && $corridor->originHub->agency?->status === AgencyStatus::Active
            && $corridor->destinationHub?->enabled && $corridor->destinationHub->archived_at === null && $corridor->destinationHub->agency?->status === AgencyStatus::Active, 409, 'Parcel corridor is unavailable.');
    }

    /** @return array{tariff_id: int, tax_rule_id: int, total: int, net: int, tax: int, tax_code: string, tax_rate: string, fiscal_group: string} */
    public function calculate(ParcelCorridor $corridor, int $grams, bool $return = false): array
    {
        $tariff = $corridor->tariffs()->latest('id')->first();
        abort_unless($tariff instanceof ParcelTariff, 409, 'Parcel tariff is unavailable.');
        if ($grams < 1 || (! $return && $grams > $tariff->maximum_grams)) {
            throw ValidationException::withMessages(['weight_grams' => 'The weight exceeds the parcel tariff limits.']);
        }

        $country = strtoupper((string) $corridor->originHub?->agency?->country);
        $today = now()->toDateString();
        $rules = TaxRule::query()
            ->where('service_type', TaxServiceType::Parcel)
            ->where('status', TaxRuleStatus::Active)
            ->whereRaw('UPPER(scope) = ?', [$country])
            ->whereDate('starts_on', '<=', $today)
            ->where(fn ($query) => $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', $today))
            ->limit(2)->get();
        abort_unless($rules->count() === 1, 409, 'Configure exactly one active parcel tax rule for the collection country.');
        $rule = $rules->firstOrFail();

        $group = match ((float) $rule->tax_rate) {
            0.0 => 'A',
            18.0 => 'B',
            default => abort(409, 'The parcel tax rate does not match a supported tax group.'),
        };

        $steps = intdiv(max(0, $grams - 1), (int) $tariff->step_grams);
        $total = $return ? (int) $tariff->return_amount : (int) ($tariff->base_amount + $steps * $tariff->step_amount);
        $net = (int) round($total / (1 + ((float) $rule->tax_rate / 100)));

        return [
            'tariff_id' => (int) $tariff->id,
            'tax_rule_id' => (int) $rule->id,
            'total' => $total,
            'net' => $net,
            'tax' => $total - $net,
            'tax_code' => $rule->tax_code,
            'tax_rate' => (string) $rule->tax_rate,
            'fiscal_group' => $group,
        ];
    }
}
