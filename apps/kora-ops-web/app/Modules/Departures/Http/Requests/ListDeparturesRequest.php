<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListDeparturesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date' => ['required', 'date_format:Y-m-d'],
            'line' => ['nullable', 'uuid', 'exists:lines,uuid'],
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
            'origin_city' => ['nullable', 'string', 'max:120'],
            'destination_city' => ['nullable', 'string', 'max:120'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array{date: string, line?: string|null, from?: string|null, to?: string|null, per_page: int}
     */
    public function filters(): array
    {
        return [
            'date' => $this->string('date')->value(),
            'line' => $this->string('line')->trim()->value() ?: null,
            'from' => $this->string('origin_city')->trim()->value()
                ?: ($this->string('from')->trim()->value() ?: null),
            'to' => $this->string('destination_city')->trim()->value()
                ?: ($this->string('to')->trim()->value() ?: null),
            'per_page' => (int) ($this->integer('per_page') ?: 15),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function bodyParameters(): array
    {
        return [];
    }
}
