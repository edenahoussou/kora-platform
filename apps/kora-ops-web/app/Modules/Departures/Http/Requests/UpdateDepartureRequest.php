<?php

namespace App\Modules\Departures\Http\Requests;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        $departure = $this->route('departure');

        return $this->user()?->can('update', $departure instanceof Departure ? $departure : new Departure) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'line_id' => ['sometimes', 'required', 'integer', Rule::exists(Line::class, 'id')->whereNull('deleted_at')],
            'bus_id' => ['sometimes', 'required', 'integer', Rule::exists(Bus::class, 'id')->whereNull('deleted_at')],
            'agency_id' => ['sometimes', 'required', 'integer', Rule::exists(Agency::class, 'id')->whereNull('deleted_at')],
            'departure_date' => ['sometimes', 'required', 'date'],
            'departure_time' => ['sometimes', 'required', 'date_format:H:i'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'conductor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $busId = $this->filled('bus_id') ? $this->integer('bus_id') : null;
            $agencyId = $this->filled('agency_id') ? $this->integer('agency_id') : null;

            if ($busId !== null && $agencyId !== null) {
                $bus = Bus::query()->find($busId);
                if ($bus !== null && $bus->agency_id !== $agencyId) {
                    $validator->errors()->add('bus_id', 'Le bus doit appartenir à l’agence sélectionnée.');
                }
            }
        });
    }

    /** @return array{line_id?: int, bus_id?: int, agency_id?: int, departure_date?: string, departure_time?: string, driver_id?: int|null, conductor_id?: int|null} */
    public function payload(): array
    {
        $payload = [];

        if ($this->filled('line_id')) {
            $payload['line_id'] = $this->integer('line_id');
        }
        if ($this->filled('bus_id')) {
            $payload['bus_id'] = $this->integer('bus_id');
        }
        if ($this->filled('agency_id')) {
            $payload['agency_id'] = $this->integer('agency_id');
        }
        if ($this->filled('departure_date')) {
            $payload['departure_date'] = $this->string('departure_date')->value();
        }
        if ($this->filled('departure_time')) {
            $payload['departure_time'] = $this->string('departure_time')->value();
        }
        if ($this->has('driver_id')) {
            $payload['driver_id'] = $this->filled('driver_id') ? $this->integer('driver_id') : null;
        }
        if ($this->has('conductor_id')) {
            $payload['conductor_id'] = $this->filled('conductor_id') ? $this->integer('conductor_id') : null;
        }

        return $payload;
    }
}
