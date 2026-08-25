<?php

namespace App\Modules\Cash\Actions;

use App\Models\User;
use App\Modules\Cash\Data\CashSessionStatus;
use App\Modules\Cash\Models\CashSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OpenCashSessionAction
{
    public function execute(User $agent, int $openingFloat): CashSession
    {
        if ($agent->agency_id === null) {
            throw ValidationException::withMessages([
                'cash_session' => 'Votre profil n’est rattaché à aucune agence.',
            ]);
        }

        return DB::transaction(function () use ($agent, $openingFloat): CashSession {
            $existing = CashSession::query()
                ->where('agent_id', $agent->getKey())
                ->where('agency_id', $agent->agency_id)
                ->whereDate('business_date', now()->toDateString())
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                throw ValidationException::withMessages([
                    'opening_float' => 'Une caisse existe déjà pour cet agent et cette date.',
                ]);
            }

            return CashSession::query()->create([
                'agent_id' => $agent->getKey(),
                'agency_id' => $agent->agency_id,
                'business_date' => now()->toDateString(),
                'opening_float' => $openingFloat,
                'expected_balance' => $openingFloat,
                'total_cash' => 0,
                'total_mobile_money' => 0,
                'status' => CashSessionStatus::Open,
            ]);
        });
    }
}
