<?php

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\Payments\Models\Payment;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\WalletTransaction;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    Notification::fake();
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function counterAgent(string $role = 'agency_agent'): User
{
    $agent = User::factory()->create();
    $agent->assignRole($role);

    return $agent;
}

function counterWalletReservation(User $agent, int $total = 15000): Reservation
{
    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus);

    $reservation = Reservation::query()->create([
        'created_by_user_id' => $agent->getKey(),
        'status' => 'held',
        'total_amount' => $total,
        'currency' => 'XOF',
        'channel' => ReservationChannel::Counter,
        'expires_at' => now()->addMinutes(15),
        'total_snapshot' => [
            'legs' => [['leg_order' => 1, 'final_amount' => $total]],
            'passenger_allocations' => [$total],
        ],
    ]);

    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->getKey(),
        'status' => 'active',
        'passenger_index' => 0,
        'first_name' => 'Moussa',
        'last_name' => 'Bello',
    ]);

    ReservationSegment::query()->create([
        'reservation_id' => $reservation->getKey(),
        'reservation_passenger_id' => $passenger->getKey(),
        'leg_order' => 1,
        'passenger_index' => 0,
        'departure_id' => $departure->getKey(),
        'seat_id' => $seat->getKey(),
        'origin_stop_id' => $origin->getKey(),
        'destination_stop_id' => $destination->getKey(),
        'origin_stop_order' => $origin->stop_order,
        'destination_stop_order' => $destination->stop_order,
        'status' => 'held',
    ]);

    return $reservation;
}

it('looks up a wallet by phone for counter agents', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 12000]);
    Sanctum::actingAs(counterAgent());

    $this->getJson('/api/v1/ops/wallets/lookup?phone='.urlencode((string) $scenario['user']->phone))
        ->assertOk()
        ->assertJsonPath('data.uuid', $scenario['wallet']->uuid)
        ->assertJsonPath('data.cash_balance', 12000)
        ->assertJsonPath('data.available.cash', 12000)
        ->assertJsonPath('data.holder.name', $scenario['user']->name);
});

it('returns no wallet for an unknown phone', function (): void {
    Sanctum::actingAs(counterAgent());

    $this->getJson('/api/v1/ops/wallets/lookup?phone=%2B2290199999999')
        ->assertOk()
        ->assertJsonPath('data', null);
});

it('debits the traveler wallet at the counter with agent audit', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);
    $agent = counterAgent();
    $reservation = counterWalletReservation($agent);

    Sanctum::actingAs($agent);
    $this->postJson('/api/v1/ops/wallets/'.$scenario['wallet']->uuid.'/debit', [
        'reservation_uuid' => $reservation->uuid,
        'wallet_amount' => 15000,
        'points_amount' => 0,
        'wallet_holder_phone' => $scenario['user']->phone,
        'wallet_holder_confirmed' => true,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(5000)
        ->and($reservation->fresh()->status->value)->toBe('paid');

    $transaction = WalletTransaction::query()
        ->where('reason', WalletTransactionReason::Purchase->value)
        ->firstOrFail();

    expect((int) $transaction->actor_user_id)->toBe($agent->getKey())
        ->and(Payment::query()->where('reservation_id', $reservation->getKey())->firstOrFail()->method->value)->toBe('wallet');
});

it('requires the present wallet holder phone to match the wallet owner', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);
    $agent = counterAgent();
    $reservation = counterWalletReservation($agent);

    Sanctum::actingAs($agent);
    $this->postJson('/api/v1/ops/wallets/'.$scenario['wallet']->uuid.'/debit', [
        'reservation_uuid' => $reservation->uuid,
        'wallet_amount' => 15000,
        'points_amount' => 0,
        'wallet_holder_phone' => '+2290199999999',
        'wallet_holder_confirmed' => true,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertUnprocessable()->assertJsonValidationErrors(['wallet_holder_phone']);

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(20000)
        ->and($reservation->fresh()->status->value)->toBe('held');
});

it('forbids the counter debit without the wallet permission', function (): void {
    walletCashEnabled();
    $scenario = walletScenario(['cash' => 20000]);
    $agent = counterAgent('agency_agent');
    $reservation = counterWalletReservation($agent);

    $outsider = User::factory()->create();

    Sanctum::actingAs($outsider);
    $this->postJson('/api/v1/ops/wallets/'.$scenario['wallet']->uuid.'/debit', [
        'reservation_uuid' => $reservation->uuid,
        'wallet_amount' => 15000,
        'wallet_holder_phone' => $scenario['user']->phone,
        'wallet_holder_confirmed' => true,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();

    expect($scenario['wallet']->fresh()->cash_balance)->toBe(20000);
});
