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
            'line_uuid' => ['sometimes', 'required', 'uuid', Rule::exists(Line::class, 'uuid')->whereNull('deleted_at')],
            'bus_uuid' => ['sometimes', 'required', 'uuid', Rule::exists(Bus::class, 'uuid')->whereNull('deleted_at')],
            'agency_uuid' => ['sometimes', 'required', 'uuid', Rule::exists(Agency::class, 'uuid')->whereNull('deleted_at')],
            'departure_date' => ['sometimes', 'required', 'date'],
            'departure_time' => ['sometimes', 'required', 'date_format:H:i'],
            'driver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'conductor_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $busUuid = $this->filled('bus_uuid') ? $this->string('bus_uuid')->value() : null;
            $agencyUuid = $this->filled('agency_uuid') ? $this->string('agency_uuid')->value() : null;

            if ($busUuid !== null && $agencyUuid !== null) {
                $bus = Bus::query()->where('uuid', $busUuid)->first(['id', 'agency_id']);
                $agency = Agency::query()->where('uuid', $agencyUuid)->first(['id']);
                if ($bus !== null && $agency !== null && $bus->agency_id !== $agency->id) {
                    $validator->errors()->add('bus_uuid', "Le bus doit appartenir à l'agence sélectionnée.");
                }
            }
        });
    }

    /** @return array{line_uuid?: string, bus_uuid?: string, agency_uuid?: string, departure_date?: string, departure_time?: string, driver_uuid?: string|null, conductor_uuid?: string|null} */
    public function payload(): array
    {
        $payload = [];

        if ($this->filled('line_uuid')) {
            $payload['line_uuid'] = $this->string('line_uuid')->value();
        }
        if ($this->filled('bus_uuid')) {
            $payload['bus_uuid'] = $this->string('bus_uuid')->value();
        }
        if ($this->filled('agency_uuid')) {
            $payload['agency_uuid'] = $this->string('agency_uuid')->value();
        }
        if ($this->filled('departure_date')) {
            $payload['departure_date'] = $this->string('departure_date')->value();
        }
        if ($this->filled('departure_time')) {
            $payload['departure_time'] = $this->string('departure_time')->value();
        }
        if ($this->has('driver_uuid')) {
            $payload['driver_uuid'] = $this->filled('driver_uuid') ? $this->string('driver_uuid')->value() : null;
        }
        if ($this->has('conductor_uuid')) {
            $payload['conductor_uuid'] = $this->filled('conductor_uuid') ? $this->string('conductor_uuid')->value() : null;
        }

        return $payload;
    }
}
