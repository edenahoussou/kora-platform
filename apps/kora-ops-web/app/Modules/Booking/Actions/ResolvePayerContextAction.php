<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\IdentityAccess\Contracts\PassengerIdentityResolver;
use Illuminate\Validation\ValidationException;

class ResolvePayerContextAction
{
    public function __construct(private readonly PassengerIdentityResolver $identityResolver) {}

    /** @param array{type?: string, phone?: string|null, name?: string|null, company_account_uuid?: string|null} $data */
    public function handle(?User $actor, array $data): PayerContext
    {
        $type = (string) ($data['type'] ?? 'personal');
        if ($type === 'personal') {
            $normalizedPhone = $this->identityResolver->normalizePhone($data['phone'] ?? null);
            if ($normalizedPhone === null && $actor?->phone !== null) {
                $normalizedPhone = $this->identityResolver->normalizePhone($actor->phone);
            }
            if ($normalizedPhone === null) {
                throw ValidationException::withMessages(['payer.phone' => 'Le téléphone du payeur est obligatoire.']);
            }

            $payer = $this->identityResolver->resolveOrCreateByPhone($normalizedPhone, $data['name'] ?? null);

            return PayerContext::personal($payer->getKey(), [
                'type' => 'personal',
                'display_name' => $payer->name,
                'phone' => $payer->phone,
                'account_uuid' => $payer->uuid,
                'captured_at' => now()->toISOString(),
            ]);
        }

        if ($actor?->isKoraPointUser()) {
            throw ValidationException::withMessages(['payer.type' => 'Les Points distribuent uniquement des ventes individuelles au public.']);
        }

        if ($type !== 'company' || $actor === null) {
            throw ValidationException::withMessages(['payer.type' => 'Le contexte de payeur est invalide.']);
        }

        $uuid = (string) ($data['company_account_uuid'] ?? '');
        $companyAccount = CompanyAccount::query()->where('uuid', $uuid)->first();
        if ($companyAccount === null || $companyAccount->status !== CompanyAccountStatus::Active) {
            throw ValidationException::withMessages(['payer.company_account_uuid' => 'Le compte entreprise est invalide ou inactif.']);
        }

        $isMember = $companyAccount->members()
            ->where('user_id', $actor->getKey())
            ->where('status', 'active')
            ->exists();
        $isOperator = $actor->can('counter-sales.create');
        if (! $isMember && ! $isOperator) {
            throw ValidationException::withMessages(['payer.company_account_uuid' => 'Vous n’êtes pas autorisé à utiliser ce compte entreprise.']);
        }

        $referent = $companyAccount->members()
            ->with('user')
            ->where('role', 'referent')
            ->where('status', 'active')
            ->first()?->user;

        return PayerContext::company($companyAccount, [
            'type' => 'company',
            'display_name' => $companyAccount->name,
            'phone' => $companyAccount->phone,
            'account_uuid' => $companyAccount->uuid,
            'company_reference' => $companyAccount->reference,
            'referent_uuid' => $referent?->uuid,
            'referent_name' => $referent?->name,
            'captured_at' => now()->toISOString(),
        ]);
    }
}
