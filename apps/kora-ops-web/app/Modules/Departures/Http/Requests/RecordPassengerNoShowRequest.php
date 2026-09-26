<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Signalement d'un no-show depuis la fiche départ OPS Web : libère tous
 * les tronçons du billet jamais embarqué. Même métier que
 * ProcessIndividualNoShowAction côté P501, sans terminal.
 */
class RecordPassengerNoShowRequest extends FormRequest
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
        ];
    }

    /** @return array{ticket_uuid: string} */
    public function payload(): array
    {
        return [
            'ticket_uuid' => $this->string('ticket_uuid')->trim()->value(),
        ];
    }
}
