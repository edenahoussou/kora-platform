<?php

namespace App\Modules\KoraPoints\Services;

use App\Models\User;
use App\Modules\IdentityAccess\Actions\CreateUserAction;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRemittanceLine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class KoraPointManagementService
{
    private const SUPPORTED_COUNTRIES = ['BJ', 'TG', 'CI'];

    public function __construct(
        private readonly KoraPointAccess $access,
        private readonly KoraPointLedgerService $ledger,
        private readonly CreateUserAction $createUser,
        private readonly KoraPointDocumentService $documents,
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

        if ($this->access->isOrganizationPartner($actor)) {
            if (! $this->access->isOrganizationSupervisor($actor)) {
                throw new AuthorizationException;
            }

            if ($point !== null) {
                $this->access->requirePoint($actor, $point);
            }
        }

        return DB::transaction(function () use ($actor, $data, $point): KoraPoint {
            $creatingPoint = $point === null;
            $currentPoint = $point === null
                ? null
                : KoraPoint::query()->whereKey($point->getKey())->firstOrFail();
            $originalOrganizationId = $currentPoint?->organization_id;
            $targetOrganizationId = $originalOrganizationId;

            if (array_key_exists('organization_uuid', $data)) {
                $targetOrganizationId = filled($data['organization_uuid'])
                    ? KoraPointOrganization::query()->where('uuid', $data['organization_uuid'])->value('id')
                    : null;

                if (filled($data['organization_uuid']) && $targetOrganizationId === null) {
                    throw ValidationException::withMessages(['organization_uuid' => 'L’organisation sélectionnée est introuvable.']);
                }
            }

            $organizationIds = collect([$originalOrganizationId, $targetOrganizationId])
                ->filter()
                ->unique()
                ->sort()
                ->values();
            $organizations = $organizationIds->isEmpty()
                ? collect()
                : KoraPointOrganization::query()
                    ->whereIn('id', $organizationIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
            $organization = $targetOrganizationId === null ? null : $organizations->get($targetOrganizationId);

            if ($targetOrganizationId !== null && $organization === null) {
                throw ValidationException::withMessages(['organization_uuid' => 'L’organisation sélectionnée est introuvable.']);
            }

            if ($organization !== null && (
                ($data['logo'] ?? null) instanceof UploadedFile
                || filter_var($data['remove_logo'] ?? false, FILTER_VALIDATE_BOOLEAN)
            )) {
                throw ValidationException::withMessages([
                    'logo' => 'Le logo de ce Point est géré depuis la fiche de son organisation.',
                ]);
            }

            $point = $currentPoint === null
                ? null
                : KoraPoint::query()->lockForUpdate()->whereKey($currentPoint->getKey())->firstOrFail();

            if ($point !== null && $point->organization_id !== $originalOrganizationId) {
                throw ValidationException::withMessages(['organization_uuid' => 'Le rattachement a changé. Rechargez la fiche du Point avant de la modifier.']);
            }

            if (
                $point !== null
                && $originalOrganizationId !== null
                && ! $this->access->isOrganizationPartner($actor)
                && array_key_exists('ceiling_amount', $data)
                && (int) $data['ceiling_amount'] !== (int) $point->ceiling_amount
            ) {
                throw ValidationException::withMessages([
                    'ceiling_amount' => 'L’allocation d’un Point rattaché se gère depuis l’espace de son organisation.',
                ]);
            }

            if ($this->access->isOrganizationPartner($actor)) {
                $organizationId = $actor->kora_point_organization_id;

                if ($organizationId === null || $targetOrganizationId !== $organizationId || ($point !== null && $originalOrganizationId !== $organizationId)) {
                    throw new AuthorizationException;
                }

                if ($point !== null) {
                    $data['code'] = $point->agency()->value('code');
                    $data['responsible_name'] = $point->responsible_name;
                    unset($data['email'], $data['document_file'], $data['document_title']);
                } elseif (array_key_exists('document_file', $data)) {
                    throw ValidationException::withMessages(['document_file' => 'Les documents des Points rattachés sont gérés sur le profil de l’organisation.']);
                }
            }

            if ($creatingPoint && blank($data['email'] ?? null)) {
                throw ValidationException::withMessages([
                    'email' => 'L’e-mail est obligatoire pour créer le compte web de l’agent du Point.',
                ]);
            }

            $existingCountry = $point?->agency()->value('country');
            $country = strtoupper((string) ($data['country'] ?? $existingCountry ?? 'BJ'));
            if (! in_array($country, self::SUPPORTED_COUNTRIES, true)) {
                throw ValidationException::withMessages(['country' => 'Choisissez un pays actuellement pris en charge par KORA POINT.']);
            }

            if ($organization !== null) {
                if ($organization->status !== 'active') {
                    throw ValidationException::withMessages(['organization_uuid' => 'Un Point ne peut être rattaché qu’à une organisation active.']);
                }

                if ($country !== $organization->country) {
                    throw ValidationException::withMessages(['country' => 'Un Point rattaché doit utiliser le pays de son organisation.']);
                }

                if ($organization->commission_basis_points === null || $organization->ceiling_amount === null || $organization->settlement_calendar === null) {
                    throw ValidationException::withMessages(['organization_uuid' => 'Configurez les conditions commerciales de l’organisation avant de lui rattacher un Point.']);
                }

                $allocation = (int) ($data['ceiling_amount'] ?? ($point === null ? 0 : $point->ceiling_amount));
                if ($allocation < 1) {
                    throw ValidationException::withMessages(['ceiling_amount' => 'L’allocation du Point doit être supérieure à zéro.']);
                }

                if ($point !== null && $this->ledger->balance($point) > $allocation) {
                    throw ValidationException::withMessages(['ceiling_amount' => 'L’allocation ne peut pas être inférieure à l’encours actuel du Point.']);
                }

                if ($this->reservedPointAllocations($organization, $point?->getKey()) + $allocation > (int) $organization->ceiling_amount) {
                    throw ValidationException::withMessages(['ceiling_amount' => 'La somme des allocations dépasserait le plafond global de l’organisation.']);
                }
            }

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
            if ($point !== null && $originalOrganizationId !== $targetOrganizationId) {
                $pending = $point->sales()->whereHas('refunds', fn ($query) => $query->whereNotIn('status', ['confirmed', 'rejected']))->exists();
                $claimed = KoraPointRemittanceLine::query()->whereNotNull('claimed_sale_id')->whereHas('sale', fn ($query) => $query->where('point_id', $point->getKey()))->exists();

                if ($this->ledger->balance($point) !== 0 || $pending || $claimed) {
                    throw ValidationException::withMessages(['organization_uuid' => 'Soldez l’encours et les opérations en cours avant de changer le rattachement.']);
                }
            }

            $data['country'] = $country;
            if (array_key_exists('phone', $data)) {
                $data['phone'] = filled($data['phone']) ? trim((string) $data['phone']) : null;
            }
            $agencyData = Arr::only($data, ['code', 'name', 'city', 'department', 'country']);
            if ($point !== null) {
                $point->agency()->firstOrFail()->update($agencyData);
            } else {
                $agency = Agency::query()->create($agencyData + ['status' => 'active', 'kind' => 'partner']);
                $point = new KoraPoint(['agency_id' => $agency->getKey(), 'status' => 'active']);
            }
            $before = $point->exists ? $point->getAttributes() : [];
            $terms = Arr::only($data, ['commission_basis_points', 'ceiling_amount', 'settlement_calendar']);
            if ($organization !== null) {
                $terms['commission_basis_points'] = null;
                $terms['settlement_calendar'] = null;
                $terms['ceiling_amount'] = (int) ($data['ceiling_amount'] ?? $point->ceiling_amount);
            }
            $point->fill(Arr::only($data, ['responsible_name', 'phone', 'address', 'latitude', 'longitude']) + $terms);
            $point->organization_id = $organization?->getKey();
            $point->save();
            $this->syncLogo($actor, $point, $data);
            $this->audit($actor, $point, 'point.saved', $before);

            $email = isset($data['email']) ? strtolower(trim((string) $data['email'])) : null;
            if ($creatingPoint) {
                $this->createUser->execute([
                    'name' => (string) ($point->responsible_name ?: ($data['responsible_name'] ?? 'Agent')),
                    'email' => strtolower($email),
                    'phone' => $point->phone,
                    'country_code' => (string) $data['country'],
                    'birth_date' => null,
                    'status' => 'active',
                    'agency_uuid' => $point->agency->uuid,
                    'kora_point_organization_uuid' => null,
                    'role' => 'kora_point_agent',
                ], $actor, request());
            }

            $documentFile = $data['document_file'] ?? null;
            if ($documentFile instanceof UploadedFile) {
                $this->documents->store($actor, $point, [
                    'kind' => 'other',
                    'title' => (string) ($data['document_title'] ?? $documentFile->getClientOriginalName()),
                ], $documentFile);
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
        $this->access->authorizeInternal($actor, 'kora-points.manage');

        return DB::transaction(function () use ($actor, $data, $organization): KoraPointOrganization {
            $creating = $organization === null;
            if (! $creating && ! empty($data['points'])) {
                throw ValidationException::withMessages(['points' => 'Les bureaux se rattachent individuellement depuis leur fiche.']);
            }
            if ($creating && empty($data['code'])) {
                $data['code'] = $this->generateOrganizationCode((string) ($data['name'] ?? 'ORG'));
            }
            $organization = $creating ? new KoraPointOrganization(['status' => 'active']) : KoraPointOrganization::query()->lockForUpdate()->whereKey($organization->getKey())->firstOrFail();

            if (! in_array((string) ($data['country'] ?? ''), self::SUPPORTED_COUNTRIES, true)) {
                throw ValidationException::withMessages(['country' => 'Choisissez un pays actuellement pris en charge par KORA POINT.']);
            }

            if (! $creating && $organization->country !== $data['country'] && $organization->points()->exists()) {
                throw ValidationException::withMessages(['country' => 'Le pays ne peut pas changer tant que des Points sont rattachés à cette organisation.']);
            }

            if ($this->reservedPointAllocations($organization) > (int) ($data['ceiling_amount'] ?? 0)) {
                throw ValidationException::withMessages(['ceiling_amount' => 'Le plafond global ne peut pas être inférieur aux allocations déjà réservées.']);
            }

            $before = $organization->getAttributes();
            $organization->fill(Arr::only($data, ['code', 'name', 'country', 'registration_identifier', 'tax_identifier', 'phone', 'address', 'commission_basis_points', 'ceiling_amount', 'settlement_calendar']));
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
                    'country_code' => $organization->country,
                    'birth_date' => null,
                    'status' => 'active',
                    'agency_uuid' => null,
                    'kora_point_organization_uuid' => $organization->uuid,
                    'role' => (string) $memberData['role'],
                ], $actor, request());

                $memberUser = $result['user'];
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
        $this->access->authorizeInternal($actor, 'kora-points.terms');
        $values = Validator::make($data, KoraPointTermsService::rules())->validate();
        DB::transaction(function () use ($actor, $subject, $values): void {
            if ($subject instanceof KoraPointOrganization) {
                $locked = KoraPointOrganization::query()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();
                foreach (['commission_basis_points', 'ceiling_amount', 'settlement_calendar'] as $requiredTerm) {
                    if (! array_key_exists($requiredTerm, $values) || $values[$requiredTerm] === null) {
                        throw ValidationException::withMessages([$requiredTerm => 'Les conditions de l’organisation doivent être complètes.']);
                    }
                }

                if ($this->reservedPointAllocations($locked) > (int) $values['ceiling_amount']) {
                    throw ValidationException::withMessages(['ceiling_amount' => 'Le plafond global ne peut pas être inférieur aux allocations déjà réservées.']);
                }
            } else {
                $current = KoraPoint::query()->whereKey($subject->getKey())->firstOrFail();
                $organization = $current->organization_id === null
                    ? null
                    : KoraPointOrganization::query()->lockForUpdate()->whereKey($current->organization_id)->firstOrFail();
                $locked = KoraPoint::query()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();

                if ($organization !== null) {
                    if (
                        array_key_exists('ceiling_amount', $values)
                        && (int) $values['ceiling_amount'] !== (int) $locked->ceiling_amount
                    ) {
                        throw ValidationException::withMessages([
                            'ceiling_amount' => 'L’allocation d’un Point rattaché se gère depuis l’espace de son organisation.',
                        ]);
                    }

                    if (array_key_exists('commission_basis_points', $values) && $values['commission_basis_points'] !== null && (int) $values['commission_basis_points'] !== (int) $organization->commission_basis_points) {
                        throw ValidationException::withMessages(['commission_basis_points' => 'La commission est définie par l’organisation.']);
                    }

                    if (array_key_exists('settlement_calendar', $values) && $values['settlement_calendar'] !== null && $values['settlement_calendar'] !== $organization->settlement_calendar) {
                        throw ValidationException::withMessages(['settlement_calendar' => 'Le calendrier de reversement est défini par l’organisation.']);
                    }

                    $allocation = (int) ($values['ceiling_amount'] ?? $locked->ceiling_amount ?? 0);
                    if ($allocation < 1) {
                        throw ValidationException::withMessages(['ceiling_amount' => 'L’allocation du Point doit être supérieure à zéro.']);
                    }

                    if ($this->ledger->balance($locked) > $allocation) {
                        throw ValidationException::withMessages(['ceiling_amount' => 'L’allocation ne peut pas être inférieure à l’encours actuel du Point.']);
                    }

                    if ($this->reservedPointAllocations($organization, $locked->getKey()) + $allocation > (int) $organization->ceiling_amount) {
                        throw ValidationException::withMessages(['ceiling_amount' => 'La somme des allocations dépasserait le plafond global de l’organisation.']);
                    }

                    $values = ['ceiling_amount' => $allocation];
                }
            }

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
        if ($subject instanceof KoraPointOrganization) {
            $this->access->authorizeInternal($actor, 'kora-points.manage');
        } else {
            $this->access->authorize($actor, 'kora-points.manage');

            if ($this->access->isOrganizationPartner($actor)) {
                if (! $this->access->isOrganizationSupervisor($actor)) {
                    throw new AuthorizationException;
                }

                $this->access->requirePoint($actor, $subject);
            }
        }
        Validator::make(['status' => $status], ['status' => 'required|in:active,suspended,closed'])->validate();
        DB::transaction(function () use ($actor, $subject, $status): void {
            if ($subject instanceof KoraPoint) {
                $current = KoraPoint::query()->whereKey($subject->getKey())->firstOrFail();
                $organization = $current->organization_id === null
                    ? null
                    : KoraPointOrganization::query()->lockForUpdate()->whereKey($current->organization_id)->firstOrFail();
                $locked = KoraPoint::query()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();

                if ($organization !== null && $locked->status === 'closed' && $status !== 'closed' && $this->ledger->balance($locked) === 0) {
                    $allocation = (int) ($locked->ceiling_amount ?? 0);
                    if ($allocation < 1 || $this->reservedPointAllocations($organization, $locked->getKey()) + $allocation > (int) $organization->ceiling_amount) {
                        throw ValidationException::withMessages(['status' => 'Le plafond disponible de l’organisation ne permet pas de réactiver ce Point.']);
                    }
                }
            } else {
                $locked = KoraPointOrganization::query()->lockForUpdate()->whereKey($subject->getKey())->firstOrFail();

                if ($status === 'closed') {
                    $points = KoraPoint::query()
                        ->where('organization_id', $locked->getKey())
                        ->orderBy('id')
                        ->lockForUpdate()
                        ->get();

                    if ($points->contains(fn (KoraPoint $point): bool => $this->ledger->balance($point) !== 0)) {
                        throw ValidationException::withMessages([
                            'status' => 'Une organisation ne peut être clôturée que lorsque l’encours de tous ses Points est nul.',
                        ]);
                    }
                }
            }

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
        $this->access->authorizeInternal($actor, 'users.manage');
        $this->access->authorizeInternal($actor, 'kora-points.manage');
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

    /**
     * Creates a new active agent account for a point and issues its web access invitation.
     *
     * @param  array{name: string, email: string, phone?: string|null}  $data
     * @return array{user: User, temporary_password: string|null, otp_challenge_uuid: string|null, web_access_invitation: KoraPointWebAccessInvitation|null}
     */
    public function createAgent(User $actor, KoraPoint $point, array $data, Request $request): array
    {
        $this->access->authorizeInternal($actor, 'users.manage');
        $this->access->authorizeInternal($actor, 'kora-points.manage');

        $point->loadMissing('agency');

        $result = $this->createUser->execute([
            'name' => trim($data['name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => isset($data['phone']) && trim($data['phone']) !== '' ? trim($data['phone']) : null,
            'country_code' => $point->agency->country,
            'birth_date' => null,
            'status' => 'active',
            'agency_uuid' => $point->agency->uuid,
            'kora_point_organization_uuid' => null,
            'role' => 'kora_point_agent',
        ], $actor, $request);

        activity('kora-points')
            ->performedOn($result['user'])
            ->causedBy($actor)
            ->withProperties(['role' => 'kora_point_agent', 'subject_uuid' => $point->uuid])
            ->log('partner.assigned');

        return $result;
    }

    /**
     * Creates an active organization supervisor or treasurer and issues the existing web invitation.
     *
     * @param  array{name: string, email: string, phone?: string|null, role: string}  $data
     * @return array{user: User, temporary_password: string|null, otp_challenge_uuid: string|null, web_access_invitation: KoraPointWebAccessInvitation|null}
     */
    public function createOrganizationMember(User $actor, KoraPointOrganization $organization, array $data, Request $request): array
    {
        $this->access->authorizeInternal($actor, 'users.manage');
        $this->access->authorizeInternal($actor, 'kora-points.manage');

        if (! in_array($data['role'], ['kora_point_supervisor', 'kora_point_treasurer'], true)) {
            throw ValidationException::withMessages(['role' => 'Choisissez un rôle autorisé pour l’organisation.']);
        }

        return DB::transaction(function () use ($actor, $organization, $data, $request): array {
            $lockedOrganization = KoraPointOrganization::query()
                ->lockForUpdate()
                ->whereKey($organization->getKey())
                ->firstOrFail();

            if ($lockedOrganization->status !== 'active') {
                throw ValidationException::withMessages(['role' => 'Un membre ne peut être ajouté qu’à une organisation active.']);
            }

            return $this->createUser->execute([
                'name' => trim($data['name']),
                'email' => strtolower(trim($data['email'])),
                'phone' => isset($data['phone']) && trim($data['phone']) !== '' ? trim($data['phone']) : null,
                'country_code' => $lockedOrganization->country,
                'birth_date' => null,
                'status' => 'active',
                'agency_uuid' => null,
                'kora_point_organization_uuid' => $lockedOrganization->uuid,
                'role' => $data['role'],
            ], $actor, $request);
        }, 3);
    }

    private function reservedPointAllocations(KoraPointOrganization $organization, ?int $excludePointId = null): int
    {
        if (! $organization->exists) {
            return 0;
        }

        $points = $organization->points()->withSum('entries as allocation_exposure', 'amount');

        if ($excludePointId !== null) {
            $points->where('id', '!=', $excludePointId);
        }

        return (int) $points->get()->sum(function (KoraPoint $point): int {
            $exposure = (int) ($point->getAttribute('allocation_exposure') ?? 0);

            if ($point->status === 'closed' && $exposure === 0) {
                return 0;
            }

            return max(0, (int) ($point->ceiling_amount ?? 0));
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
