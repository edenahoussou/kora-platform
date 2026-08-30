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
    public function __construct(private DepartureSearchService $departures)
    {
        //
    }

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
     *
     * @response 200 scenario="Departures list" {
     *   "data": [
     *     {
     *       "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *       "departure_date": "2026-08-15",
     *       "departure_time": "07:30",
     *       "estimated_arrival_at": "2026-08-16T04:30:00.000000Z",
     *       "status": "scheduled",
     *       "line": {
     *         "uuid": "9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f",
     *         "code": "L1",
     *         "name": "Cotonou - Abidjan",
     *         "type": "internationale",
     *         "countries_crossed": ["BJ", "TG", "GH", "CI"],
     *         "distance_km": 1020,
     *         "estimated_duration_minutes": 1260,
     *         "status": "active",
     *         "stops": []
     *       },
     *       "bus": {
     *         "uuid": "3a2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e30",
     *         "registration_number": "BJ-9254-RB",
     *         "brand": "KORA",
     *         "model": "Coach 55",
     *         "seat_count": 55,
     *         "layout": [
     *           {"type": "standard", "codes": ["1A", "1B", "1C", "1D"]},
     *           {"type": "standard", "codes": ["2A", "2B", "2C", "2D"]}
     *         ],
     *         "amenities": [
     *           {"uuid": "a1b2c3d4-0000-0000-0000-000000000001", "code": "wifi", "label": "Wi-Fi à bord", "icon": "wifi"},
     *           {"uuid": "a1b2c3d4-0000-0000-0000-000000000002", "code": "air_conditioning", "label": "Climatisation", "icon": "snowflake"},
     *           {"uuid": "a1b2c3d4-0000-0000-0000-000000000003", "code": "usb_charging", "label": "Prises USB", "icon": "usb"}
     *         ]
     *       },
     *       "origin_stop": {
     *         "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21",
     *         "name": "Cotonou · St Michel",
     *         "city": "Cotonou",
     *         "country": "BJ",
     *         "stop_order": 1,
     *         "km_from_origin": 0
     *       },
     *       "destination_stop": {
     *         "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22",
     *         "name": "Abidjan · Adjamé",
     *         "city": "Abidjan",
     *         "country": "CI",
     *         "stop_order": 2,
     *         "km_from_origin": 1020
     *       },
     *       "available_seats_count": 41,
     *       "fare_preview": {
     *         "fare_uuid": "7d2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e40",
     *         "base_amount": 12500,
     *         "final_amount": 12500,
     *         "currency": "XOF",
     *         "service_class_code": "STD",
     *         "service_class_name": "Standard",
     *         "service_class_description": "Sièges inclinables, climatisation, prise USB.",
     *         "is_quote": false
     *       }
     *     }
     *   ],
     *   "links": {
     *     "first": "http://kora-ops-web.local/api/v1/bus/departures?page=1",
     *     "last": "http://kora-ops-web.local/api/v1/bus/departures?page=1",
     *     "prev": null,
     *     "next": null
     *   },
     *   "meta": {
     *     "current_page": 1,
     *     "from": 1,
     *     "last_page": 1,
     *     "path": "http://kora-ops-web.local/api/v1/bus/departures",
     *     "per_page": 15,
     *     "to": 1,
     *     "total": 1
     *   }
     * }
     * @response 422 scenario="Missing required date" {
     *   "message": "The date field is required.",
     *   "errors": {
     *     "date": ["The date field is required."]
     *   }
     * }
     */
    public function index(ListDeparturesRequest $request): AnonymousResourceCollection
    {
        return DepartureResource::collection($this->departures->search($request->filters(), sellableOnly: true));
    }

    /**
     * Show a departure.
     *
     * @urlParam departure string required Public UUID of the departure. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @queryParam from string Optional origin city. Example: Cotonou
     * @queryParam to string Optional destination city. Example: Abidjan
     *
     * @unauthenticated
     *
     * @response 200 scenario="Departure detail" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "departure_date": "2026-08-15",
     *     "departure_time": "07:30",
     *     "estimated_arrival_at": "2026-08-16T04:30:00.000000Z",
     *     "status": "scheduled",
     *     "line": {
     *       "uuid": "9c2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e1f",
     *       "code": "L1",
     *       "name": "Cotonou - Abidjan",
     *       "type": "internationale",
     *       "countries_crossed": ["BJ", "TG", "GH", "CI"],
     *       "distance_km": 1020,
     *       "estimated_duration_minutes": 1260,
     *       "status": "active",
     *       "stops": []
     *     },
     *     "bus": {
     *       "uuid": "3a2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e30",
     *       "registration_number": "BJ-9254-RB",
     *       "brand": "KORA",
     *       "model": "Coach 55",
     *       "seat_count": 55,
     *       "layout": [
     *         {"type": "standard", "codes": ["1A", "1B", "1C", "1D"]},
     *         {"type": "standard", "codes": ["2A", "2B", "2C", "2D"]}
     *       ],
     *       "amenities": [
     *         {"uuid": "a1b2c3d4-0000-0000-0000-000000000001", "code": "wifi", "label": "Wi-Fi à bord", "icon": "wifi"},
     *         {"uuid": "a1b2c3d4-0000-0000-0000-000000000002", "code": "air_conditioning", "label": "Climatisation", "icon": "snowflake"},
     *         {"uuid": "a1b2c3d4-0000-0000-0000-000000000003", "code": "usb_charging", "label": "Prises USB", "icon": "usb"}
     *       ]
     *     },
     *     "origin_stop": {
     *       "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21",
     *       "name": "Cotonou · St Michel",
     *       "city": "Cotonou",
     *       "country": "BJ",
     *       "stop_order": 1,
     *       "km_from_origin": 0
     *     },
     *     "destination_stop": {
     *       "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22",
     *       "name": "Abidjan · Adjamé",
     *       "city": "Abidjan",
     *       "country": "CI",
     *       "stop_order": 2,
     *       "km_from_origin": 1020
     *     },
     *     "available_seats_count": 41,
     *     "fare_preview": {
     *       "fare_uuid": "7d2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e40",
     *       "base_amount": 12500,
     *       "final_amount": 12500,
     *       "currency": "XOF",
     *       "service_class_code": "STD",
     *       "service_class_name": "Standard",
     *       "service_class_description": "Sièges inclinables, climatisation, prise USB.",
     *       "is_quote": false
     *     }
     *   }
     * }
     * @response 404 scenario="Departure not found" {
     *   "message": "No query results for model [App\\Modules\\Departures\\Models\\Departure]."
     * }
     */
    public function show(ShowDepartureRequest $request, Departure $departure): DepartureResource
    {
        return new DepartureResource($this->departures->show($departure, $request->filters()));
    }

    /**
     * List departure seats.
     *
     * Returns every seat on the assigned bus, with its availability status for
     * the requested origin/destination segment (available, held, occupied, or blocked).
     *
     * @urlParam departure string required Public UUID of the departure. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @queryParam origin_stop_uuid string required Public UUID of the boarding stop. Example: 5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21
     * @queryParam destination_stop_uuid string required Public UUID of the drop-off stop. Example: 5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22
     *
     * @unauthenticated
     *
     * @response 200 scenario="Seat map" {
     *   "data": [
     *     {
     *       "uuid": "6e2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e50",
     *       "code": "1A",
     *       "type": "standard",
     *       "sellable": true,
     *       "availability_status": "available",
     *       "held_until": null
     *     },
     *     {
     *       "uuid": "6e2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e51",
     *       "code": "1B",
     *       "type": "standard",
     *       "sellable": true,
     *       "availability_status": "held",
     *       "held_until": "2026-08-15T07:20:00.000000Z"
     *     },
     *     {
     *       "uuid": "6e2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e52",
     *       "code": "1C",
     *       "type": "standard",
     *       "sellable": true,
     *       "availability_status": "occupied",
     *       "held_until": null
     *     }
     *   ]
     * }
     * @response 422 scenario="Invalid segment" {
     *   "message": "The requested origin and destination do not form a valid ordered segment for this line.",
     *   "errors": {
     *     "segment": ["The requested origin and destination do not form a valid ordered segment for this line."]
     *   }
     * }
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
