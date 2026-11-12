<?php

namespace App\Modules\Wallet\Data;

use App\Modules\Payments\Models\Payment;
use Illuminate\Validation\ValidationException;

final readonly class WalletPurchaseFunding
{
    public function __construct(
        public int $walletCash,
        public int $points,
        public int $pointRate,
        public int $pointsValue,
        public int $providerAmount,
    ) {}

    public static function purchase(int $total, int $cash, int $points, int $rate): self
    {
        if ($cash < 0 || $points < 0 || $rate < 1 || $cash > $total || $points > intdiv($total - $cash, $rate)) {
            throw ValidationException::withMessages(['wallet_amount' => 'La valeur des points et le cash Wallet ne doivent pas dépasser le prix.']);
        }

        $value = $points * $rate;

        return new self($cash, $points, $rate, $value, $total - $cash - $value);
    }

    public static function fromPayment(Payment $payment): self
    {
        $data = $payment->wallet_funding ?? $payment->provider_payload ?? [];
        $points = (int) ($data['points_amount'] ?? 0);
        $cash = (int) ($data['wallet_amount'] ?? 0);
        $value = $payment->wallet_funding === null ? $points : (int) $data['points_value_fcfa'];

        return new self(
            $cash,
            $points,
            $payment->wallet_funding === null ? 1 : (int) $data['point_value_fcfa'],
            $value,
            (int) ($data['provider_amount_fcfa'] ?? max(0, $payment->amount - $cash - $value)),
        );
    }

    /** @return array{wallet_amount: int, points_amount: int, point_value_fcfa: int, points_value_fcfa: int, provider_amount_fcfa: int} */
    public function toArray(): array
    {
        return [
            'wallet_amount' => $this->walletCash,
            'points_amount' => $this->points,
            'point_value_fcfa' => $this->pointRate,
            'points_value_fcfa' => $this->pointsValue,
            'provider_amount_fcfa' => $this->providerAmount,
        ];
    }
}
