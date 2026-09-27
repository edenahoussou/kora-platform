<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RecordOpsDelayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Les bornes métier (durée maximale, statut du départ) sont vérifiées par
     * RecordDepartureDelayAction, la même règle que sur OPS Web.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'delay_minutes' => ['required', 'integer'],
            'delay_reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function delayMinutes(): int
    {
        return $this->integer('delay_minutes');
    }

    public function delayReason(): string
    {
        return $this->string('delay_reason')->toString();
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'delay_minutes' => ['description' => 'Retard constaté en minutes.'],
            'delay_reason' => ['description' => 'Motif du retard.'],
        ];
    }
}
