<?php

namespace App\Modules\Tracking\Http\Requests;

use App\Modules\Settings\Settings\OperationsSettings;
use App\Modules\Tracking\Data\TrackingPositionSource;
use App\Modules\Tracking\Data\TrackingPositionStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Le départ n'est volontairement PAS validé par une règle `exists` : un
 * départ inconnu et un départ non affecté reçoivent la même réponse (403
 * `departure_not_assigned`), l'existence d'un départ d'autrui n'est jamais
 * révélée (même principe que StoreIncidentRequest).
 */
class IngestTrackingPositionsRequest extends FormRequest
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
        $max = app(OperationsSettings::class)->trackingMaxPositionsPerBatch;

        return [
            'departure_uuid' => ['required', 'uuid'],
            'positions' => ['required', 'array', 'min:1', "max:{$max}"],
            'positions.*.position_uuid' => ['required', 'uuid', 'distinct'],
            'positions.*.sequence' => ['required', 'integer', 'min:0'],
            // Heure locale du terminal — les horodatages hors bornes sont
            // rejetés individuellement par l'action, pas ici : un lot ne
            // doit pas échouer en entier pour une seule position aberrante.
            'positions.*.recorded_at' => ['required', 'date'],
            'positions.*.status' => ['sometimes', Rule::enum(TrackingPositionStatus::class)],
            'positions.*.latitude' => ['required_if:positions.*.status,fix', 'nullable', 'numeric', 'between:-90,90'],
            'positions.*.longitude' => ['required_if:positions.*.status,fix', 'nullable', 'numeric', 'between:-180,180'],
            'positions.*.accuracy_meters' => ['nullable', 'numeric', 'min:0'],
            'positions.*.speed_kmh' => ['nullable', 'numeric', 'min:0'],
            'positions.*.heading_degrees' => ['nullable', 'numeric', 'between:0,359.99'],
            'positions.*.source' => ['sometimes', Rule::enum(TrackingPositionSource::class)],
        ];
    }

    public function departureUuid(): string
    {
        return $this->string('departure_uuid')->toString();
    }

    /**
     * @return array<int, array{position_uuid: string, sequence: int, recorded_at: string, latitude: ?float, longitude: ?float, accuracy_meters: ?float, speed_kmh: ?float, heading_degrees: ?float, source: string, status: string}>
     */
    public function positions(): array
    {
        /** @var array<int, array<string, mixed>> $positions */
        $positions = $this->input('positions', []);

        return collect($positions)->map(fn (array $item): array => [
            'position_uuid' => $item['position_uuid'],
            'sequence' => (int) $item['sequence'],
            'recorded_at' => $item['recorded_at'],
            'status' => $item['status'] ?? TrackingPositionStatus::Fix->value,
            'latitude' => isset($item['latitude']) ? (float) $item['latitude'] : null,
            'longitude' => isset($item['longitude']) ? (float) $item['longitude'] : null,
            'accuracy_meters' => isset($item['accuracy_meters']) ? (float) $item['accuracy_meters'] : null,
            'speed_kmh' => isset($item['speed_kmh']) ? (float) $item['speed_kmh'] : null,
            'heading_degrees' => isset($item['heading_degrees']) ? (float) $item['heading_degrees'] : null,
            'source' => $item['source'] ?? TrackingPositionSource::Gps->value,
        ])->all();
    }

    /** @return array<string, array{description: string}> */
    public function bodyParameters(): array
    {
        return [
            'departure_uuid' => ['description' => 'UUID du départ concerné.'],
            'positions' => ['description' => 'Positions de suivi à ingérer.'],
            'positions.*.position_uuid' => ['description' => 'UUID unique de la position.'],
            'positions.*.sequence' => ['description' => 'Numéro de séquence de la position.'],
            'positions.*.recorded_at' => ['description' => 'Date et heure de la position.'],
            'positions.*.status' => ['description' => 'Statut demandé ou observé.'],
            'positions.*.latitude' => ['description' => 'Latitude GPS en degrés décimaux.'],
            'positions.*.longitude' => ['description' => 'Longitude GPS en degrés décimaux.'],
            'positions.*.accuracy_meters' => ['description' => 'Précision GPS en mètres.'],
            'positions.*.speed_kmh' => ['description' => 'Valeur de « speed kmh ».'],
            'positions.*.heading_degrees' => ['description' => 'Cap en degrés.'],
            'positions.*.source' => ['description' => 'Source de la position.'],
        ];
    }
}
