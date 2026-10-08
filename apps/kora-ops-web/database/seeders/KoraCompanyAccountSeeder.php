<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\CompanyAccounts\Data\CompanyAccountStatus;
use App\Modules\CompanyAccounts\Data\CompanyAccountType;
use App\Modules\CompanyAccounts\Data\CompanyMemberRole;
use App\Modules\CompanyAccounts\Data\CompanyMemberStatus;
use App\Modules\CompanyAccounts\Data\CompanyTravelerStatus;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Modules\CompanyAccounts\Models\CompanyTraveler;
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

        $this->seedSbtTravelers();
    }

    /** Roster de démonstration pour l'espace entreprise SBT (MEZ-120). */
    private function seedSbtTravelers(): void
    {
        $sbt = CompanyAccount::query()->where('reference', 'SBT-2026-001')->first();

        if ($sbt === null) {
            return;
        }

        $travelers = [
            [
                'first_name' => 'Sandrine',
                'last_name' => 'Kouassi',
                'phone' => '+22997000011',
                'email' => 'sandrine.kouassi@benin-transport.bj',
                'nationality' => 'BJ',
                'document_type' => 'national_id',
                'document_number' => 'ID-BJ-778845',
                'status' => CompanyTravelerStatus::Active,
            ],
            [
                'first_name' => 'Brice',
                'last_name' => 'Hounsou',
                'phone' => '+22997000012',
                'email' => 'brice.hounsou@benin-transport.bj',
                'nationality' => 'BJ',
                'status' => CompanyTravelerStatus::Active,
            ],
            [
                'first_name' => 'Adjoua',
                'last_name' => 'Sanni',
                'phone' => '+22997000013',
                'email' => null,
                'nationality' => 'BJ',
                'document_type' => 'passport',
                'document_number' => 'BJ-P-552310',
                'birth_date' => '1986-04-17',
                'yellow_fever_vaccinated' => true,
                'status' => CompanyTravelerStatus::Active,
            ],
            [
                'first_name' => 'Koffi',
                'last_name' => 'Agossou',
                'phone' => '+22997000014',
                'email' => 'k.agossou@benin-transport.bj',
                'nationality' => 'TG',
                'status' => CompanyTravelerStatus::Inactive,
            ],
        ];

        foreach ($travelers as $data) {
            CompanyTraveler::query()->firstOrCreate(
                [
                    'company_account_id' => $sbt->id,
                    'phone' => $data['phone'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    ...$data,
                ],
            );
        }
    }
}
