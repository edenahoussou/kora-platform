<?php

namespace App\Modules\FleetLite\Http\Requests;

use App\Modules\FleetLite\Data\OdometerReadingKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class RecordOdometerRequest extends FormRequest
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
            'kind' => ['required', Rule::enum(OdometerReadingKind::class)],
            'value_km' => ['required', 'integer', 'min:0', 'max:9999999'],
            'client_uuid' => ['nullable', 'uuid'],
            // Heure locale de l'appareil : pas dans le futur (5 min de
            // tolérance), pas plus vieille que 7 jours (file hors ligne).
            'recorded_at' => ['nullable', 'date', 'before_or_equal:+5 minutes', 'after:-7 days'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'value_km.required' => 'Saisissez le kilométrage affiché au compteur.',
            'value_km.integer' => 'Le kilométrage doit être un nombre entier.',
            'value_km.max' => 'Le kilométrage saisi est trop élevé.',
        ];
    }

    public function kind(): OdometerReadingKind
    {
        return OdometerReadingKind::from($this->string('kind')->toString());
    }

    public function valueKm(): int
    {
        return (int) $this->integer('value_km');
    }

    public function clientUuid(): ?string
    {
        return $this->filled('client_uuid') ? $this->string('client_uuid')->toString() : null;
    }

    public function recordedAt(): ?Carbon
    {
        return $this->filled('recorded_at') ? Carbon::parse($this->string('recorded_at')->toString()) : null;
    }

    /** @return array<string, array{description: string, example?: mixed}> */
    public function bodyParameters(): array
    {
        return [
            'kind' => ['description' => 'start (départ) ou end (arrivée).', 'example' => 'start'],
            'value_km' => ['description' => 'Kilométrage affiché au compteur.', 'example' => 182450],
            'client_uuid' => ['description' => 'UUID de la saisie côté mobile (file hors ligne).'],
            'recorded_at' => ['description' => 'Heure locale du relevé (ISO 8601).'],
        ];
    }
}
