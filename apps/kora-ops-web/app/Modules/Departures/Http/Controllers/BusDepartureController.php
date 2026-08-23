<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Departures\Http\Requests\ListDepartureSeatsRequest;
use App\Modules\Departures\Http\Requests\ListDeparturesRequest;
use App\Modules\Departures\Http\Requests\ShowDepartureRequest;
use App\Modules\Departures\Http\Resources\DepartureResource;
use App\Modules\Departures\Http\Resources\SeatAvailabilityResource;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Bus
 *
 * Public Bus departure and seat availability endpoints.
 */
class BusDepartureController extends Controller
{
    public function __construct(private DepartureSearchService $departures) {}

    /**
     * List departures.
     *
     * Paginated list of scheduled departures (15 per page by default).
     *
     * @queryParam date string required Travel date in YYYY-MM-DD format. Example: 2026-08-15
     * @queryParam line string Optional line UUID filter. Example: 9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f
     * @queryParam origin_city string Optional origin city filter (alias of from). Example: Cotonou
     * @queryParam destination_city string Optional destination city filter (alias of to). Example: Abidjan
     * @queryParam from string Optional origin city filter. Example: Cotonou
     * @queryParam to string Optional destination city filter. Example: Abidjan
     * @queryParam per_page int Optional page size (max 50, default 15). Example: 15
     * @queryParam page int Optional page number. Example: 1
     *
     * @unauthenticated
     */
    public function index(ListDeparturesRequest $request): AnonymousResourceCollection
    {
        return DepartureResource::collection($this->departures->search($request->filters()));
    }

    /**
     * Show a departure.
     *
     * @queryParam from string Optional origin city. Example: Cotonou
     * @queryParam to string Optional destination city. Example: Abidjan
     *
     * @unauthenticated
     */
    public function show(ShowDepartureRequest $request, Departure $departure): DepartureResource
    {
        return new DepartureResource($this->departures->show($departure, $request->filters()));
    }

    /**
     * List departure seats.
     *
     * @queryParam origin_stop_uuid string required Public UUID of the boarding stop.
     * @queryParam destination_stop_uuid string required Public UUID of the drop-off stop.
     *
     * @unauthenticated
     */
    public function seats(ListDepartureSeatsRequest $request, Departure $departure): AnonymousResourceCollection
    {
        $data = $request->segmentFilters();

        return SeatAvailabilityResource::collection(
            $this->departures->seats(
                $departure,
                $data['origin_stop_uuid'],
                $data['destination_stop_uuid'],
            ),
        );
    }
}
