<?php

namespace App\Modules\Wallet\Http\Resources;

use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Wallet
 */
class WalletResource extends JsonResource
{
    /**
     * Expose les soldes, l'échéance de points et les paramètres UX du Wallet.
     *
     * @param  Request  $request  Requête courante
     * @return array<string, mixed> Représentation API
     */
    public function toArray(Request $request): array
    {
        $settings = app(WalletSettings::class);

        return [
            'uuid' => $this->uuid,
            'cash_balance' => (int) $this->cash_balance,
            'points_balance' => (int) $this->points_balance,
            'points_expires_at' => $this->points_expires_at?->toIso8601String(),
            'points_compensation_due' => (int) $this->points_compensation_due,
            'referral_code' => $this->referral_code,
            'referred_by' => $this->referred_by_user_id !== null,
            'holder' => [
                'name' => $this->whenLoaded('user', fn () => $this->user->name),
                'phone' => $this->whenLoaded('user', fn () => $this->user->phone),
            ],
            'settings' => [
                'wallet_cash_enabled' => $settings->walletCashEnabled,
                'wallet_points_enabled' => $settings->walletPointsEnabled,
                'point_value_fcfa' => $settings->pointValueFcfa,
                'points_min_spend' => $settings->pointsMinSpend,
                'points_expiry_months' => $settings->pointsExpiryMonths,
                'topup_min_amount' => $settings->topupMinAmount,
                'topup_max_amount' => $settings->topupMaxAmount,
                'cash_balance_cap' => $settings->cashBalanceCap,
                'topup_amount_presets' => $settings->topupAmountPresets,
                'wallet_hold_ttl_minutes' => $settings->walletHoldTtlMinutes,
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
