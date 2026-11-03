<?php

use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Data\DepartureStatus;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Data\WalletBalanceType;
use App\Modules\Wallet\Data\WalletTransactionReason;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Services\WalletLedgerService;
use Illuminate\Support\Str;

function walletCashEnabled(bool $cash = true, bool $points = true): void
{
    $settings = app(WalletSettings::class);
    $settings->walletCashEnabled = $cash;
    $settings->walletPointsEnabled = $points;
    $settings->save();
}

function walletForUser(User $user): Wallet
{
    return Wallet::query()->firstOrCreate(
        ['user_id' => $user->getKey()],
        ['referral_code' => 'K'.Str::upper(Str::random(7))],
    );
}

function walletFund(Wallet $wallet, WalletBalanceType $type, int $amount, ?string $key = null): void
{
    app(WalletLedgerService::class)->credit(
        $wallet,
        $type,
        $amount,
        $type === WalletBalanceType::Cash ? WalletTransactionReason::TopUp : WalletTransactionReason::PointsEarn,
        $key ?? 'test:'.Str::uuid(),
    );
}

/**
 * @param  array{total?: int, cash?: int, points?: int, segment_status?: string}  $options
 * @return array{user: User, wallet: Wallet, reservation: Reservation, segment: ReservationSegment, passenger: ReservationPassenger, departure: Departure, seat: Seat}
 */
function walletScenario(array $options = []): array
{
    $total = $options['total'] ?? 15000;
    $user = User::factory()->create(['phone' => '+22901'.random_int(1000000, 9999999)]);
    $wallet = walletForUser($user);

    if (($options['cash'] ?? 0) > 0) {
        walletFund($wallet, WalletBalanceType::Cash, (int) $options['cash']);
    }

    if (($options['points'] ?? 0) > 0) {
        walletFund($wallet, WalletBalanceType::Points, (int) $options['points']);
    }

    $line = boardingLine();
    [$origin, $destination] = boardingStops($line);
    [$bus, $seat] = boardingBusWithSeat();
    $departure = boardingDeparture($line, $bus, [], DepartureStatus::Scheduled->value);

    $reservation = Reservation::query()->create([
        'created_by_user_id' => $user->getKey(),
        'payer_user_id' => $user->getKey(),
        'payer_type' => PayerType::Personal,
        'status' => ReservationStatus::Held,
        'total_amount' => $total,
        'currency' => 'XOF',
        'channel' => ReservationChannel::Mobile,
        'expires_at' => now()->addMinutes(15),
        'total_snapshot' => [
            'type' => 'itinerary',
            'legs' => [[
                'leg_order' => 1,
                'final_amount' => $total,
                'departure_uuid' => $departure->uuid,
            ]],
            'passenger_allocations' => [$total],
        ],
    ]);

    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->getKey(),
        'user_id' => $user->getKey(),
        'status' => 'active',
        'passenger_index' => 0,
        'first_name' => 'Fatou',
        'last_name' => 'Diop',
        'phone' => $user->phone,
    ]);

    $segment = ReservationSegment::query()->create([
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
        'status' => $options['segment_status'] ?? ReservationSegmentStatus::Ticketed->value,
    ]);

    return compact('user', 'wallet', 'reservation', 'segment', 'passenger', 'departure', 'seat');
}

/** @param array<string, mixed> $payload */
function walletConfirmedPayment(Reservation $reservation, array $payload = [], ?int $amount = null): Payment
{
    return Payment::query()->create([
        'reservation_id' => $reservation->getKey(),
        'method' => 'wallet',
        'provider' => 'wallet',
        'idempotency_key' => (string) Str::uuid(),
        'amount' => $amount ?? (int) $reservation->total_amount,
        'currency' => $reservation->currency,
        'status' => PaymentStatus::Confirmed,
        'confirmed_at' => now(),
        'provider_payload' => $payload,
    ]);
}
