<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateUserAction;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRemittanceLine;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KoraPointManagementService
{
    public function __construct(
        private readonly KoraPointAccess $access,
        private readonly KoraPointLedgerService $ledger,
        private readonly CreateUserAction $createUser,
    ) {}

    /** @param array<string, mixed> $data */
    /**
     * Creates or updates a point inside a transaction; auto-generates a unique agency code when absent.
     *
     * @param  array<string, mixed>  $data
     */
    public function savePoint(User $actor, array $data, ?KoraPoint $point = null): KoraPoint
    {
        $this->access->authorize($actor, 'kora-points.manage');

        return DB::transaction(function () use ($actor, $data, $point): KoraPoint {
            $organization = isset($data['organization_uuid']) ? KoraPointOrganization::query()->where('uuid', $data['organization_uuid'])->lockForUpdate()->firstOrFail() : null;
            if (empty($data['code'])) {
                $cityPrefix = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) ($data['city'] ?? 'KP')), 0, 3));
                if ($cityPrefix === '') {
                    $cityPrefix = 'KP';
                }
                do {
                    $candidateCode = sprintf('KP-%s-%04d', $cityPrefix, random_int(1000, 9999));
                } while (Agency::query()->where('code', $candidateCode)->exists());
                $data['code'] = $candidateCode;
            }
            if (empty($data['country'])) {
                $data['country'] = 'BJ';
            }
            if ($point !== null) {
                $point = KoraPoint::query()->lockForUpdate()->whereKey($point->getKey())->firstOrFail();
                if (array_key_exists('organization_uuid', $data) && $point->organization_id !== $organization?->getKey()) {
                    $pending = $point->sales()->whereHas('refunds', fn ($query) => $query->whereNotIn('status', ['confirmed', 'rejected']))->exists();
                    $claimed = KoraPointRemittanceLine::query()->whereNotNull('claimed_sale_id')->whereHas('sale', fn ($query) => $query->where('point_id', $point->getKey()))->exists();
                    if ($this->ledger->balance($point) !== 0 || $pending || $claimed) {
                        throw ValidationException::withMessages(['organization_uuid' => 'Soldez les encours et opérations en cours avant de changer le rattachement.']);
                    }
                }
                $point->agency->update(Arr::only($data, ['code', 'name', 'city', 'country']));
            } else {
                $agency = Agency::query()->create(Arr::only($data, ['code', 'name', 'city', 'country']) + ['status' => 'active', 'kind' => 'partner']);
                $point = new KoraPoint(['agency_id' => $agency->getKey(), 'status' => 'active']);
            }
            $before = $point->exists ? $point->getAttributes() : [];
            $point->fill(Arr::only($data, ['responsible_name', 'phone', 'address', 'latitude', 'longitude', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar']));
            if (array_key_exists('organization_uuid', $data)) {
                $point->organization_id = $organization?->getKey();
            }
            $point->save();
            $this->syncLogo($actor, $point, $data);
            $this->audit($actor, $point, 'point.saved', $before);

            $email = isset($data['email']) ? trim((string) $data['email']) : null;
            if (! empty($email)) {
                $this->createUser->execute([
                    'name' => (string) ($point->responsible_name ?: ($data['responsible_name'] ?? 'Agent')),
                    'email' => strtolower($email),
                    'phone' => $point->phone ?: ($data['phone'] ?? null),
                    'country_code' => (string) $data['country'],
                    'birth_date' => null,
                    'status' => 'active',
                    'agency_uuid' => $point->agency->uuid,
                    'role' => 'kora_point_agent',
                ], $actor, request());
            }

            return $point->refresh();
        }, 3);
    }

    /** @param array<string, mixed> $data */
    /**
     * Creates or updates a partner organization and optionally batches its points atomically.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveOrganization(User $actor, array $data, ?KoraPointOrganization $organization = null): KoraPointOrganization
    {
        $this->access->authorize($actor, 'kora-points.manage');

        return DB::transaction(function () use ($actor, $data, $organization): KoraPointOrganization {
            $creating = $organization === null;
            if (! $creating && ! empty($data['points'])) {
                throw ValidationException::withMessages(['points' => 'Les bureaux se rattachent individuellement depuis leur fiche.']);
            }
            if ($creating && empty($data['code'])) {
                $data['code'] = $this->generateOrganizationCode((string) ($data['name'] ?? 'ORG'));
            }
            $organization = $creating ? new KoraPointOrganization(['status' => 'active']) : KoraPointOrganization::query()->lockForUpdate()->whereKey($organization->getKey())->firstOrFail();
            $before = $organization->getAttributes();
            $organization->fill(Arr::only($data, ['code', 'name', 'phone', 'address', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar']));
            $organization->save();
            $this->syncLogo($actor, $organization, $data);
            foreach ($data['points'] ?? [] as $pointData) {
                $this->savePoint($actor, $pointData + ['organization_uuid' => $organization->uuid]);
            }
            foreach ($data['members'] ?? [] as $memberData) {
                $result = $this->createUser->execute([
                    'name' => trim((string) $memberData['name']),
                    'email' => strtolower(trim((string) $memberData['email'])),
                    'phone' => ! empty($memberData['phone']) ? trim((string) $memberData['phone']) : null,
                    'country_code' => $data['country'] ?? 'BJ',
                    'birth_date' => null,
                    'status' => 'active',
                    'agency_uuid' => null,
                    'role' => (string) $memberData['role'],
                ], $actor, request());

                $memberUser = $result['user'];
                $memberUser->forceFill(['kora_point_organization_id' => $organization->getKey()])->save();
                activity('kora-points')
                    ->performedOn($memberUser)
                    ->causedBy($actor)
                    ->withProperties(['role' => $memberData['role'], 'subject_uuid' => $organization->uuid])
                    ->log('partner.assigned');
            }
            $this->audit($actor, $organization, 'organization.saved', $before);

            return $organization;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    /**
     * Persists validated financial terms (commission, ceiling, calendar) on a point or organization.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTerms(User $actor, KoraPoint|KoraPointOrganization $subject, array $data): void
    {
        $this->access->authorize($actor, 'kora-points.terms');
        $values = Validator::make($data, KoraPointTermsService::rules())->validate();
        DB::transaction(function () use ($actor, $subject, $values): void {
            $locked = $subject->newQuery()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();
            $before = $locked->getAttributes();
            $locked->fill(Arr::only($values, ['commission_basis_points', 'ceiling_amount', 'settlement_calendar']))->save();
            $this->audit($actor, $locked, 'terms.updated', $before);
        });
    }

    /**
     * Applies a lifecycle status change (active, suspended, closed) on a point or organization.
     */
    public function changeStatus(User $actor, KoraPoint|KoraPointOrganization $subject, string $status): void
    {
        $this->access->authorize($actor, 'kora-points.manage');
        Validator::make(['status' => $status], ['status' => 'required|in:active,suspended,closed'])->validate();
        DB::transaction(function () use ($actor, $subject, $status): void {
            $locked = $subject->newQuery()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();
            $before = $locked->getAttributes();
            $locked->update(['status' => $status]);
            $this->audit($actor, $locked, 'status.updated', $before);
        });
    }

    /**
     * Binds a partner user to a point or organization with the matching role, rejecting internal accounts.
     */
    public function assign(User $actor, User $user, KoraPoint|KoraPointOrganization $subject, string $role): void
    {
        $this->access->authorize($actor, 'users.manage');
        $this->access->authorize($actor, 'kora-points.manage');
        $allowed = $subject instanceof KoraPoint ? ['kora_point_agent'] : ['kora_point_supervisor', 'kora_point_treasurer'];
        if (! in_array($role, $allowed, true)) {
            throw ValidationException::withMessages(['role' => 'Rôle incompatible avec cette affectation.']);
        }
        DB::transaction(function () use ($actor, $user, $subject, $role): void {
            $locked = User::query()->lockForUpdate()->whereKey($user->getKey())->firstOrFail();
            if ($locked->hasAnyRole(SystemRole::internalRoles())) {
                throw ValidationException::withMessages(['role' => 'Désaffectez d’abord ce compte de son rôle interne.']);
            }
            $before = ['agency_id' => $locked->agency_id, 'organization_id' => $locked->kora_point_organization_id, 'roles' => $locked->getRoleNames()->all()];
            $locked->forceFill(['agency_id' => $subject instanceof KoraPoint ? $subject->agency_id : null, 'kora_point_organization_id' => $subject instanceof KoraPointOrganization ? $subject->getKey() : null])->save();
            $locked->syncRoles([$role]);
            activity('kora-points')->performedOn($locked)->causedBy($actor)->withProperties(['before' => $before, 'role' => $role, 'subject_uuid' => $subject->uuid])->log('partner.assigned');
        });
    }

    /** @param array<string, mixed> $data */
    /**
     * Replaces or clears the single-file logo when the payload carries one.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncLogo(User $actor, KoraPoint|KoraPointOrganization $subject, array $data): void
    {
        $logo = $data['logo'] ?? null;
        $removing = filter_var($data['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (! $logo instanceof UploadedFile && ! $removing) {
            return;
        }
        unset($subject->media);
        if ($logo instanceof UploadedFile) {
            $media = $subject->addMedia($logo)->toMediaCollection('logo');
            activity('kora-points')->performedOn($subject)->causedBy($actor)->withProperties(['media_uuid' => $media->uuid])->log('logo.updated');
        } else {
            $subject->clearMediaCollection('logo');
            activity('kora-points')->performedOn($subject)->causedBy($actor)->log('logo.removed');
        }
    }

    /** @param array<string, mixed> $before */
    private function audit(User $actor, KoraPoint|KoraPointOrganization $subject, string $event, array $before): void
    {
        activity('kora-points')->performedOn($subject)->causedBy($actor)->withProperties(['before' => $before, 'after' => $subject->getAttributes()])->log($event);
    }

    /** Generates a unique business code from an organization name (e.g. LPB-01). */
    private function generateOrganizationCode(string $name): string
    {
        $words = preg_split('/[\s\-_\'\.]+/u', trim($name)) ?: [];
        $words = array_values(array_filter($words, fn (string $w): bool => $w !== ''));
        $prefix = '';
        foreach ($words as $word) {
            $clean = preg_replace('/[^A-Za-z0-9]/', '', $word);
            if ($clean !== '') {
                $prefix .= strtoupper(substr($clean, 0, 1));
            }
        }
        if (strlen($prefix) < 3) {
            $cleanName = strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $name));
            $prefix = substr($cleanName.'ORG', 0, 4);
        }
        $prefix = substr($prefix, 0, 6);
        $counter = 1;
        do {
            $candidate = sprintf('%s-%02d', $prefix, $counter);
            $counter++;
        } while (KoraPointOrganization::query()->where('code', $candidate)->exists());

        return $candidate;
    }
}
