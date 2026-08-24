<?php

namespace App\Modules\BusNetwork\Http\Resources;

use App\Modules\BusNetwork\Models\Line;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

use function Illuminate\Support\enum_value;

/**
 * @mixin Line
 */
class LineResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'code' => $this->code,
            'name' => $this->name,
            'type' => enum_value($this->type),
            'countries_crossed' => $this->countries_crossed ?? [],
            'distance_km' => $this->distance_km,
            'estimated_duration_minutes' => $this->estimated_duration_minutes,
            'status' => enum_value($this->status),
            'stops' => StopResource::collection($this->whenLoaded('stops')),
        ];
    }
}
