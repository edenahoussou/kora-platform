<?php

namespace App\Modules\IdentityAccess\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\IdentityAccess\Actions\ArchiveUserAction;
use App\Modules\IdentityAccess\Actions\CreateUserAction;
use App\Modules\IdentityAccess\Actions\RequestUserPinResetAction;
use App\Modules\IdentityAccess\Actions\ResetUserPasswordAction;
use App\Modules\IdentityAccess\Actions\RestoreUserAction;
use App\Modules\IdentityAccess\Actions\UnlockUserAction;
use App\Modules\IdentityAccess\Actions\UpdateUserAction;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Http\Requests\StoreUserRequest;
use App\Modules\IdentityAccess\Http\Requests\UpdateUserRequest;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $query = User::query()->with(['agency', 'roles']);
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

        $users = $query
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => $this->serializeUser($user));

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
            'permissions' => [
                'canManage' => Gate::allows('create', User::class),
            ],
        ]);
    }

    public function show(string $user): Response
    {
        $target = User::withTrashed()->with(['agency', 'roles'])->where('uuid', $user)->firstOrFail();
        Gate::authorize('view', $target);

        return Inertia::render('users', [
            'user' => $this->serializeUser($target),
            'permissions' => [
                'canManage' => Gate::allows('update', $target),
            ],
        ]);
    }

    public function store(StoreUserRequest $request, CreateUserAction $createUser): RedirectResponse
    {
        $result = $createUser->execute($request->payload(), $request->user(), $request);

        Inertia::flash('temporaryPassword', $result['temporary_password']);
        Inertia::flash('otpChallengeUuid', $result['otp_challenge_uuid']);
        Inertia::flash('toast', ['type' => 'success', 'message' => __('Utilisateur créé avec succès.')]);

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

    public function resetPassword(User $user, ResetUserPasswordAction $resetPassword): RedirectResponse
    {
        Gate::authorize('resetPassword', $user);
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
                'label' => $role->label ?: (string) __('system_role.'.$role->name),
                'category' => $role->category->value,
                'isSystem' => (bool) $role->is_system,
                'isActive' => (bool) $role->is_active,
            ])
            ->values()
            ->all());
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        $role = $user->roles->first();

        return [
            'uuid' => $user->uuid,
            'name' => $user->name,
            'email' => $user->email,
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
            'role' => $role ? [
                'code' => $role->name,
                'label' => $role->label ?: __('system_role.'.$role->name),
                'category' => $role->category->value,
            ] : null,
        ];
    }
}
