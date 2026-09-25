<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Descente anticipée depuis la fiche départ OPS Web : libère la portion
 * non parcourue du billet à partir de l'arrêt réel choisi. Même métier
 * que ProcessSeatReleaseAction côté P501, sans terminal.
 */
class RecordEarlyExitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departures.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ticket_uuid' => ['required', 'uuid'],
            'stop_uuid' => ['required', 'uuid'],
        ];
    }

    /** @return array{ticket_uuid: string, stop_uuid: string} */
    public function payload(): array
    {
        return [
            'ticket_uuid' => $this->string('ticket_uuid')->trim()->value(),
            'stop_uuid' => $this->string('stop_uuid')->trim()->value(),
        ];
    }
}
