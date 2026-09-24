<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Services\TerminalManagementReadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TerminalManagementController extends Controller
{
    public function __construct(
        private TerminalManagementReadService $read,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', TerminalP501::class);

        return Inertia::render('terminals', [
            'terminals' => $this->read->paginate($request),
            'statistics' => $this->read->statistics($request),
            'filters' => [
                'q' => $request->string('q')->trim()->value(),
                'status' => $request->string('status')->trim()->value(),
                'health' => $request->string('health')->trim()->value(),
                'agency' => $request->string('agency')->trim()->value(),
                'assignment' => $request->string('assignment')->trim()->value(),
            ],
            ...$this->read->references(),
            'users' => $this->userCandidates(),
            'permissions' => [
                'canCreate' => Gate::allows('create', TerminalP501::class),
                'canManage' => Gate::allows('update', new TerminalP501),
            ],
        ]);
    }

    public function show(Request $request, TerminalP501 $terminal): Response
    {
        Gate::authorize('view', $terminal);

        $detail = $this->read->detail($terminal);

        return Inertia::render('terminals/detail', [
            'terminal' => $detail['terminal'],
            'assignmentHistory' => $detail['assignment_history'],
            'sessions' => $detail['sessions'],
            'history' => $this->read->history($terminal, $request),
            'historyTypes' => $this->read->historyTypes(),
            'historyFilters' => [
                'event_type' => $request->string('event_type')->trim()->value(),
                'from' => $request->string('from')->trim()->value(),
                'to' => $request->string('to')->trim()->value(),
            ],
            ...$this->read->references(),
            'users' => $this->userCandidates(),
            'permissions' => [
                'canManage' => Gate::allows('update', $terminal),
            ],
        ]);
    }

    /**
     * @return array<int, array{uuid: string, name: string, agency: array{uuid: string, name: string}|null, role: array{code: string, label: string, category: string}|null}>
     */
    private function userCandidates(): array
    {
        return User::query()
            ->where('status', 'active')
            ->role([
                SystemRole::BusDriver->value,
                SystemRole::Conductor->value,
                SystemRole::AgencyAgent->value,
                SystemRole::AgencyManager->value,
            ])
            ->with(['agency:id,uuid,name', 'roles'])
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'agency_id'])
            ->map(function (User $user): array {
                $role = $user->roles->first();

                return [
                    'uuid' => $user->uuid,
                    'name' => $user->name,
                    'agency' => $user->agency ? [
                        'uuid' => $user->agency->uuid,
                        'name' => $user->agency->name,
                    ] : null,
                    'role' => $role ? [
                        'code' => $role->name,
                        'label' => SystemRole::tryFrom($role->name)?->translatedLabel()
                            ?? $role->label
                            ?? $role->name,
                        'category' => $role->category->value,
                    ] : null,
                ];
            })
            ->values()
            ->all();
    }
}
