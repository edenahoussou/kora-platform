<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\IdentityAccess\Actions\ActivateTerminalAction;
use App\Modules\IdentityAccess\Actions\AssignTerminalAction;
use App\Modules\IdentityAccess\Actions\DeactivateTerminalAction;
use App\Modules\IdentityAccess\Actions\RecoverTerminalAction;
use App\Modules\IdentityAccess\Actions\RegisterTerminalAction;
use App\Modules\IdentityAccess\Actions\ReportTerminalLostAction;
use App\Modules\IdentityAccess\Actions\ReturnTerminalToStockAction;
use App\Modules\IdentityAccess\Actions\RevokeTerminalSessionsAction;
use App\Modules\IdentityAccess\Actions\SetTerminalMaintenanceAction;
use App\Modules\IdentityAccess\Actions\UnassignTerminalAction;
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Http\Requests\AssignTerminalRequest;
use App\Modules\IdentityAccess\Http\Requests\RecoverTerminalRequest;
use App\Modules\IdentityAccess\Http\Requests\RegisterTerminalRequest;
use App\Modules\IdentityAccess\Http\Requests\TerminalReasonRequest;
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
     * Liste JSON conservée pour les consommateurs existants de l'interface
     * terminal. La page Inertia est portée par TerminalManagementController.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', TerminalP501::class);

        $query = TerminalP501::query()
            ->where('type', 'p501')
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

    public function reportLost(TerminalReasonRequest $request, TerminalP501 $terminal, ReportTerminalLostAction $reportLost): JsonResponse
    {
        Gate::authorize('changeStatus', $terminal);

        $terminal = $reportLost->execute($terminal, $request->user(), $request->reason())->load(['assignedUser', 'agency']);

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

    public function unassign(
        TerminalReasonRequest $request,
        TerminalP501 $terminal,
        UnassignTerminalAction $unassignTerminal,
    ): JsonResponse {
        $terminal = $unassignTerminal->execute($terminal, $request->reason() ?? 'Désaffectation administrative', $request->user());

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function recover(
        RecoverTerminalRequest $request,
        TerminalP501 $terminal,
        RecoverTerminalAction $recoverTerminal,
    ): JsonResponse {
        $terminal = $recoverTerminal->execute($terminal, $request->payload(), $request->user());

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function returnToStock(
        TerminalReasonRequest $request,
        TerminalP501 $terminal,
        ReturnTerminalToStockAction $returnToStock,
    ): JsonResponse {
        $terminal = $returnToStock->execute($terminal, $request->reason() ?? 'Retour au stock', $request->user());

        return response()->json(['data' => new TerminalP501Resource($terminal)]);
    }

    public function revokeSessions(
        TerminalReasonRequest $request,
        TerminalP501 $terminal,
        RevokeTerminalSessionsAction $revokeSessions,
    ): JsonResponse {
        Gate::authorize('changeStatus', $terminal);

        $count = $revokeSessions->execute($terminal, $request->reason() ?? 'manual_revoke');

        return response()->json(['revoked_sessions' => $count]);
    }
}
