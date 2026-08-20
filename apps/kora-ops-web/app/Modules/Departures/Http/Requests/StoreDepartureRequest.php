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
            'line_id' => ['required', 'integer', Rule::exists(Line::class, 'id')->whereNull('deleted_at')],
            'bus_id' => ['required', 'integer', Rule::exists(Bus::class, 'id')->whereNull('deleted_at')],
            'agency_id' => ['required', 'integer', Rule::exists(Agency::class, 'id')->whereNull('deleted_at')],
            'departure_date' => ['required', 'date', 'after_or_equal:today'],
            'departure_time' => ['required', 'date_format:H:i'],
            'driver_id' => ['nullable', 'integer', 'exists:users,id'],
            'conductor_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $busId = $this->integer('bus_id');
            $agencyId = $this->integer('agency_id');

            if ($busId > 0 && $agencyId > 0) {
                $bus = Bus::query()->find($busId);
                if ($bus !== null && $bus->agency_id !== $agencyId) {
                    $validator->errors()->add('bus_id', 'Le bus doit appartenir à l’agence sélectionnée.');
                }
            }
        });
    }

    /** @return array{line_id: int, bus_id: int, agency_id: int, departure_date: string, departure_time: string, driver_id: int|null, conductor_id: int|null, created_by: int|null} */
    public function payload(): array
    {
        return [
            'line_id' => $this->integer('line_id'),
            'bus_id' => $this->integer('bus_id'),
            'agency_id' => $this->integer('agency_id'),
            'departure_date' => $this->string('departure_date')->value(),
            'departure_time' => $this->string('departure_time')->value(),
            'driver_id' => $this->filled('driver_id') ? $this->integer('driver_id') : null,
            'conductor_id' => $this->filled('conductor_id') ? $this->integer('conductor_id') : null,
            'created_by' => $this->user()?->id,
        ];
    }
}
