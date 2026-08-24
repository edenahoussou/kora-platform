<?php

namespace App\Modules\Departures\Http\Requests;

use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Departure::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'line_uuid' => ['required', 'uuid', Rule::exists(Line::class, 'uuid')->whereNull('deleted_at')],
            'bus_uuid' => ['required', 'uuid', Rule::exists(Bus::class, 'uuid')->whereNull('deleted_at')],
            'agency_uuid' => ['required', 'uuid', Rule::exists(Agency::class, 'uuid')->whereNull('deleted_at')],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_time' => ['required', 'date_format:H:i'],
            'driver_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
            'conductor_uuid' => ['nullable', 'uuid', 'exists:users,uuid'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $busUuid = $this->string('bus_uuid')->value();
            $agencyUuid = $this->string('agency_uuid')->value();

            if ($busUuid !== '' && $agencyUuid !== '') {
                $bus = Bus::query()->where('uuid', $busUuid)->first(['id', 'agency_id']);
                $agency = Agency::query()->where('uuid', $agencyUuid)->first(['id']);
                if ($bus !== null && $agency !== null && $bus->agency_id !== $agency->id) {
                    $validator->errors()->add('bus_uuid', "Le bus doit appartenir à l'agence sélectionnée.");
                }
            }
        });
    }

    /** @return array{line_uuid: string, bus_uuid: string, agency_uuid: string, departure_date: string, departure_time: string, driver_uuid: string|null, conductor_uuid: string|null, created_by: int|null} */
    public function payload(): array
    {
        return [
            'line_uuid' => $this->string('line_uuid')->value(),
            'bus_uuid' => $this->string('bus_uuid')->value(),
            'agency_uuid' => $this->string('agency_uuid')->value(),
            'departure_date' => $this->string('departure_date')->value(),
            'departure_time' => $this->string('departure_time')->value(),
            'driver_uuid' => $this->filled('driver_uuid') ? $this->string('driver_uuid')->value() : null,
            'conductor_uuid' => $this->filled('conductor_uuid') ? $this->string('conductor_uuid')->value() : null,
            'created_by' => $this->user()?->id,
        ];
    }
}
