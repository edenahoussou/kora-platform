<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Departures\Http\Requests\ListDepartureSeatsRequest;
use App\Modules\Departures\Http\Resources\SeatAvailabilityResource;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\DepartureSearchService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Bus
 *
 * Public Bus seat availability endpoint.
 */
class BusDepartureController extends Controller
{
    public function __construct(private DepartureSearchService $departures)
    {
        //
    }

    /**
     * List departure seats.
     *
     * Returns every seat on the assigned bus, with its availability status for
     * the requested origin/destination segment (available, held, occupied, or blocked).
     * Adds `composition_uuid` and `grid` (canonical cabin layout; cells join
     * `data` seats on `code`). Both are null when the bus has no template.
     *
     * @urlParam departure string required Public UUID of the departure. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @queryParam origin_stop_uuid string required Public UUID of the boarding stop. Example: 5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21
     * @queryParam destination_stop_uuid string required Public UUID of the drop-off stop. Example: 5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e22
     *
     * @unauthenticated
     *
     * @response 200 scenario="Seat map" {
     *   "composition_uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *   "grid": {
     *     "rows": 2, "cols": 4,
     *     "cells": [
     *       {"kind": "driver", "x": 0, "y": 0},
     *       {"kind": "door", "x": 3, "y": 0},
     *       {"kind": "seat", "x": 0, "y": 1, "code": "1A", "seatKind": "standard"},
     *       {"kind": "aisle", "x": 2, "y": 1},
     *       {"kind": "seat", "x": 3, "y": 1, "code": "1B", "seatKind": "standard", "maintenance": true}
     *     ]
     *   },
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
        )->additional([
            'composition_uuid' => $departure->bus?->composition?->uuid,
            'grid' => $departure->bus?->composition?->seatGrid()->toArray(),
        ]);
    }
}
