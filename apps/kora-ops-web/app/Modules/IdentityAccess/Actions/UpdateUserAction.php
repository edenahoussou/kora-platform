<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Http\Requests\UserRoleResolver;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UpdateUserAction
{
    public function __construct(
        private readonly DeliverInitialWebAccessAction $deliverInitialWebAccess,
        private readonly UserRoleResolver $roleResolver,
        private readonly PassengerIdentityResolver $identityResolver,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string|null, country_code: string|null, birth_date: string|null, status: string, agency_uuid: string|null, kora_point_organization_uuid: string|null, role: string}  $attributes
     */
    public function execute(User $user, array $attributes, User $actor): User
    {
        $before = [
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => $user->status->value,
            'agency_uuid' => $user->agency?->uuid,
            'role' => $user->roles->first()?->name,
        ];

        $updated = DB::transaction(function () use ($user, $attributes, $actor): User {
            $user = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $role = $this->roleResolver->resolve((string) $attributes['role']);
            $agency = $this->resolveAgency($attributes['agency_uuid'] ?? null);
            $organization = $this->resolveKoraPointOrganization($attributes['kora_point_organization_uuid'] ?? null);
            $this->assertAssignment($role->category, $agency !== null);

            $emailChanged = $user->email !== $attributes['email'];

            $user->forceFill([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $this->identityResolver->normalizePhone($attributes['phone'] ?? null, $attributes['country_code'] ?? null),
                'country_code' => $attributes['country_code'] ?? null,
                'birth_date' => $attributes['birth_date'] ?? null,
                'status' => $attributes['status'],
                'agency_id' => $agency?->getKey(),
                'kora_point_organization_id' => $organization?->getKey(),
                'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            ])->save();

            $user->syncRoles([$role->name]);

            if ($user->initial_web_access_pending && $user->status === UserStatus::Active) {
                $temporaryPassword = Str::random(32);
                $user->forceFill([
                    'password' => $temporaryPassword,
                    'must_change_password' => true,
                    'password_changed_at' => null,
                    'initial_web_access_pending' => false,
                ])->save();
                $this->deliverInitialWebAccess->execute($user, $actor, $temporaryPassword);
            }

            return $user->refresh()->load(['agency', 'koraPointOrganization', 'roles']);
        });

        activity('identity-access')
            ->performedOn($updated)
            ->causedBy($actor)
            ->withProperties([
                'before' => $before,
                'after' => [
                    'email' => $updated->email,
                    'phone' => $updated->phone,
                    'status' => $updated->status->value,
                    'agency_uuid' => $updated->agency?->uuid,
                    'role' => $updated->roles->first()?->name,
                ],
            ])
            ->log('user_updated');

        return $updated;
    }

    private function resolveAgency(?string $uuid): ?Agency
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return Agency::query()->where('uuid', $uuid)->firstOrFail();
    }

    private function resolveKoraPointOrganization(?string $uuid): ?KoraPointOrganization
    {
        if ($uuid === null || $uuid === '') {
            return null;
        }

        return KoraPointOrganization::query()->where('uuid', $uuid)->where('status', 'active')->firstOrFail();
    }

    private function assertAssignment(?RoleCategory $category, bool $hasAgency): void
    {
        if ($category !== null && in_array($category, [RoleCategory::Agency, RoleCategory::Terrain], true) && ! $hasAgency) {
            abort(422, 'Une agence est obligatoire pour ce rôle.');
        }

        if ($category === RoleCategory::Central && $hasAgency) {
            abort(422, 'Un rôle central ne peut pas être rattaché à une agence.');
        }
    }
}
