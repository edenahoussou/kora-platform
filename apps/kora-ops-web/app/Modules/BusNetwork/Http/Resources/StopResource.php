<?php

namespace App\Modules\BusNetwork\Http\Resources;

use App\Modules\BusNetwork\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stop
 */
class StopResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'city' => $this->city,
            'country' => $this->country,
            'stop_order' => $this->stop_order,
            'km_from_origin' => (float) $this->km_from_origin,
        ];
    }
}
