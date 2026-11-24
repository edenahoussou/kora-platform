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
     * Returns active cities with their physical stops, ready for itinerary search.
     * Each physical stop exposes its UUID, which the mobile uses as
     * `from_physical_stop_uuid` / `to_physical_stop_uuid` in `GET /bus/itineraries`.
     *
     * @unauthenticated
     *
     * @response 200 scenario="Cities list" {
     *   "data": [
     *     {
     *       "city": "Cotonou",
     *       "country": "BJ",
     *       "physical_stops": [
     *         {
     *           "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21",
     *           "name": "Cotonou · St Michel"
     *         },
     *         {
     *           "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22",
     *           "name": "Cotonou · Dantokpa"
     *         }
     *       ]
     *     },
     *     {
     *       "city": "Abidjan",
     *       "country": "CI",
     *       "physical_stops": [
     *         {
     *           "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e23",
     *           "name": "Abidjan · Adjamé"
     *         }
     *       ]
     *     }
     *   ]
     * }
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
     *
     * @response 200 scenario="Lines list" {
     *   "data": [
     *     {
     *       "uuid": "9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f",
     *       "code": "L1",
     *       "name": "Cotonou - Abidjan",
     *       "type": "internationale",
     *       "countries_crossed": ["BJ", "TG", "GH", "CI"],
     *       "distance_km": 1020,
     *       "estimated_duration_minutes": 1260,
     *       "status": "active",
     *       "stops": []
     *     }
     *   ]
     * }
     */
    public function lines(ListLinesRequest $request): AnonymousResourceCollection
    {
        return LineResource::collection($this->busCatalog->lines($request->filters()));
    }

    /**
     * Show a Bus line.
     *
     * Includes the line's active stops, ordered along the route.
     *
     * @urlParam line string required Public UUID of the line. Example: 9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f
     *
     * @unauthenticated
     *
     * @response 200 scenario="Line detail" {
     *   "data": {
     *     "uuid": "9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f",
     *     "code": "L1",
     *     "name": "Cotonou - Abidjan",
     *     "type": "internationale",
     *     "countries_crossed": ["BJ", "TG", "GH", "CI"],
     *     "distance_km": 1020,
     *     "estimated_duration_minutes": 1260,
     *     "status": "active",
     *     "stops": [
     *       {
     *         "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21",
     *         "name": "Cotonou · St Michel",
     *         "city": "Cotonou",
     *         "country": "BJ",
     *         "stop_order": 1,
     *         "km_from_origin": 0,
     *         "physical_stop": {
     *           "uuid": "a1b2c3d4-8f1a-4b3e-9a1a-2f4b6c8d0e31",
     *           "name": "Gare de Dantokpa",
     *           "city": "Cotonou",
     *           "country": "BJ",
     *           "latitude": 6.3654,
     *           "longitude": 2.4183
     *         }
     *       },
     *       {
     *         "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22",
     *         "name": "Abidjan · Adjamé",
     *         "city": "Abidjan",
     *         "country": "CI",
     *         "stop_order": 2,
     *         "km_from_origin": 1020,
     *         "physical_stop": null
     *       }
     *     ]
     *   }
     * }
     * @response 404 scenario="Line not found" {
     *   "message": "No query results for model [App\\Modules\\BusNetwork\\Models\\Line]."
     * }
     */
    public function line(Line $line): LineResource
    {
        return new LineResource($this->busCatalog->line($line));
    }
}
