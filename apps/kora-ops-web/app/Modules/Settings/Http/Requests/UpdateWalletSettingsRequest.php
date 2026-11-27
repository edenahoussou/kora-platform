<?php

namespace App\Modules\Settings\Http\Requests;

use App\Modules\Settings\Data\SettingsGroup;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletLoyaltyRuleSet;
use Illuminate\Support\Facades\Gate;

final class UpdateWalletSettingsRequest extends SettingsUpdateRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', WalletSettings::class);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        $editableFields = [
            'walletCashEnabled',
            'walletPointsEnabled',
            'walletHoldTtlMinutes',
            'topupMinAmount',
            'topupMaxAmount',
            'cashBalanceCap',
            'topupAmountPresets',
            'topupInitiationTtlMinutes',
        ];
        $rules = parent::rules();
        $rules['values'] = ['sometimes', 'array:'.implode(',', $editableFields)];
        $rules['values.walletCashEnabled'] = ['sometimes', 'boolean'];
        $rules['values.walletPointsEnabled'] = ['sometimes', 'boolean'];
        $rules['values.walletHoldTtlMinutes'] = ['sometimes', 'integer', 'between:1,1440'];
        $rules['values.topupMinAmount'] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'];
        $rules['values.topupMaxAmount'] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'];
        $rules['values.cashBalanceCap'] = ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000'];
        $rules['values.topupInitiationTtlMinutes'] = ['sometimes', 'integer', 'between:1,1440'];
        $rules['values.topupAmountPresets'] = ['sometimes', 'array', 'min:1', 'max:8'];
        $rules['values.topupAmountPresets.*'] = ['required', 'integer', 'between:1,100000000'];

        foreach (WalletLoyaltyRuleSet::FIELDS as $field) {
            $rules['values.'.$field] = ['prohibited'];
        }

        return $rules;
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['values.*.prohibited' => 'Le champ « :attribute » se modifie dans Wallet → Règles de fidélité.'];
    }

    /**
     * Groupe de paramètres validé par cette requête.
     *
     * @return SettingsGroup Groupe Wallet
     */
    protected function settingsGroup(): SettingsGroup
    {
        return SettingsGroup::Wallet;
    }
}
