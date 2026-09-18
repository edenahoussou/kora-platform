<?php

namespace App\Modules\DynamicPricing\Http\Requests;

use App\Modules\DynamicPricing\Data\PromotionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChangePromotionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PromotionStatus::class)],
        ];
    }

    public function status(): PromotionStatus
    {
        return PromotionStatus::from($this->string('status')->value());
    }
}
