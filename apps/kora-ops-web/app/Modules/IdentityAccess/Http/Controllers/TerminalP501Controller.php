<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Actions\ActivateTerminalAction;
use App\Modules\IdentityAccess\Actions\AssignTerminalAction;
use App\Modules\IdentityAccess\Actions\DeactivateTerminalAction;
use App\Modules\IdentityAccess\Actions\RegisterTerminalAction;
use App\Modules\IdentityAccess\Actions\ReportTerminalLostAction;
use App\Modules\IdentityAccess\Actions\SetTerminalMaintenanceAction;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Http\Requests\AssignTerminalRequest;
use App\Modules\IdentityAccess\Http\Requests\RegisterTerminalRequest;
use App\Modules\IdentityAccess\Http\Resources\TerminalAssignmentResource;
use App\Modules\IdentityAccess\Http\Resources\TerminalP501Resource;
use App\Modules\IdentityAccess\Models\TerminalP501;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Services et endpoints pour l'administration des terminaux P501, destinés
 * à l'écran OPS Web (front construit séparément dans MEZ-99). La route
 * `terminals` (page) reste volontairement inchangée ici ; ces endpoints
 * exposent uniquement les données et actions dont cette page aura besoin.
 */
class TerminalP501Controller extends Controller
{
    /**
     * Liste paginée des terminaux (uuid, numéro de série, statut,
     * affectation, dernière synchronisation).
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TerminalP501::class);

        $query = TerminalP501::query()
            ->with(['assignedUser', 'agency'])
            ->orderBy('serial_number');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $query->whereRaw('LOWER(serial_number) LIKE ?', ['%'.mb_strtolower($search).'%']);
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, TerminalP501Status::values(), true)) {
            $query->where('status', $status);
        }

        $terminals = $query->paginate(20);

        return response()->json([
            'data' => TerminalP501Resource::collection($terminals),
            'meta' => [
                'current_page' => $terminals->currentPage(),
                'last_page' => $terminals->lastPage(),
                'total' => $terminals->total(),
            ],
            'statuses' => TerminalP501Status::options(),
            'permissions' => [
                'canCreate' => Gate::allows('create', TerminalP501::class),
                'canManage' => Gate::allows('update', new TerminalP501),
            ],
        ]);
    }

    /**
     * Détail d'un terminal : affectation courante, statut, dernière
     * synchronisation, dernier accès et historique d'affectation.
     */
    public function show(TerminalP501 $terminal): JsonResponse
    {
        Gate::authorize('view', $terminal);

        $terminal->load(['assignedUser', 'agency', 'assignments.assignedUser', 'assignments.agency', 'assignments.assignedByUser']);

        return response()->json([
            'data' => (new TerminalP501Resource($terminal))->toArray(request()),
            'assignment_history' => TerminalAssignmentResource::collection($terminal->assignments),
        ]);
    }

    public function store(RegisterTerminalRequest $request, RegisterTerminalAction $registerTerminal): JsonResponse
    {
        $terminal = $registerTerminal->execute($request->payload(), $request->user())->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)], 201);
    }

    public function activate(Request $request, TerminalP501 $terminal, ActivateTerminalAction $activateTerminal): JsonResponse
    {
        Gate::authorize('changeStatus', $terminal);

        $terminal = $activateTerminal->execute($terminal, $request->user())->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function deactivate(Request $request, TerminalP501 $terminal, DeactivateTerminalAction $deactivateTerminal): JsonResponse
    {
        Gate::authorize('changeStatus', $terminal);

        $terminal = $deactivateTerminal->execute($terminal, $request->user())->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function maintenance(Request $request, TerminalP501 $terminal, SetTerminalMaintenanceAction $setMaintenance): JsonResponse
    {
        Gate::authorize('changeStatus', $terminal);

        $terminal = $setMaintenance->execute($terminal, $request->user())->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function reportLost(Request $request, TerminalP501 $terminal, ReportTerminalLostAction $reportLost): JsonResponse
    {
        Gate::authorize('changeStatus', $terminal);

        $terminal = $reportLost->execute($terminal, $request->user())->load(['assignedUser', 'agency']);

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function assign(
        AssignTerminalRequest $request,
        TerminalP501 $terminal,
        AssignTerminalAction $assignTerminal,
    ): JsonResponse {
        $terminal = $assignTerminal->execute($terminal, $request->payload(), $request->user());

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }
}
