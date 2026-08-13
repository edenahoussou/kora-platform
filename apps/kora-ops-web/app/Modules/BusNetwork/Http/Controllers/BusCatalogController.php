<?php

namespace App\Modules\BusNetwork\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Http\Requests\ListLinesRequest;
use App\Modules\BusNetwork\Http\Resources\CityResource;
use App\Modules\BusNetwork\Http\Resources\LineResource;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Services\BusCatalogService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Bus
 *
 * Public Bus catalog endpoints for KORA BUS search experiences.
 */
class BusCatalogController extends Controller
{
    public function __construct(private BusCatalogService $busCatalog) {}

    /**
     * List served cities.
     *
     * Returns active cities derived from active Bus stops.
     *
     * @unauthenticated
     */
    public function cities(): AnonymousResourceCollection
    {
        return CityResource::collection($this->busCatalog->cities());
    }

    /**
     * List Bus lines.
     *
     * @queryParam from string Optional origin city filter. Example: Cotonou
     * @queryParam to string Optional destination city filter. Example: Abidjan
     *
     * @unauthenticated
     */
    public function lines(ListLinesRequest $request): AnonymousResourceCollection
    {
        return LineResource::collection($this->busCatalog->lines($request->filters()));
    }

    /**
     * Show a Bus line.
     *
     * @unauthenticated
     */
    public function line(Line $line): LineResource
    {
        return new LineResource($this->busCatalog->line($line));
    }
}
