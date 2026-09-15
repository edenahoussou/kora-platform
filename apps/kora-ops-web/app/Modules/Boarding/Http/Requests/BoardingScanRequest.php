<?php

namespace App\Modules\Boarding\Http\Requests;

use App\Modules\Departures\Models\Departure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class BoardingScanRequest extends FormRequest
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
            'qr_payload' => ['nullable', 'string', 'required_without:short_code'],
            'short_code' => ['nullable', 'string', 'required_without:qr_payload'],
            'operation_uuid' => ['required', 'uuid'],
            'local_recorded_at' => ['required', 'date'],
        ];
    }

    public function departure(): Departure
    {
        return Departure::query()->where('uuid', $this->string('departure_uuid')->value())->firstOrFail();
    }

    public function qrPayload(): ?string
    {
        return $this->filled('qr_payload') ? $this->string('qr_payload')->value() : null;
    }

    public function shortCode(): ?string
    {
        return $this->filled('short_code') ? $this->string('short_code')->trim()->upper()->value() : null;
    }

    public function operationUuid(): string
    {
        return $this->string('operation_uuid')->value();
    }

    public function localRecordedAt(): Carbon
    {
        return Carbon::parse($this->input('local_recorded_at'));
    }
}
