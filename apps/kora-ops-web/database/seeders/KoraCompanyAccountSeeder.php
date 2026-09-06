<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyMemberRole;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Crée 4 comptes entreprise de test pour exercer les flux de paiement
 * par crédit entreprise : entreprise, ONG, ambassade, et un compte à
 * crédit insuffisant.
 */
class KoraCompanyAccountSeeder extends Seeder
{
    public function run(): void
    {
        $sandrine = User::query()->where('email', 'agent.sandrine@kora.demo')->first();
        $brice = User::query()->where('email', 'agent.brice@kora.demo')->first();
        $adjoua = User::query()->where('email', 'manager.adjoua@kora.demo')->first();

        $accounts = [
            [
                'name' => 'Société Bénin Transport',
                'type' => CompanyAccountType::Company,
                'reference' => 'SBT-2026-001',
                'phone' => '+22997000001',
                'email' => 'compta@benin-transport.bj',
                'monthly_credit_limit' => 500_000,
                'referent' => $sandrine,
            ],
            [
                'name' => 'ONG Aide Médicale Bénin',
                'type' => CompanyAccountType::Ngo,
                'reference' => 'ONG-AMBJ-2026',
                'phone' => '+22997000002',
                'email' => 'finance@aidemed.bj',
                'monthly_credit_limit' => 300_000,
                'referent' => $brice,
            ],
            [
                'name' => 'Ambassade de France au Bénin',
                'type' => CompanyAccountType::Embassy,
                'reference' => 'AMB-FR-2026',
                'phone' => '+22997000003',
                'email' => 'logistique@ambassade-france.bj',
                'monthly_credit_limit' => 1_000_000,
                'referent' => $adjoua,
            ],
            [
                'name' => 'Petite Coopérative du Nord',
                'type' => CompanyAccountType::Company,
                'reference' => 'PCN-2026-001',
                'phone' => '+22997000004',
                'email' => 'coop@nord.bj',
                'monthly_credit_limit' => 50_000,
                'referent' => $sandrine,
            ],
        ];

        foreach ($accounts as $data) {
            $account = CompanyAccount::query()->firstOrCreate(
                ['reference' => $data['reference']],
                [
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['name'],
                    'type' => $data['type'],
                    'phone' => $data['phone'],
                    'email' => $data['email'],
                    'monthly_credit_limit' => $data['monthly_credit_limit'],
                    'status' => CompanyAccountStatus::Active,
                    'created_by' => $data['referent']?->id,
                    'updated_by' => $data['referent']?->id,
                ],
            );

            if ($data['referent']) {
                CompanyAccountMember::query()->firstOrCreate(
                    ['company_account_id' => $account->id, 'user_id' => $data['referent']->id],
                    [
                        'uuid' => (string) Str::uuid(),
                        'role' => CompanyMemberRole::Referent,
                        'status' => CompanyMemberStatus::Active,
                    ],
                );
            }
        }
    }
}
