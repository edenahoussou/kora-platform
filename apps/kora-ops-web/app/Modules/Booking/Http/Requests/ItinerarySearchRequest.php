<?php

namespace App\Modules\Booking\Http\Requests;

use App\Modules\Booking\Models\GroupBookingSetting;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

class ItinerarySearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'from_physical_stop_uuid' => ['required_without:from_city', 'nullable', 'uuid', 'exists:physical_stops,uuid'],
            'to_physical_stop_uuid' => ['required_without:to_city', 'nullable', 'uuid', 'exists:physical_stops,uuid'],
            'from_city' => ['required_without:from_physical_stop_uuid', 'nullable', 'string', 'max:255'],
            'to_city' => ['required_without:to_physical_stop_uuid', 'nullable', 'string', 'max:255'],
            'date' => ['required', 'date_format:Y-m-d'],
            'passenger_count' => ['sometimes', 'integer', 'min:1', 'max:'.GroupBookingSetting::current()->maximum_passengers],
            'sort' => ['sometimes', 'string', 'in:departure_time,arrival_time,price,duration,connections'],
            'sort_direction' => ['sometimes', 'string', 'in:asc,desc'],
            'service_class_code' => ['sometimes', 'string', 'exists:service_classes,code'],
            'trip_type' => ['sometimes', 'string', 'in:one_way,round_trip'],
            'return_date' => ['sometimes', 'date_format:Y-m-d'],
        ];
    }

    public function sortBy(): string
    {
        return $this->string('sort', 'connections')->toString();
    }

    public function sortDirection(): string
    {
        return $this->string('sort_direction', 'asc')->toString();
    }

    public function serviceClassCode(): ?string
    {
        $code = $this->string('service_class_code')->toString();

        return $code !== '' ? $code : null;
    }

    public function tripType(): string
    {
        return $this->string('trip_type', 'one_way')->toString();
    }

    public function returnDate(): ?CarbonImmutable
    {
        $date = $this->string('return_date')->toString();

        return $date !== '' ? CarbonImmutable::parse($date) : null;
    }

    /** @return array<string, array{description: string, example?: string}> */
    public function queryParameters(): array
    {
        return [
            'from_physical_stop_uuid' => ['description' => 'Valeur de « from physical stop uuid ».'],
            'to_physical_stop_uuid' => ['description' => 'Valeur de « to physical stop uuid ».'],
            'from_city' => ['description' => 'Ville de départ, telle que renvoyée par bus/cities.', 'example' => 'Cotonou'],
            'to_city' => ['description' => "Ville d'arrivée, telle que renvoyée par bus/cities.", 'example' => 'Parakou'],
            'date' => ['description' => 'Date du trajet au format YYYY-MM-DD.'],
            'passenger_count' => ['description' => 'Nombre de passagers.'],
            'sort' => ['description' => 'Critère de tri des résultats.'],
            'sort_direction' => ['description' => 'Valeur de « sort direction ».'],
            'service_class_code' => ['description' => 'Valeur de « service class code ».'],
            'trip_type' => ['description' => 'Type de trajet.'],
            'return_date' => ['description' => 'Date du trajet retour au format YYYY-MM-DD.'],
        ];
    }
}
