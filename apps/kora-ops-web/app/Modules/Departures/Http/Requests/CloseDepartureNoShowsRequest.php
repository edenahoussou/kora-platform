<?php

namespace App\Modules\Departures\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Clôture no-show du départ depuis la fiche OPS Web : tout billet encore
 * "ticketed" devient no-show et libère tout son trajet. Sans terminal
 * P501 — `terminal_p501_id` reste null sur les événements générés.
 */
class CloseDepartureNoShowsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('departures.manage') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
