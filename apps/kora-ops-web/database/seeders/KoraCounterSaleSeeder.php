<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Models\AgencyPaymentAccount;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class KoraCounterSaleSeeder extends Seeder
{
    public function run(): void
    {
        $agency = Agency::query()->where('code', 'CTN')->firstOrFail();

        User::query()
            ->whereIn('email', [
                'agent.sandrine@kora.demo',
                'agent.brice@kora.demo',
            ])
            ->update(['agency_id' => $agency->getKey()]);

        $accounts = [
            [
                'network' => MobileMoneyNetwork::MtnMomo,
                'label' => 'Compte test MTN MoMo',
                'account_identifier' => '+22997000001',
            ],
            [
                'network' => MobileMoneyNetwork::MoovFlooz,
                'label' => 'Compte test Moov Money / Flooz',
                'account_identifier' => '+22996000001',
            ],
            [
                'network' => MobileMoneyNetwork::CeltiisCash,
                'label' => 'Compte test Celtis Cash',
                'account_identifier' => '+22991000001',
            ],
        ];

        foreach ($accounts as $account) {
            AgencyPaymentAccount::query()->firstOrCreate(
                [
                    'agency_id' => $agency->getKey(),
                    'network' => $account['network']->value,
                    'account_identifier' => $account['account_identifier'],
                ],
                [
                    'uuid' => (string) Str::uuid(),
                    'label' => $account['label'],
                    'status' => 'active',
                ],
            );
        }
    }
}
