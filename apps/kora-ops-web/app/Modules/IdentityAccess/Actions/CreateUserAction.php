<?php

namespace App\Modules\IdentityAccess\Actions;

use App\Models\User;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use App\Modules\IdentityAccess\Data\RoleCategory;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Http\Requests\UserRoleResolver;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateUserAction
{
    public function __construct(
        private readonly DeliverInitialWebAccessAction $deliverInitialWebAccess,
        private readonly UserRoleResolver $roleResolver,
        private readonly PassengerIdentityResolver $identityResolver,
    ) {}

    /**
     * @param  array{name: string, email: string, phone: string|null, country_code: string|null, birth_date: string|null, status: string, agency_uuid: string|null, kora_point_organization_uuid: string|null, role: string}  $attributes
     * @return array{user: User, temporary_password: string|null, web_access_invitation: KoraPointWebAccessInvitation|null}
     */
    public function execute(array $attributes, User $actor): array
    {
        $temporaryPassword = Str::random(32);

        [$user, $role, $webAccessInvitation] = DB::transaction(function () use ($attributes, $temporaryPassword, $actor): array {
            $role = $this->roleResolver->resolve((string) $attributes['role']);
            $agency = $this->resolveAgency($attributes['agency_uuid'] ?? null);
            $organization = $this->resolveKoraPointOrganization($attributes['kora_point_organization_uuid'] ?? null);

            $this->assertAssignment($role->category, $agency !== null);

            $user = User::query()->create([
                'name' => $attributes['name'],
                'email' => $attributes['email'],
                'phone' => $this->identityResolver->normalizePhone($attributes['phone'] ?? null, $attributes['country_code'] ?? null),
                'country_code' => $attributes['country_code'] ?? null,
                'birth_date' => $attributes['birth_date'] ?? null,
                'status' => $attributes['status'],
                'agency_id' => $agency?->getKey(),
                'password' => $temporaryPassword,
                'must_change_password' => true,
            ]);

            $user->forceFill([
                'kora_point_organization_id' => $organization?->getKey(),
                'initial_web_access_pending' => $user->status === UserStatus::Suspended,
            ])->save();

            $user->syncRoles([$role->name]);

            $invitation = $user->status === UserStatus::Active
                ? $this->deliverInitialWebAccess->execute($user, $actor, $temporaryPassword)
                : null;

            return [$user, $role, $invitation];
        });

        activity('identity-access')
            ->performedOn($user)
            ->causedBy($actor)
            ->withProperties([
                'role' => $role->name,
                'agency_uuid' => $user->agency?->uuid,
                'status' => $user->status->value,
            ])
            ->log('user_created');

        return [
            'user' => $user->refresh()->load(['agency', 'roles']),
            'temporary_password' => $user->status === UserStatus::Active && ! $user->isKoraPointUser() ? $temporaryPassword : null,
            'web_access_invitation' => $webAccessInvitation?->refresh(),
        ];
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
