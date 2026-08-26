<?php

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Models\CashMovement;
use Illuminate\Foundation\Http\FormRequest;

class ApproveCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $movement = $this->route('movement');

        return $movement instanceof CashMovement
            && $this->user()->can('cash.transfer.approve')
            && $movement->cashSession?->agency_id === $this->user()->agency_id;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
