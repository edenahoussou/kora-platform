<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListDepartureSeatsRequest extends FormRequest
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
            'origin_stop_uuid' => ['required', 'uuid'],
            'destination_stop_uuid' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array{origin_stop_uuid: string, destination_stop_uuid: string}
     */
    public function segmentFilters(): array
    {
        return [
            'origin_stop_uuid' => $this->string('origin_stop_uuid')->value(),
            'destination_stop_uuid' => $this->string('destination_stop_uuid')->value(),
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
