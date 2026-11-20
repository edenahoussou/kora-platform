<?php

namespace App\Modules\Parcels\Http\Resources;

use App\Modules\Parcels\Services\ParcelPricing;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParcelQuoteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return app(ParcelPricing::class)->present($this->resource);
    }
}
