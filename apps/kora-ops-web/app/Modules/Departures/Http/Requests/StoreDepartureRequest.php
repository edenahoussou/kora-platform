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
            'driver_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
            'conductor_uuid' => ['nullable', 'uuid', Rule::exists(User::class, 'uuid')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $busUuid = $this->string('bus_uuid')->trim()->value();
            $agencyUuid = $this->string('agency_uuid')->trim()->value();

            if ($busUuid !== '' && $agencyUuid !== '') {
                $bus = Bus::query()->where('uuid', $busUuid)->first();
                $agency = Agency::query()->where('uuid', $agencyUuid)->first();
                if ($bus !== null && $agency !== null && $bus->agency_id !== $agency->id) {
                    $validator->errors()->add('bus_uuid', 'Le bus doit appartenir à l’agence sélectionnée.');
                }
            }
        });
    }

    /** @return array{line_id: int, bus_id: int, agency_id: int, departure_date: string, departure_time: string, driver_id: int|null, conductor_id: int|null, created_by: int|null} */
    public function payload(): array
    {
        return [
            'line_id' => (int) Line::query()->where('uuid', $this->string('line_uuid')->value())->value('id'),
            'bus_id' => (int) Bus::query()->where('uuid', $this->string('bus_uuid')->value())->value('id'),
            'agency_id' => (int) Agency::query()->where('uuid', $this->string('agency_uuid')->value())->value('id'),
            'departure_date' => $this->string('departure_date')->value(),
            'departure_time' => $this->string('departure_time')->value(),
            'driver_id' => $this->filled('driver_uuid')
                ? User::query()->where('uuid', $this->string('driver_uuid')->value())->value('id')
                : null,
            'conductor_id' => $this->filled('conductor_uuid')
                ? User::query()->where('uuid', $this->string('conductor_uuid')->value())->value('id')
                : null,
            'created_by' => $this->user()?->id,
        ];
    }
}
