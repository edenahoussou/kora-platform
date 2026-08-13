<?php

namespace App\Modules\BusNetwork\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'city' => $this->resource['city'],
            'country' => $this->resource['country'],
            'stops_count' => $this->resource['stops_count'],
        ];
    }
}
