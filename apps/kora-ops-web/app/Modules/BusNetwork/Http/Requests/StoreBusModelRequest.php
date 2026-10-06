<?php

namespace App\Modules\BusNetwork\Http\Requests;

use App\Modules\BusNetwork\Data\BusModelStatus;
use App\Modules\BusNetwork\Models\BusModel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBusModelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', BusModel::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'brand' => ['required', 'string', 'max:120'],
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('bus_models', 'name')->where('brand', $this->string('brand')->trim()->value()),
            ],
            'default_seat_count' => ['required', 'integer', 'min:1', 'max:200'],
            'status' => ['required', Rule::enum(BusModelStatus::class)],
        ];
    }

    /** @return array{brand: string, name: string, default_seat_count: int, status: string} */
    public function payload(): array
    {
        return [
            'brand' => $this->string('brand')->trim()->value(),
            'name' => $this->string('name')->trim()->value(),
            'default_seat_count' => $this->integer('default_seat_count'),
            'status' => $this->string('status')->trim()->value(),
        ];
    }
}
