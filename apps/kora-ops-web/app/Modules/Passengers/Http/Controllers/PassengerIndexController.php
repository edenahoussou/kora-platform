<?php

namespace App\Modules\Passengers\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Passengers\Services\PassengerDirectoryService;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Contrôleur de l'annuaire des passagers pour le back-office KORA OPS.
 */
class PassengerIndexController extends Controller
{
    public function __construct(
        private readonly PassengerDirectoryService $directoryService,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ticket::class);

        $filters = [
            'q' => $request->string('q')->trim()->value() ?: null,
            'status' => $request->string('status')->trim()->value() ?: 'all',
            'route' => $request->string('route')->trim()->value() ?: 'all',
            'frequency' => $request->string('frequency')->trim()->value() ?: 'all',
        ];

        $page = max(1, $request->integer('page', 1));
        $paginated = $this->directoryService->paginate($filters, 15, $page);

        return Inertia::render('passengers', [
            'passengers' => [
                'data' => $paginated->items(),
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
                'from' => $paginated->firstItem() ?? 0,
                'to' => $paginated->lastItem() ?? 0,
            ],
            'filters' => $filters,
        ]);
    }

    public function show(string $phone): JsonResponse
    {
        Gate::authorize('viewAny', Ticket::class);

        $detail = $this->directoryService->findByPhone($phone);

        if ($detail === null) {
            return response()->json(['message' => 'Passager non trouvé.'], 404);
        }

        return response()->json($detail);
    }
}
