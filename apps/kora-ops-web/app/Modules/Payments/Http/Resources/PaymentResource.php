<?php

namespace App\Modules\Payments\Http\Resources;

use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Data\TicketIssuanceStatus;
use App\Modules\Wallet\Data\WalletPurchaseFunding;
use App\Modules\Wallet\Http\Resources\WalletTopUpResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Payment
 */
class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if ($this->reservation_id === null && $this->resource->walletTopUp()->exists()) {
            return WalletTopUpResource::paymentDetails($this->resource) + [
                'amount' => $this->amount,
                'currency' => $this->currency,
                'method' => $this->method?->value,
                'provider_transaction_id' => $this->provider_transaction_id,
                'reservation' => null,
            ];
        }

        $ticketIssuanceStatus = TicketIssuanceStatus::tryFrom((string) $this->reservation?->getRawOriginal('ticket_issuance_status'));

        return [
            'uuid' => $this->uuid,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'method' => $this->method?->value,
            'provider' => $this->provider,
            'provider_reference' => $this->provider_reference,
            'provider_transaction_id' => $this->provider_transaction_id,
            'provider_currency' => $this->provider_currency,
            'provider_amount' => $this->provider_amount,
            'mobile_money_network' => $this->mobile_money_network?->value,
            'payer_phone' => $this->payer_phone,
            'wallet_funding' => $this->when(
                $this->wallet_funding !== null || isset($this->provider_payload['points_amount']) || isset($this->provider_payload['wallet_amount']),
                fn () => WalletPurchaseFunding::fromPayment($this->resource)->toArray(),
            ),
            'confirmed_at' => $this->confirmed_at?->toIso8601String(),
            'last_verified_at' => $this->last_verified_at?->toIso8601String(),
            'next_action' => $this->nextAction(),
            'checkout_url' => $this->when($this->parcel_charge_id !== null && isset($this->provider_payload['checkout_url']), fn () => $this->provider_payload['checkout_url']),
            'widget' => $this->when($this->provider === 'kkiapay', fn () => $this->provider_payload['widget'] ?? null),
            'failure' => $this->when($this->failure_code !== null || $this->failure_message !== null, [
                'code' => $this->failure_code,
                'message' => $this->failure_message,
            ]),
            'expires_at' => $this->reservation?->expires_at?->toIso8601String(),
            'polling_interval_seconds' => 10,
            'reservation' => $this->when($this->relationLoaded('reservation') && $this->reservation !== null, fn () => [
                'uuid' => $this->reservation->uuid,
                'status' => $this->reservation->status->value,
                'departure_uuid' => $this->reservation?->primaryDeparture()?->uuid,
                'ticket_issuance' => [
                    'status' => $ticketIssuanceStatus instanceof TicketIssuanceStatus
                        ? $ticketIssuanceStatus->value
                        : TicketIssuanceStatus::NotStarted->value,
                    'tickets_url' => route('api.v1.bookings.tickets.index', $this->reservation->uuid),
                ],
            ]),
        ];
    }

    private function nextAction(): string
    {
        if ($this->parcel_charge_id !== null && $this->status->value === 'confirmed') {
            return $this->failure_code === 'parcel_reconciliation_required' ? 'contact_support' : 'show_parcel';
        }

        if ($this->status->value === 'pending') {
            return 'verify';
        }

        if ($this->status->value === 'confirmed') {
            return $this->ticketIssuanceStatus() === TicketIssuanceStatus::Issued
                ? 'show_tickets'
                : 'wait_for_tickets';
        }

        return 'retry';
    }

    private function ticketIssuanceStatus(): TicketIssuanceStatus
    {
        return TicketIssuanceStatus::tryFrom((string) $this->reservation?->getRawOriginal('ticket_issuance_status'))
            ?? TicketIssuanceStatus::NotStarted;
    }
}
