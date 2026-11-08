<?php

namespace App\Modules\Wallet\Http\Requests;

use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use Illuminate\Foundation\Http\FormRequest;

final class WalletLoyaltyRuleVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'values' => ['required', 'array:'.implode(',', WalletLoyaltyRuleSet::FIELDS)],
            'values.loyaltyEarnRateBasisPoints' => ['required', 'integer', 'between:0,10000'],
            'values.pointValueFcfa' => ['required', 'integer', 'between:1,100000'],
            'values.pointsMinSpend' => ['required', 'integer', 'between:1,1000000'],
            'values.bonusWelcomePoints' => ['required', 'integer', 'between:0,1000000'],
            'values.bonusReferralPoints' => ['required', 'integer', 'between:0,1000000'],
            'values.bonusAnniversaryPoints' => ['required', 'integer', 'between:0,1000000'],
            'values.bonusUsageNth' => ['required', 'integer', 'between:1,1000'],
            'values.bonusUsagePoints' => ['required', 'integer', 'between:0,1000000'],
            'values.pointsExpiryMonths' => ['required', 'integer', 'between:1,120'],
            'values.pointsExpiryReminderDays' => ['required', 'array', 'min:1', 'max:5'],
            'values.pointsExpiryReminderDays.*' => ['required', 'integer', 'between:1,365', 'distinct'],
        ];
    }

    /** @return array<string, mixed> */
    public function values(): array
    {
        return (array) $this->validated('values');
    }
}
