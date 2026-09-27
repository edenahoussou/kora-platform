<?php

namespace App\Modules\FleetLite\Http\Requests;

use App\Modules\FleetLite\Data\IncidentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListIncidentsRequest extends FormRequest
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
            'status' => ['sometimes', Rule::enum(IncidentStatus::class)],
            'departure_uuid' => ['sometimes', 'uuid'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 30);
    }

    /** @return array<string, array{description: string}> */
    public function queryParameters(): array
    {
        return [
            'status' => ['description' => 'Statut demandé ou observé.'],
            'departure_uuid' => ['description' => 'UUID du départ concerné.'],
            'per_page' => ['description' => 'Nombre d’éléments par page.'],
            'page' => ['description' => 'Numéro de page.'],
        ];
    }
}
