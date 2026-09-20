<?php

namespace App\Modules\Departures\Http\Requests;

use App\Modules\Departures\Data\DepartureStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class ProgressDepartureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Le convoyeur ne peut que faire avancer le départ : jamais l'annuler ni
     * revenir en arrière (cela reste `departures.manage` sur OPS Web).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                DepartureStatus::Boarding->value,
                DepartureStatus::Departed->value,
                DepartureStatus::Arrived->value,
            ])],
            'occurred_at' => ['nullable', 'date', 'before_or_equal:+5 minutes', 'after:-3 days'],
        ];
    }

    public function target(): DepartureStatus
    {
        return DepartureStatus::from($this->string('status')->toString());
    }

    public function occurredAt(): ?Carbon
    {
        return $this->filled('occurred_at') ? Carbon::parse($this->string('occurred_at')->toString()) : null;
    }
}
