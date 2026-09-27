<?php

namespace App\Modules\Boarding\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReverseBoardingEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('boardingEvent')) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function reason(): string
    {
        return $this->string('reason')->trim()->value();
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'reason' => ['description' => 'Motif de l’action.'],
        ];
    }
}
