<?php

namespace App\Modules\Departures\Http\Requests;

use App\Models\User;
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
            'driver_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'conductor_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $busUuid = $this->filled('bus_uuid') ? $this->string('bus_uuid')->trim()->value() : null;
            $agencyUuid = $this->filled('agency_uuid') ? $this->string('agency_uuid')->trim()->value() : null;

            if ($busUuid !== null && $agencyUuid !== null) {
                $bus = Bus::query()->where('uuid', $busUuid)->first();
                $agency = Agency::query()->where('uuid', $agencyUuid)->first();
                if ($bus !== null && $agency !== null && $bus->agency_id !== $agency->id) {
                    $validator->errors()->add('bus_uuid', 'Le bus doit appartenir à l’agence sélectionnée.');
                }
            }
        });
    }

    /** @return array{line_id?: int, bus_id?: int, agency_id?: int, departure_date?: string, departure_time?: string, driver_id?: int|null, conductor_id?: int|null} */
    public function payload(): array
    {
        $payload = [];

        if ($this->filled('line_uuid')) {
            $payload['line_id'] = (int) Line::query()->where('uuid', $this->string('line_uuid')->value())->value('id');
        }
        if ($this->filled('bus_uuid')) {
            $payload['bus_id'] = (int) Bus::query()->where('uuid', $this->string('bus_uuid')->value())->value('id');
        }
        if ($this->filled('agency_uuid')) {
            $payload['agency_id'] = (int) Agency::query()->where('uuid', $this->string('agency_uuid')->value())->value('id');
        }
        if ($this->filled('departure_date')) {
            $payload['departure_date'] = $this->string('departure_date')->value();
        }
        if ($this->filled('departure_time')) {
            $payload['departure_time'] = $this->string('departure_time')->value();
        }
        if ($this->has('driver_uuid')) {
            $payload['driver_id'] = $this->filled('driver_uuid')
                ? User::query()->where('uuid', $this->string('driver_uuid')->value())->value('id')
                : null;
        }
        if ($this->has('conductor_uuid')) {
            $payload['conductor_id'] = $this->filled('conductor_uuid')
                ? User::query()->where('uuid', $this->string('conductor_uuid')->value())->value('id')
                : null;
        }

        return $payload;
    }
}
