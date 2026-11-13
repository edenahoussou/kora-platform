<?php

namespace App\Modules\Parcels\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
                'corridor_uuid' => ['required', 'uuid'],
                'weight_grams' => ['required', 'integer', 'min:1', 'max:1000000'],
                'sender_name' => ['required', 'string', 'max:120'],
                'sender_phone' => ['nullable', 'string', 'max:32'],
                'recipient_name' => ['required', 'string', 'max:120'],
                'recipient_phone' => ['required', 'string', 'max:32'],
                'contents' => ['required', 'string', 'max:500'],
            ],
            'inspect' => [
                'weight_grams' => ['required_unless:category,refused', 'integer', 'min:1', 'max:1000000'],
                'category' => ['required', 'in:allowed,cash,flammable,chemical,undeclared_medicine,weapon,refused'],
                'cash_value' => ['required_if:category,cash', 'integer', 'min:0'],
                'reason' => ['nullable', 'string', 'max:500'],
            ],
            default => [],
        };
    }
}
