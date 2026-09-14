<?php

namespace App\Modules\Departures\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\BusNetwork\Data\LineType;
use App\Modules\Departures\Actions\ComputeManifestVersionAction;
use App\Modules\Departures\Actions\ResolveManifestRowStatusAction;
use App\Modules\Departures\Http\Resources\OpsDepartureResource;
use App\Modules\Departures\Http\Resources\OpsManifestRowResource;
use App\Modules\Departures\Models\Departure;
use App\Modules\Departures\Services\CrewDepartureScope;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Eloquent\Collection;
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

        [$includedSegments, $ticketsByPassengerId] = $this->manifestSegments($departure);
        $manifestVersion = app(ComputeManifestVersionAction::class)->execute($departure, $includedSegments, $ticketsByPassengerId);

        return response()->json([
            'data' => (new OpsDepartureResource($departure, $manifestVersion))->toArray($request),
        ]);
    }

    /**
     * Manifeste téléchargeable : une ligne par segment/passager du départ
     * consulté (jamais les autres jambes d'un itinéraire multi-jambes).
     * Jamais de prix, montant, ou identifiant numérique interne.
     */
    public function manifest(Request $request, Departure $departure): JsonResponse
    {
        Gate::authorize('viewAsCrew', $departure);

        $departure->loadMissing('line:id,uuid,code,name,type');
        $international = $departure->line->type === LineType::Internationale;

        [$includedSegments, $ticketsByPassengerId, $statusesBySegmentId] = $this->manifestSegments($departure, withStatuses: true);
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
            'data' => $paginated->getCollection()->map(function ($segment) use ($ticketsByPassengerId, $statusesBySegmentId, $international) {
                $ticket = $segment->reservation_passenger_id !== null ? ($ticketsByPassengerId[$segment->reservation_passenger_id] ?? null) : null;

                return (new OpsManifestRowResource($segment, $ticket, $statusesBySegmentId[$segment->id], $international))->toArray(request());
            })->values(),
            'meta' => $this->paginationMeta($paginated),
            'manifestVersion' => $manifestVersion,
        ]);
    }

    /**
     * Segments du départ retenus pour le manifeste — exclut les segments
     * pas encore payés/confirmés (hold, paiement en attente...), qui
     * n'ont pas de billet à présenter à l'embarquement.
     *
     * @return array{0: Collection<int, \App\Modules\Booking\Models\ReservationSegment>, 1: array<int, Ticket>, 2: array<int, \App\Modules\Departures\Data\ManifestSegmentStatus>}
     */
    private function manifestSegments(Departure $departure, bool $withStatuses = false): array
    {
        $segments = $departure->reservationSegments()
            ->with([
                'reservationPassenger',
                'reservation.payments' => fn ($q) => $q->orderByDesc('created_at'),
                'seat:id,uuid,code',
                'originStop:id,uuid,name',
                'destinationStop:id,uuid,name',
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $passengerIds = $segments->pluck('reservation_passenger_id')->filter()->unique()->values();
        $ticketsByPassengerId = $passengerIds->isEmpty() ? [] : Ticket::query()
            ->whereIn('reservation_passenger_id', $passengerIds)
            ->get()
            ->keyBy('reservation_passenger_id')
            ->all();

        $resolveStatus = app(ResolveManifestRowStatusAction::class);
        $statusesBySegmentId = [];
        $included = $segments->filter(function ($segment) use ($resolveStatus, &$statusesBySegmentId, $departure) {
            $latestPayment = $segment->reservation?->payments->first();
            $status = $resolveStatus->execute($segment, $latestPayment, $departure->status);

            if ($status === null) {
                return false;
            }

            $statusesBySegmentId[$segment->id] = $status;

            return true;
        })->values();

        return $withStatuses ? [$included, $ticketsByPassengerId, $statusesBySegmentId] : [$included, $ticketsByPassengerId];
    }

    private function perPage(Request $request): int
    {
        return min(self::MAX_PER_PAGE, max(1, $request->integer('per_page', self::DEFAULT_PER_PAGE)));
    }

    /**
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
