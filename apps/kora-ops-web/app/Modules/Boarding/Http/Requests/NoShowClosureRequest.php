<?php

namespace App\Modules\Boarding\Http\Requests;

use App\Modules\Departures\Models\Departure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class NoShowClosureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'departure_uuid' => ['required', 'string', Rule::exists((new Departure)->getTable(), 'uuid')],
            'operation_uuid' => ['required', 'uuid'],
            'local_recorded_at' => ['required', 'date'],
        ];
    }

    public function departure(): Departure
    {
        return Departure::query()->where('uuid', $this->string('departure_uuid')->value())->firstOrFail();
    }

    public function operationUuid(): string
    {
        return $this->string('operation_uuid')->value();
    }

    public function localRecordedAt(): Carbon
    {
        return Carbon::parse($this->input('local_recorded_at'));
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'departure_uuid' => ['description' => 'UUID du départ concerné.'],
            'operation_uuid' => ['description' => 'UUID idempotent de l’opération cliente.'],
            'local_recorded_at' => ['description' => 'Date et heure locales de l’enregistrement.'],
        ];
    }
}
