<?php

namespace App\Modules\FleetLite\Http\Requests;

use App\Modules\FleetLite\Data\IncidentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class StoreIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Le départ n'est volontairement PAS validé par une règle `exists` : un
     * départ inconnu et un départ non affecté reçoivent la même réponse
     * (403 `departure_not_assigned`), l'existence d'un départ d'autrui n'est
     * jamais révélée.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'incident_uuid' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(IncidentType::class)],
            'description' => ['required', 'string', 'min:5', 'max:1000'],
            'departure_uuid' => ['required', 'uuid'],
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:-180,180'],
            // Heure locale du terminal : pas dans le futur (5 min de tolérance
            // d'horloge), pas plus vieille que 7 jours (file hors ligne).
            'occurred_at' => ['nullable', 'date', 'before_or_equal:+5 minutes', 'after:-7 days'],
        ];
    }

    public function incidentUuid(): string
    {
        return $this->string('incident_uuid')->toString();
    }

    public function incidentType(): IncidentType
    {
        return IncidentType::from($this->string('type')->toString());
    }

    public function departureUuid(): string
    {
        return $this->string('departure_uuid')->toString();
    }

    public function latitude(): ?float
    {
        return $this->filled('latitude') ? (float) $this->input('latitude') : null;
    }

    public function longitude(): ?float
    {
        return $this->filled('longitude') ? (float) $this->input('longitude') : null;
    }

    public function occurredAt(): ?Carbon
    {
        return $this->filled('occurred_at') ? Carbon::parse($this->string('occurred_at')->toString()) : null;
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'incident_uuid' => ['description' => 'UUID de l’incident côté client.'],
            'type' => ['description' => 'Type de l’événement ou de la ressource.'],
            'description' => ['description' => 'Description détaillée de l’incident.'],
            'departure_uuid' => ['description' => 'UUID du départ concerné.'],
            'latitude' => ['description' => 'Latitude GPS en degrés décimaux.'],
            'longitude' => ['description' => 'Longitude GPS en degrés décimaux.'],
            'occurred_at' => ['description' => 'Date et heure de l’événement au format ISO 8601.'],
        ];
    }
}
