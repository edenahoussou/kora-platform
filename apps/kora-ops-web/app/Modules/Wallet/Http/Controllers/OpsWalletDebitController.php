<?php

namespace App\Modules\Wallet\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Payments\Actions\RecordCounterSalePaymentAction;
use App\Modules\Wallet\Http\Requests\OpsWalletDebitRequest;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Http\JsonResponse;

/**
 * @group Wallet OPS
 *
 * Débit Wallet au guichet sans application, tracé agent/agence.
 */
class OpsWalletDebitController extends Controller
{
    /**
     * Débite le Wallet d'un voyageur pour une vente guichet.
     *
     * @param  OpsWalletDebitRequest  $request  Réservation, montants et clé d'idempotence
     * @param  Wallet  $wallet  Wallet lié par UUID
     * @param  RecordCounterSalePaymentAction  $recordCounterSalePayment  Parcours de vente guichet
     * @return JsonResponse Réservation payée et soldes restants
     */
    public function __invoke(
        OpsWalletDebitRequest $request,
        Wallet $wallet,
        RecordCounterSalePaymentAction $recordCounterSalePayment,
    ): JsonResponse {
        abort_unless($request->user()?->can('wallet.debit-counter'), 403);

        $reservation = Reservation::query()
            ->where('uuid', $request->string('reservation_uuid')->value())
            ->firstOrFail();

        $result = $recordCounterSalePayment->execute($reservation, $request->user(), [
            'method' => 'wallet',
            'amount' => (int) $reservation->total_amount,
            'idempotency_key' => $request->string('idempotency_key')->value(),
            'wallet_uuid' => $wallet->uuid,
            'wallet_amount' => $request->integer('wallet_amount'),
            'points_amount' => $request->integer('points_amount'),
        ]);

        return response()->json([
            'data' => [
                'reservation_uuid' => $result['reservation']->uuid,
                'payment_uuid' => $result['payment']->uuid,
                'wallet_uuid' => $wallet->uuid,
                'cash_balance' => (int) $wallet->fresh()->cash_balance,
                'points_balance' => (int) $wallet->fresh()->points_balance,
            ],
        ], 201);
    }
}
