<?php

namespace App\Modules\Booking\Http\Requests;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Data\MobileMoneyNetwork;
use App\Modules\Payments\Data\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCounterSalePaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reservation = $this->route('reservation');
        $user = $this->user();

        return $reservation instanceof Reservation
            && $user instanceof User
            && $reservation->created_by_user_id === $user->getKey()
            && $user->can('createCounterSale', Reservation::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'amount' => ['required', 'integer', 'min:0'],
            'idempotency_key' => ['required', 'uuid'],
            'cash_received_amount' => [
                'nullable',
                'integer',
                'min:0',
                Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::Cash->value),
            ],
            'mobile_money_network' => [
                'nullable',
                Rule::enum(MobileMoneyNetwork::class),
                Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::MobileMoney->value),
            ],
            'agency_payment_account_uuid' => [
                'nullable',
                'uuid',
                Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::MobileMoney->value),
            ],
            'payer_phone' => [
                'nullable',
                'string',
                'max:32',
                Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::MobileMoney->value),
            ],
            'payer_name' => ['nullable', 'string', 'max:120'],
            'provider_reference' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::MobileMoney->value),
            ],
            'verified_manually' => [
                'accepted',
                Rule::requiredIf(fn (): bool => $this->input('method') === PaymentMethod::MobileMoney->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Le montant de la vente est obligatoire.',
            'amount.same' => 'Le montant reçu doit correspondre au total de la vente.',
            'cash_received_amount.required' => 'Le montant reçu est obligatoire pour un paiement en espèces.',
            'mobile_money_network.required' => 'Sélectionnez le réseau Mobile Money utilisé.',
            'agency_payment_account_uuid.required' => 'Sélectionnez le compte Mobile Money crédité.',
            'payer_phone.required' => 'Le numéro du payeur est obligatoire.',
            'provider_reference.required' => 'La référence de transaction est obligatoire.',
            'verified_manually.required' => 'Confirmez la vérification manuelle du crédit reçu.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }

    /**
     * @return array<string, string>
     */
    public function saleQuery(string $reservationUuid): array
    {
        return ['reservation_uuid' => $reservationUuid];
    }
}
