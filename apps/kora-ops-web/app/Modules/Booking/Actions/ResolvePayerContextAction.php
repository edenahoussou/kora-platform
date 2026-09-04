<?php

namespace App\Modules\Booking\Actions;

use App\Models\User;
use App\Modules\Booking\Data\PayerContext;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use Illuminate\Validation\ValidationException;

class ResolvePayerContextAction
{
    /** @param array{type?: string, user_uuid?: string|null, company_account_uuid?: string|null} $data */
    public function handle(?User $actor, array $data): PayerContext
    {
        $type = (string) ($data['type'] ?? 'personal');
        if ($type === 'personal') {
            $payerUuid = $data['user_uuid'] ?? null;
            if ($payerUuid !== null && ($actor === null || $actor->uuid !== $payerUuid)) {
                throw ValidationException::withMessages(['payer.user_uuid' => 'Le payeur personnel doit être l’utilisateur authentifié.']);
            }

            return PayerContext::personal($actor?->getKey());
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

        return PayerContext::company($companyAccount);
    }
}
