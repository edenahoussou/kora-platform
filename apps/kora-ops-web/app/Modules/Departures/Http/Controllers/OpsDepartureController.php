<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\Departures\Actions\ComputeManifestVersionAction;
use App\Modules\Departures\Actions\ResolveManifestSegmentsAction;
use App\Modules\Departures\Http\Resources\OpsDepartureResource;
use App\Modules\Departures\Http\Resources\OpsManifestRowResource;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

/**
 * @group OPS Mobile — Departures
 *
 * Départs et manifeste pour l'équipage terrain (convoyeur, chauffeur,
 * chauffeur second, mécanicien) affecté — remplace les écrans mockés de
 * MEZ-11. Authentification : auth:sanctum (login existant) + terminal
 * enregistré et actif (middleware terminal.active).
 */
class OpsDepartureController extends Controller
{
    private const int MAX_PER_PAGE = 200;

    private const int DEFAULT_PER_PAGE = 100;

    /**
     * Départs accessibles à l'utilisateur connecté : ceux où il est
     * affecté (chauffeur, chauffeur second, convoyeur ou mécanicien),
     * hors départs annulés.
     *
     * @authenticated
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @queryParam date string Filtre sur la date de départ (YYYY-MM-DD). Example: 2026-10-01
     * @queryParam status string Filtre sur le statut opérationnel (scheduled, boarding, departed, arrived). Example: scheduled
     * @queryParam page integer Numéro de page. Example: 1
     * @queryParam per_page integer Taille de page (max 200). Example: 100
     *
     * @response 200 scenario="Départs affectés" {
     *   "data": [
     *     {
     *       "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *       "status": "scheduled",
     *       "departureDate": "2026-10-01",
     *       "departureTime": "08:00",
     *       "estimatedArrivalAt": "2026-10-01T11:00:00.000Z",
     *       "line": { "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21", "code": "COT-LFW", "name": "Cotonou - Lomé", "type": "internationale", "international": true },
     *       "stops": null,
     *       "bus": { "uuid": "7c1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e33", "registrationNumber": "AB-1234-RB", "seatCount": 60 },
     *       "crew": { "driver": null, "secondaryDriver": null, "conductor": null, "mechanic": null },
     *       "manifestVersion": null
     *     }
     *   ],
     *   "meta": { "currentPage": 1, "lastPage": 1, "perPage": 100, "total": 1 }
     * }
     * @response 404 scenario="Terminal unknown" {
     *   "error": { "code": "terminal_unknown", "message": "Ce terminal n'est pas enregistré. Contactez votre agence." }
     * }
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Departure::class);

        $query = app(CrewDepartureScope::class)->query($request->user())
            ->with(['line:id,uuid,code,name,type', 'bus:id,uuid,registration_number,seat_count'])
            ->orderBy('departure_date')
            ->orderBy('departure_time');

        $date = $request->string('date')->trim()->value();
        if ($date !== '') {
            $query->whereDate('departure_date', $date);
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '') {
            $query->where('status', $status);
        }

        $departures = $query->paginate($this->perPage($request));

        return response()->json([
            'data' => OpsDepartureResource::collection($departures),
            'meta' => $this->paginationMeta($departures),
        ]);
    }

    /**
     * Détail d'un départ affecté : ligne, arrêts, bus, équipage, statut
     * opérationnel et version du manifeste.
     *
     * @authenticated
     *
     * @urlParam departure string required UUID public du départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @response 200 scenario="Départ affecté" {
     *   "data": {
     *     "uuid": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e",
     *     "status": "scheduled",
     *     "departureDate": "2026-10-01",
     *     "departureTime": "08:00",
     *     "estimatedArrivalAt": "2026-10-01T11:00:00.000Z",
     *     "line": { "uuid": "5f2e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e21", "code": "COT-LFW", "name": "Cotonou - Lomé", "type": "internationale", "international": true },
     *     "stops": [
     *       { "uuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41", "name": "Cotonou Gare", "city": "Cotonou", "country": "BJ", "order": 1 },
     *       { "uuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e42", "name": "Lomé Gare", "city": "Lomé", "country": "TG", "order": 2 }
     *     ],
     *     "bus": { "uuid": "7c1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e33", "registrationNumber": "AB-1234-RB", "seatCount": 60 },
     *     "crew": {
     *       "driver": { "uuid": "1a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e51", "name": "Jean Dossou" },
     *       "secondaryDriver": null,
     *       "conductor": { "uuid": "2a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e52", "name": "Awa Traoré" },
     *       "mechanic": null
     *     },
     *     "manifestVersion": "3f9a1c2e8b7d4a10"
     *   }
     * }
     * @response 403 scenario="Départ non affecté, annulé ou hors périmètre" {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Terminal unknown" {
     *   "error": { "code": "terminal_unknown", "message": "Ce terminal n'est pas enregistré. Contactez votre agence." }
     * }
     */
    public function show(Request $request, Departure $departure): JsonResponse
    {
        Gate::authorize('viewAsCrew', $departure);

        $departure->load([
            'line:id,uuid,code,name,type',
            'line.stops:id,uuid,line_id,name,city,country,stop_order',
            'bus:id,uuid,registration_number,seat_count',
            'driver:id,uuid,name',
            'secondaryDriver:id,uuid,name',
            'conductor:id,uuid,name',
            'mechanic:id,uuid,name',
        ]);

        [$includedSegments, $ticketsByPassengerId] = app(ResolveManifestSegmentsAction::class)->execute($departure);
        $manifestVersion = app(ComputeManifestVersionAction::class)->execute($departure, $includedSegments, $ticketsByPassengerId);

        return response()->json([
            'data' => (new OpsDepartureResource($departure, $manifestVersion))->toArray($request),
        ]);
    }

