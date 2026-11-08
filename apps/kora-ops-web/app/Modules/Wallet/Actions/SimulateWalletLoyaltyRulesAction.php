<?php

namespace App\Modules\Wallet\Actions;

use Illuminate\Support\Carbon;

final class SimulateWalletLoyaltyRulesAction
{
    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, int|string|bool>
     */
    public function execute(
        array $rules,
        int $ticketAmount,
        int $pointsUsed,
        int $qualifyingTrips,
        bool $isFirstUsage = false,
    ): array {
        $eligibleAmount = max(0, $ticketAmount - $pointsUsed);
        $pointsEarned = (int) floor($eligibleAmount * (int) $rules['loyaltyEarnRateBasisPoints'] / 10000);
        $welcomeBonus = $isFirstUsage ? (int) $rules['bonusWelcomePoints'] : 0;
        $usageBonus = (int) $rules['bonusUsageNth'] > 0
            && $qualifyingTrips + 1 === (int) $rules['bonusUsageNth']
            ? (int) $rules['bonusUsagePoints']
            : 0;

        return [
            'ticketAmount' => $ticketAmount,
            'pointsUsed' => $pointsUsed,
            'eligibleAmount' => $eligibleAmount,
            'pointsEarned' => $pointsEarned,
            'welcomeBonusPoints' => $welcomeBonus,
            'usageBonusPoints' => $usageBonus,
            'projectedPoints' => $pointsEarned + $welcomeBonus + $usageBonus,
            'pointValueFcfa' => (int) $rules['pointValueFcfa'],
            'projectedValueFcfa' => ($pointsEarned + $welcomeBonus + $usageBonus) * (int) $rules['pointValueFcfa'],
            'expiryAt' => Carbon::now()->addMonths((int) $rules['pointsExpiryMonths'])->toIso8601String(),
            'persisted' => false,
        ];
    }
}
