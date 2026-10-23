<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\SeatStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSeatStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('bus')) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(SeatStatus::class)],
            'reason' => [
                Rule::requiredIf(fn (): bool => $this->input('status') !== SeatStatus::Active->value),
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['reason.required' => 'Le motif est requis lorsque le siège n’est pas remis en service.'];
    }

    public function seatStatus(): SeatStatus
    {
        return SeatStatus::from($this->string('status')->trim()->value());
    }

    public function reason(): ?string
    {
        return $this->filled('reason') ? $this->string('reason')->trim()->value() : null;
    }
}
