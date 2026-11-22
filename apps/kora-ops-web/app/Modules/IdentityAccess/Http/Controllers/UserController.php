<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Actions\ArchiveUserAction;
use App\Modules\IdentityAccess\Actions\CreateUserAction;
use App\Modules\IdentityAccess\Actions\IssueKoraPointWebAccessAction;
use App\Modules\IdentityAccess\Actions\RequestUserPinResetAction;
use App\Modules\IdentityAccess\Actions\ResetUserPasswordAction;
use App\Modules\IdentityAccess\Actions\RestoreUserAction;
use App\Modules\IdentityAccess\Actions\UnlockUserAction;
use App\Modules\IdentityAccess\Actions\UpdateUserAction;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Http\Requests\StoreUserRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateUserRequest;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        KoraPointWebAccessInvitation::query()
            ->where('status', KoraPointWebAccessInvitation::STATUS_SENT)
            ->where(function ($query): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', now());
            })
            ->update(['status' => KoraPointWebAccessInvitation::STATUS_EXPIRED]);

        $query = User::query()->with(['agency', 'koraPointOrganization', 'roles', 'latestKoraPointWebAccessInvitation']);
        $archived = $request->string('archived')->trim()->value();

        if ($archived === 'only') {
            $query->onlyTrashed();
        } elseif ($archived === 'with') {
            $query->withTrashed();
        }

        $search = $request->string('q')->trim()->value();
        if ($search !== '') {
            $term = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($term): void {
                $builder->whereRaw('LOWER(name) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$term]);
            });
        }

        $role = $request->string('role')->trim()->value();
        if ($role !== '') {
            $query->whereHas('roles', fn ($roles) => $roles->where('name', $role));
        }

        $agencyUuid = $request->string('agency')->trim()->value();
        if ($agencyUuid !== '') {
            $query->whereHas('agency', fn ($agencies) => $agencies->where('uuid', $agencyUuid));
        }

        $status = $request->string('status')->trim()->value();
        if (in_array($status, UserStatus::values(), true)) {
            $query->where('status', $status);
        }

        $paginator = $query
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $koraPoints = $this->koraPointsByAgency($paginator->getCollection());

        $users = $paginator->through(
            fn (User $user): array => $this->serializeUser($user, $koraPoints->get($user->agency_id)),
        );

        return Inertia::render('users', [
            'users' => $users,
            'filters' => [
                'q' => $search,
                'role' => $role,
                'agency' => $agencyUuid,
                'status' => $status,
                'archived' => $archived,
            ],
            'statuses' => UserStatus::values(),
            'roles' => $this->roleOptions(),
            'agencies' => Agency::query()
                ->orderBy('name')
                ->get(['uuid', 'code', 'name'])
                ->map(fn (Agency $agency): array => [
                    'uuid' => $agency->uuid,
                    'code' => $agency->code,
                    'name' => $agency->name,
                ])
                ->values()
                ->all(),
            'koraPointOrganizations' => KoraPointOrganization::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['uuid', 'code', 'name'])
                ->map(fn (KoraPointOrganization $organization): array => [
                    'uuid' => $organization->uuid,
                    'code' => $organization->code,
                    'name' => $organization->name,
                ])
                ->values()
                ->all(),
            'permissions' => [
                'canManage' => Gate::allows('create', User::class),
            ],
        ]);
    }

    public function show(string $user): Response
    {
        $target = User::withTrashed()->with(['agency', 'koraPointOrganization', 'roles'])->where('uuid', $user)->firstOrFail();
        Gate::authorize('view', $target);

        $koraPoint = $target->agency_id === null ? null : KoraPoint::query()
            ->where('agency_id', $target->agency_id)
            ->with(['agency', 'organization'])
            ->first();

        return Inertia::render('users', [
            'user' => $this->serializeUser($target, $koraPoint),
            'permissions' => [
                'canManage' => Gate::allows('update', $target),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $createUser): RedirectResponse
    {
        $result = $createUser->execute($request->payload(), $request->user());

        if ($result['temporary_password'] !== null) {
            Inertia::flash('temporaryPassword', $result['temporary_password']);
        }

        $message = $result['web_access_invitation'] === null
            ? ($result['temporary_password'] === null
                ? __('Utilisateur créé. Les accès seront envoyés après activation.')
                : __('Utilisateur créé. L’e-mail de bienvenue est en cours d’envoi.'))
            : $this->webAccessMessage($result['web_access_invitation']->status);

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return to_route('users');
    }

    public function update(UpdateUserRequest $request, User $user, UpdateUserAction $updateUser): RedirectResponse
    {
        $updateUser->execute($user, $request->payload(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utilisateur mis à jour avec succès.')]);

        return back();
    }

    public function destroy(User $user, ArchiveUserAction $archiveUser): RedirectResponse
    {
        Gate::authorize('delete', $user);
        $archiveUser->execute($user, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utilisateur archivé.')]);

        return back();
    }

    public function restore(string $user, RestoreUserAction $restoreUser): RedirectResponse
    {
        $target = User::withTrashed()->where('uuid', $user)->firstOrFail();
        Gate::authorize('restore', $target);
        $restoreUser->execute($target, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utilisateur restauré.')]);

        return back();
    }

    public function resetPassword(
        Request $request,
        User $user,
        ResetUserPasswordAction $resetPassword,
        IssueKoraPointWebAccessAction $issueKoraPointWebAccess,
    ): RedirectResponse {
        Gate::authorize('resetPassword', $user);

        if ($user->isKoraPointUser()) {
            $invitation = $issueKoraPointWebAccess->execute($user, $request->user())->refresh();

            Inertia::flash('toast', ['type' => 'success', 'message' => $this->webAccessMessage($invitation->status)]);

            return back();
        }

        $temporaryPassword = $resetPassword->execute($user, request()->user());

        Inertia::flash('temporaryPassword', $temporaryPassword);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Mot de passe temporaire généré.')]);

        return back();
    }

    public function resetPin(Request $request, User $user, RequestUserPinResetAction $resetPin): RedirectResponse
    {
        Gate::authorize('resetPin', $user);
        $challengeUuid = $resetPin->execute($user, $request->user(), $request);

        Inertia::flash('otpChallengeUuid', $challengeUuid);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Le code OTP de réinitialisation a été envoyé.')]);

        return back();
    }

    public function unlock(string $user, UnlockUserAction $unlockUser): RedirectResponse
    {
        $target = User::withTrashed()->where('uuid', $user)->firstOrFail();
        Gate::authorize('unlock', $target);
        $unlockUser->execute($target, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Compte déverrouillé.')]);

        return back();
    }

    /**
     * @return list<array{code: string, label: string, category: string, isSystem: bool, isActive: bool}>
     */
    private function roleOptions(): array
    {
        return array_values(Role::query()
            ->where('guard_name', 'web')
            ->where('is_active', true)
            ->orderBy('label')
            ->get()
            ->map(fn (Role $role): array => [
                'code' => $role->name,
                'label' => SystemRole::tryFrom($role->name)?->translatedLabel()
                    ?? $role->label
                    ?? $role->name,
                'category' => $role->category->value,
                'isSystem' => (bool) $role->is_system,
                'isActive' => (bool) $role->is_active,
            ])
            ->values()
            ->all());
    }

    /**
     * Points de vente des `kora_point_agent` de la page, indexés par `agency_id` (pas de N+1).
     *
     * @param  Collection<int, User>  $users
     * @return Collection<int, KoraPoint>
     */
    private function koraPointsByAgency(Collection $users): Collection
    {
        $agencyIds = $users
            ->filter(fn (User $user): bool => $user->roles->first()?->name === 'kora_point_agent' && $user->agency_id !== null)
            ->pluck('agency_id')
            ->all();

        if ($agencyIds === []) {
            return collect();
        }

        return KoraPoint::query()
            ->whereIn('agency_id', $agencyIds)
            ->with(['agency', 'organization'])
            ->get()
            ->keyBy('agency_id');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user, ?KoraPoint $koraPoint = null): array
    {
        $role = $user->roles->first();
        $webAccess = $user->latestKoraPointWebAccessInvitation;
        $sentAt = $webAccess?->sent_at === null ? null : Carbon::parse($webAccess->sent_at);
        $expiresAt = $webAccess?->expires_at === null ? null : Carbon::parse($webAccess->expires_at);

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->getFirstMediaUrl('avatar', 'thumb') ?: null,
            'phone' => $user->phone,
            'country_code' => $user->country_code,
            'birth_date' => $user->birth_date?->toDateString(),
            'status' => $user->status->value,
            'deleted_at' => $user->deleted_at?->toISOString(),
            'agency' => $user->agency ? [
                'uuid' => $user->agency->uuid,
                'code' => $user->agency->code,
                'name' => $user->agency->name,
            ] : null,
            'kora_point' => $koraPoint !== null && $role?->name === 'kora_point_agent' ? [
                'uuid' => $koraPoint->uuid,
                'code' => $koraPoint->agency->code,
                'name' => $koraPoint->agency->name,
                'organization_name' => $koraPoint->organization?->name,
            ] : null,
            'kora_point_organization' => $user->koraPointOrganization ? [
                'uuid' => $user->koraPointOrganization->uuid,
                'code' => $user->koraPointOrganization->code,
                'name' => $user->koraPointOrganization->name,
            ] : null,
            'role' => $role ? [
                'code' => $role->name,
                'label' => SystemRole::tryFrom($role->name)?->translatedLabel()
                    ?? $role->label
                    ?? $role->name,
                'category' => $role->category->value,
            ] : null,
            'web_access' => $webAccess ? [
                'status' => $webAccess->status === 'sent' && $expiresAt?->isPast()
                    ? 'expired'
                    : $webAccess->status,
                'delivery_channel' => $webAccess->delivery_channel,
                'sent_at' => $sentAt?->toISOString(),
                'expires_at' => $expiresAt?->toISOString(),
            ] : null,
        ];
    }

    private function webAccessMessage(string $status): string
    {
        if ($status === 'failed') {
            return 'L’e-mail d’accès web n’a pas pu être envoyé. Vous pouvez renvoyer l’accès depuis Utilisateurs.';
        }

        $delivery = $status === 'sent' ? 'envoyé' : 'en attente de livraison';

        return "Accès web initial {$delivery}. Le mot de passe n’est pas affiché dans OPS; consultez son état ou renvoyez l’accès depuis Utilisateurs.";
    }
}