    /**
     * Manifeste téléchargeable : une ligne par segment/passager du départ
     * consulté (jamais les autres jambes d'un itinéraire multi-jambes).
     * Jamais de prix, montant, ou identifiant numérique interne.
     *
     * @authenticated
     *
     * @urlParam departure string required UUID public du départ. Example: 9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6e
     *
     * @header X-Terminal-Serial Numéro de série du terminal. Example: P501-000123
     *
     * @queryParam q string Recherche par nom de passager ou code court du billet. Example: traore
     * @queryParam page integer Numéro de page. Example: 1
     * @queryParam per_page integer Taille de page (max 200). Example: 100
     *
     * @response 200 scenario="Manifeste (ligne nationale)" {
     *   "data": [
     *     {
     *       "ticketUuid": "4b1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e61",
     *       "shortCode": "AB12CD",
     *       "name": "Awa Traoré",
     *       "seatCode": "1A",
     *       "originStop": { "uuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e41", "name": "Cotonou Gare" },
     *       "destinationStop": { "uuid": "3a1e4b2a-8f1a-4b3e-9a1a-2f4b6c8d0e42", "name": "Lomé Gare" },
     *       "segmentStatus": "paid",
     *       "ticketStatus": "issued"
     *     }
     *   ],
     *   "meta": { "currentPage": 1, "lastPage": 1, "perPage": 100, "total": 1 },
     *   "manifestVersion": "3f9a1c2e8b7d4a10"
     * }
     * @response 403 scenario="Départ non affecté, annulé ou hors périmètre" {
     *   "message": "This action is unauthorized."
     * }
     * @response 404 scenario="Terminal unknown" {
     *   "error": { "code": "terminal_unknown", "message": "Ce terminal n'est pas enregistré. Contactez votre agence." }
     * }
     */
    public function manifest(Request $request, Departure $departure): JsonResponse
    {
        Gate::authorize('viewAsCrew', $departure);

        $departure->loadMissing('line:id,uuid,code,name,type');
        $international = $departure->line->type === LineType::Internationale;

        [$includedSegments, $ticketsByPassengerId, $statusesBySegmentId] = app(ResolveManifestSegmentsAction::class)->execute($departure);
        $manifestVersion = app(ComputeManifestVersionAction::class)->execute($departure, $includedSegments, $ticketsByPassengerId);

        $search = $request->string('q')->trim()->value();
        $rows = $includedSegments;

        if ($search !== '') {
            $term = mb_strtolower($search);
            $rows = $rows->filter(function ($segment) use ($ticketsByPassengerId, $term): bool {
                $passenger = $segment->reservationPassenger;
                $ticket = $segment->reservation_passenger_id !== null ? ($ticketsByPassengerId[$segment->reservation_passenger_id] ?? null) : null;
                $name = $passenger === null ? '' : mb_strtolower($passenger->first_name.' '.$passenger->last_name);
                $shortCode = $ticket === null ? '' : mb_strtolower((string) $ticket->short_code);

                return str_contains($name, $term) || str_contains($shortCode, $term);
            })->values();
        }

        $page = max(1, $request->integer('page', 1));
        $perPage = $this->perPage($request);
        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
        );

        return response()->json([
            'data' => $paginated->getCollection()->map(function (ReservationSegment $segment) use ($ticketsByPassengerId, $statusesBySegmentId, $international) {
                $ticket = $segment->reservation_passenger_id !== null ? ($ticketsByPassengerId[$segment->reservation_passenger_id] ?? null) : null;

                return (new OpsManifestRowResource($segment, $ticket, $statusesBySegmentId[$segment->id], $international))->toArray(request());
            })->values(),
            'meta' => $this->paginationMeta($paginated),
            'manifestVersion' => $manifestVersion,
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, int>
     */
    private function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
