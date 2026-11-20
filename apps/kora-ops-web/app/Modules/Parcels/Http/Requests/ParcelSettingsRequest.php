<?php

namespace App\Modules\Parcels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ParcelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'parcelIntakeExpiryHours' => ['required', 'integer', 'between:1,720'],
            'parcelMinimumWeightGrams' => ['required', 'integer', 'between:1,30000'],
            'parcelMaximumWeightGrams' => ['required', 'integer', 'between:1,30000', 'gt:parcelMinimumWeightGrams'],
            'parcelCashDeclarationThresholdFcfa' => ['required', 'integer', 'between:0,1000000000'],
            'parcelFirstReminderDays' => ['required', 'integer', 'min:1', 'max:365'],
            'parcelSecondReminderDays' => ['required', 'integer', 'gt:parcelFirstReminderDays', 'max:365'],
            'parcelReturnDays' => ['required', 'integer', 'gt:parcelSecondReminderDays', 'max:365'],
            'parcelHandoffMaxAttempts' => ['required', 'integer', 'between:1,20'],
            'parcelFiscalAlertHours' => ['required', 'integer', 'between:1,23'],
            'parcelRoutingHorizonDays' => ['required', 'integer', 'between:1,365'],
            'parcelIdentityOverrideEnabled' => ['required', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'parcelSecondReminderDays.gt' => 'Le second rappel doit être postérieur au premier rappel.',
            'parcelReturnDays.gt' => 'Le délai de retour doit être postérieur au second rappel.',
            'parcelMaximumWeightGrams.gt' => 'Le poids maximum doit être supérieur au poids minimum.',
        ];
    }
}
