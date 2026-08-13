<?php

namespace App\Modules\BusNetwork\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListLinesRequest extends FormRequest
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
            'from' => ['nullable', 'string', 'max:120'],
            'to' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array{from?: string|null, to?: string|null}
     */
    public function filters(): array
    {
        return [
            'from' => $this->string('from')->trim()->value() ?: null,
            'to' => $this->string('to')->trim()->value() ?: null,
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
