<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Annulation exceptionnelle d'un embarquement depuis la fiche départ
 * (erreur de scan P501, correction opérationnelle). Même métier que
 * ReverseBoardingEventAction côté API : motif obligatoire, audit
 * immuable, l'événement d'origine n'est jamais modifié.
 */
class ReverseDepartureBoardingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('boarding.reverse') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'ticket_uuid' => ['required', 'uuid'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array{ticket_uuid: string, reason: string} */
    public function payload(): array
    {
        return [
            'ticket_uuid' => $this->string('ticket_uuid')->trim()->value(),
            'reason' => $this->string('reason')->trim()->value(),
        ];
    }
}
