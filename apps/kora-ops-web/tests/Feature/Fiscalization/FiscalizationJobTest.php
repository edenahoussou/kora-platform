<?php

use App\Jobs\FiscalizeReservationInvoiceJob;
use App\Models\User;
use App\Modules\Booking\Data\PayerType;
use App\Modules\Booking\Data\ReservationSegmentStatus;
use App\Modules\Booking\Data\ReservationStatus;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Fiscalization\Data\FiscalInvoiceStatus;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\Fiscalization\Services\FiscalInvoicePayloadBuilder;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Carbon::setTestNow('2026-09-08 10:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('builds a valid e-MECeF payload with correct operator and client separation', function (): void {
    $user = User::factory()->create(['name' => 'Agent KORA']);
    $reservation = createFiscalizationReservation($user, payerType: PayerType::Personal, totalAmount: 30000, passengerCount: 3);

    $payload = app(FiscalInvoicePayloadBuilder::class)->build($reservation);

    expect($payload['ifu'])->toBe((string) config('emecf.default_ifu'))
        ->and($payload['type'])->toBe('FV')
        ->and($payload['operator']['name'])->toBe('Agent KORA')
        ->and($payload['operator']['id'])->toBe($user->id)
        ->and($payload['client']['name'])->toBe('Client FISC TEST')
        ->and($payload['items'])->toHaveCount(3)
        ->and($payload['items'][0]['taxGroup'])->toBe('A');
});

it('distributes the unit price without rounding loss across passengers', function (): void {
    $user = User::factory()->create();
    $reservation = createFiscalizationReservation($user, totalAmount: 10000, passengerCount: 3);

    $payload = app(FiscalInvoicePayloadBuilder::class)->build($reservation);

    $total = array_sum(array_map(fn ($item): int => $item['price'], $payload['items']));
    expect($total)->toBe(10000)
        ->and($payload['items'][0]['price'])->toBe(3334)
        ->and($payload['items'][1]['price'])->toBe(3333)
        ->and($payload['items'][2]['price'])->toBe(3333);
});

it('marks the fiscal invoice as failed when e-MECeF is not configured', function (): void {
    config()->set('emecf.token', null);
    config()->set('emecf.default_ifu', '0000000000000');

    $user = User::factory()->create();
    $reservation = createFiscalizationReservation($user);

    (new FiscalizeReservationInvoiceJob($reservation->id))->handle(app(FiscalInvoicePayloadBuilder::class));

    $invoice = FiscalInvoice::query()->where('reservation_id', $reservation->id)->first();
    expect($invoice)->not->toBeNull()
        ->and($invoice->status)->toBe(FiscalInvoiceStatus::Failed)
        ->and($invoice->last_error)->toContain('e-MECeF non configuré');
});

it('creates only one fiscal invoice per reservation across multiple job runs', function (): void {
    config()->set('emecf.token', null);
    config()->set('emecf.default_ifu', '0000000000000');

    $user = User::factory()->create();
    $reservation = createFiscalizationReservation($user);

    (new FiscalizeReservationInvoiceJob($reservation->id))->handle(app(FiscalInvoicePayloadBuilder::class));
    (new FiscalizeReservationInvoiceJob($reservation->id))->handle(app(FiscalInvoicePayloadBuilder::class));

    expect(FiscalInvoice::query()->where('reservation_id', $reservation->id)->count())->toBe(1);
});

it('has the correct relationships on the FiscalInvoice model', function (): void {
    $user = User::factory()->create();
    $reservation = createFiscalizationReservation($user);

    (new FiscalizeReservationInvoiceJob($reservation->id))->handle(app(FiscalInvoicePayloadBuilder::class));

    $invoice = FiscalInvoice::query()->where('reservation_id', $reservation->id)->first();
    expect($invoice->reservation)->not->toBeNull()
        ->and($invoice->payment)->not->toBeNull();
});

function createFiscalizationReservation(User $user, PayerType $payerType = PayerType::Personal, int $totalAmount = 15000, int $passengerCount = 1): Reservation
{
    $line = Line::query()->firstOrCreate(
        ['code' => 'FISC-L1'],
        ['name' => 'Cotonou - Parakou FISC', 'countries_crossed' => ['BJ'], 'distance_km' => 450, 'estimated_duration_minutes' => 360, 'status' => 'active'],
    );

    $originPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'FISC Origin Physical'],
        ['city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
    );
    $origin = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 1],
        ['physical_stop_id' => $originPhysical->id, 'name' => 'FISC Origin', 'city' => 'Cotonou', 'country' => 'BJ', 'km_from_origin' => 0, 'status' => 'active'],
    );
    $destinationPhysical = PhysicalStop::query()->firstOrCreate(
        ['name' => 'FISC Dest Physical'],
        ['city' => 'Parakou', 'country' => 'BJ', 'status' => 'active'],
    );
    $destination = Stop::query()->firstOrCreate(
        ['line_id' => $line->id, 'stop_order' => 2],
        ['physical_stop_id' => $destinationPhysical->id, 'name' => 'FISC Dest', 'city' => 'Parakou', 'country' => 'BJ', 'km_from_origin' => 450, 'status' => 'active'],
    );

    $bus = Bus::query()->firstOrCreate(
        ['registration_number' => 'FISC-BUS-001'],
        ['brand' => 'KORA', 'model' => 'Coach FISC', 'seat_count' => 12, 'status' => 'active'],
    );

    $seats = [];
    $seatCodes = ['1A', '1B', '1C', '1D', '1E', '1F', '2A', '2B'];
    foreach (array_slice($seatCodes, 0, $passengerCount) as $code) {
        $seats[$code] = Seat::query()->firstOrCreate(
            ['bus_id' => $bus->id, 'code' => $code],
            ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
        );
    }

    $departure = Departure::query()->firstOrCreate(
        ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => '2026-09-10', 'departure_time' => '07:00'],
        ['estimated_arrival_at' => Carbon::parse('2026-09-10 13:00'), 'status' => 'scheduled'],
    );

    $reservation = Reservation::create([
        'created_by_user_id' => $user->id,
        'payer_user_id' => $user->id,
        'payer_type' => $payerType,
        'status' => ReservationStatus::Paid,
        'total_amount' => $totalAmount,
        'currency' => 'XOF',
        'channel' => 'counter',
        'expires_at' => now()->addMinutes(15),
    ]);

    for ($i = 0; $i < $passengerCount; $i++) {
        $passenger = ReservationPassenger::create([
            'reservation_id' => $reservation->id,
            'first_name' => 'Passager '.$i,
            'last_name' => 'TEST',
            'phone' => '+22901000000',
            'nationality' => 'BJ',
            'passenger_index' => $i,
        ]);

        $seat = $seats[array_slice($seatCodes, 0, $passengerCount)[$i]];
        ReservationSegment::create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'leg_order' => $i,
            'status' => ReservationSegmentStatus::Held,
        ]);
    }

    Payment::create([
        'reservation_id' => $reservation->id,
        'method' => 'cash',
        'provider' => 'cash',
        'provider_reference' => 'CASH-FISC-'.$reservation->id,
        'idempotency_key' => (string) Str::uuid(),
        'amount' => $totalAmount,
        'currency' => 'XOF',
        'status' => PaymentStatus::Confirmed,
        'payer_name' => 'Client FISC TEST',
        'payer_phone' => '+22901000000',
        'confirmed_at' => now(),
    ]);

    return $reservation->fresh();
}
