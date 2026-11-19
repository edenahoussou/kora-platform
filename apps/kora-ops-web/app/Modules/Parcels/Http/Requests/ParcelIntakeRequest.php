<?php

namespace App\Modules\Parcels\Http\Requests;

use App\Modules\Parcels\Data\ParcelInspectionCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ParcelIntakeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return match ($this->route()?->getActionMethod()) {
            'quote' => [
                'corridor_uuid' => ['required', 'uuid'],
                'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
            ],
            'store' => [
                'corridor_uuid' => ['nullable', 'uuid'],
                'deposit_hub_uuid' => ['required_without:corridor_uuid', 'uuid'],
                'destination_hub_uuid' => ['required_without:corridor_uuid', 'uuid'],
                'idempotency_key' => ['required', 'uuid'],
                'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
                'sender_name' => ['required', 'string', 'max:120'],
                'sender_phone' => ['nullable', 'string', 'max:32'],
                'recipient_name' => ['required', 'string', 'max:120'],
                'recipient_phone' => ['required', 'string', 'max:32'],
                'contents' => ['required', 'string', 'max:500'],
            ],
            'inspect' => [
                'weight_grams' => [
                    Rule::requiredIf(fn (): bool => ! in_array($this->input('category'), [
                        ParcelInspectionCategory::Flammable->value,
                        ParcelInspectionCategory::Chemical->value,
                        ParcelInspectionCategory::UndeclaredMedicine->value,
                        ParcelInspectionCategory::Weapon->value,
                        ParcelInspectionCategory::Refused->value,
                    ], true)),
                    'nullable', 'integer', 'min:1', 'max:1000000',
                ],
                'category' => ['required', Rule::enum(ParcelInspectionCategory::class)],
                'cash_value' => ['required_if:category,cash', 'nullable', 'integer', 'min:0', 'max:1000000000'],
                'reason' => ['nullable', 'string', 'min:5', 'max:500'],
            ],
            default => [],
        };
    }
}
