<?php

namespace App\Modules\Parcels\Services;

use App\Modules\DynamicPricing\Data\TaxRuleStatus;
use App\Modules\DynamicPricing\Data\TaxServiceType;
use App\Modules\DynamicPricing\Models\TaxRule;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\Parcels\Models\ParcelCorridor;
use App\Modules\Parcels\Models\ParcelTariff;
use App\Modules\Settings\Settings\GeneralSettings;
use Illuminate\Validation\ValidationException;

class ParcelPricing
{
    public function __construct(private readonly GeneralSettings $generalSettings) {}

    public function ensureOpen(ParcelCorridor $corridor): void
    {
        $corridor->loadMissing(['originHub.agency', 'destinationHub.agency']);

        abort_unless($corridor->enabled
            && $corridor->originHub?->enabled && $corridor->originHub->archived_at === null && $corridor->originHub->agency?->status === AgencyStatus::Active
            && $corridor->destinationHub?->enabled && $corridor->destinationHub->archived_at === null && $corridor->destinationHub->agency?->status === AgencyStatus::Active, 409, 'Parcel corridor is unavailable.');
    }

    /**
     * @return array{
     *     tariff_id:int, tariff_uuid:string, tariff_version:int, deposit_kind:string,
     *     tax_rule_id:int, weight_grams:int, currency:string, maximum_grams:int,
     *     base_amount:int, step_amount:int, step_grams:int, extra_steps:int,
     *     tiers:list<array{kind:string, from_grams:int, to_grams:int, amount:int}>,
     *     total:int, net:int, tax:int, tax_code:string, tax_rate:string, fiscal_group:string
     * }
     */
    public function calculate(ParcelCorridor $corridor, int $grams, bool $return = false): array
    {
        $corridor->loadMissing(['originHub.agency', 'destinationHub.agency']);
        $depositKind = (string) $corridor->originHub?->kind;
        abort_if($depositKind === '', 409, 'The parcel deposit location kind is incomplete.');

        $tariff = $corridor->tariffs()
            ->where('deposit_kind', $depositKind)
            ->latest('version')
            ->latest('id')
            ->first();
        abort_unless($tariff instanceof ParcelTariff, 409, 'No tariff grid is published for this deposit location kind.');

        $maximum = (int) $tariff->maximum_grams;

        if ($grams < 1 || (! $return && $grams > $maximum)) {
            throw ValidationException::withMessages([
                'weight_grams' => "The weight must be between 1 g and {$maximum} g for this tariff grid.",
            ]);
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

        $stepGrams = (int) $tariff->step_grams;
        $stepAmount = (int) $tariff->step_amount;
        $baseAmount = (int) $tariff->base_amount;
        $extraSteps = $return ? 0 : intdiv(max(0, $grams - 1), $stepGrams);
        $total = $return ? (int) $tariff->return_amount : $baseAmount + $extraSteps * $stepAmount;

        if ($return) {
            $tiers = [[
                'kind' => 'return',
                'from_grams' => 1,
                'to_grams' => $maximum,
                'amount' => (int) $tariff->return_amount,
            ]];
        } else {
            $tiers = [[
                'kind' => 'base',
                'from_grams' => 1,
                'to_grams' => $stepGrams,
                'amount' => $baseAmount,
            ]];

            for ($index = 1; $index <= $extraSteps; $index++) {
                $tiers[] = [
                    'kind' => 'step',
                    'from_grams' => ($index - 1) * $stepGrams + $stepGrams + 1,
                    'to_grams' => min($index * $stepGrams + $stepGrams, $maximum),
                    'amount' => $stepAmount,
                ];
            }
        }

        $net = (int) round($total / (1 + ((float) $rule->tax_rate / 100)));

        return [
            'tariff_id' => (int) $tariff->id,
            'tariff_uuid' => (string) $tariff->uuid,
            'tariff_version' => (int) $tariff->version,
            'deposit_kind' => $depositKind,
            'tax_rule_id' => (int) $rule->id,
            'weight_grams' => $grams,
            'currency' => $this->generalSettings->currency,
            'maximum_grams' => $maximum,
            'base_amount' => $baseAmount,
            'step_amount' => $stepAmount,
            'step_grams' => $stepGrams,
            'extra_steps' => $extraSteps,
            'tiers' => $tiers,
            'total' => $total,
            'net' => $net,
            'tax' => $total - $net,
            'tax_code' => $rule->tax_code,
            'tax_rate' => (string) $rule->tax_rate,
            'fiscal_group' => $group,
        ];
    }

    /**
     * Remove persistence-only identifiers before exposing a quote.
     *
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    public function present(array $quote): array
    {
        unset($quote['tariff_id'], $quote['tax_rule_id']);
        $quote['estimated'] = true;

        return $quote;
    }
}
