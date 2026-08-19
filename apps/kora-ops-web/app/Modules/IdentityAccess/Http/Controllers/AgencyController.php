<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Actions\ChangeAgencyStatusAction;
use App\Modules\IdentityAccess\Actions\CreateAgencyAction;
use App\Modules\IdentityAccess\Actions\UpdateAgencyAction;
use App\Modules\IdentityAccess\Data\AgencyStatus;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Http\Requests\ChangeAgencyStatusRequest;
use App\Modules\IdentityAccess\Http\Requests\StoreAgencyRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateAgencyRequest;
use App\Modules\IdentityAccess\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Agency::class);

        $query = Agency::query()
            ->with('head')
            ->orderBy('name');

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(code) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(city) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(country) LIKE ?', [$term]);
            });
        }

        $status = $request->string('status')->trim()->value();
        if ($status !== '' && in_array($status, array_column(AgencyStatus::cases(), 'value'), true)) {
            $query->where('status', $status);
        }

        $agencies = $query->paginate(15)->through(fn (Agency $agency): array => $this->serializeAgency($agency));

        $headCandidates = User::query()
            ->role([SystemRole::AgencyManager->value, SystemRole::AgencyAgent->value])
            ->orderBy('name')
            ->get()
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();

        return inertia('agencies', [
            'agencies' => $agencies,
            'statuses' => AgencyStatus::options(),
            'headCandidates' => $headCandidates,
            'permissions' => [
                'canCreate' => Gate::allows('create', Agency::class),
                'canUpdate' => Gate::allows('update', new Agency),
                'canDeactivate' => Gate::allows('deactivate', new Agency),
            ],
        ]);
    }

    public function store(StoreAgencyRequest $request, CreateAgencyAction $createAgency): RedirectResponse
    {
        $createAgency->execute($request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agence créée avec succès.')]);

        return to_route('agencies.index');
    }

    public function update(
        UpdateAgencyRequest $request,
        Agency $agency,
        UpdateAgencyAction $updateAgency,
    ): RedirectResponse {
        $updateAgency->execute($agency, $request->payload());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Agence mise à jour avec succès.')]);

        return to_route('agencies.index');
    }

    public function changeStatus(
        ChangeAgencyStatusRequest $request,
        Agency $agency,
        ChangeAgencyStatusAction $changeAgencyStatus,
    ): RedirectResponse {
        $changeAgencyStatus->execute($agency, $request->status());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Statut de l’agence mis à jour.')]);

        return to_route('agencies.index');
    }

    /**
     * @return array{uuid: string, code: string, name: string, city: string, country: string, headUserId: int|null, headName: string|null, status: string, createdAt: string|null, updatedAt: string|null}
     */
    private function serializeAgency(Agency $agency): array
    {
        return [
            'uuid' => $agency->uuid,
            'code' => $agency->code,
            'name' => $agency->name,
            'city' => $agency->city,
            'country' => $agency->country,
            'headUserId' => $agency->head_user_id,
            'headName' => $agency->head?->name,
            'status' => AgencyStatus::from((string) $agency->getRawOriginal('status'))->value,
            'createdAt' => $agency->created_at?->toISOString(),
            'updatedAt' => $agency->updated_at?->toISOString(),
        ];
    }
}
