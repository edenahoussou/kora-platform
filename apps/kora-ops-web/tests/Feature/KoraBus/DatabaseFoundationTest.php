<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Cash\Models\CashSession;
use App\Modules\Departures\Models\Departure;
use App\Modules\DynamicPricing\Models\Fare;
use App\Modules\DynamicPricing\Models\PriceQuote;
use App\Modules\Fiscalization\Models\FiscalInvoice;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

it('uses public uuids and hides numeric ids on business models', function (): void {
    $agency = Agency::query()->create([
        'code' => 'TST',
        'name' => 'Agence Test',
        'city' => 'Cotonou',
        'country' => 'BJ',
    ]);

    expect($agency->id)->toBeInt()
        ->and($agency->uuid)->not()->toBeEmpty()
        ->and($agency->getRouteKeyName())->toBe('uuid')
        ->and($agency->toArray())->not()->toHaveKey('id');
});

it('keeps roles out of users and centralizes authorization through policies', function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    expect(Schema::hasColumn('users', 'role'))->toBeFalse();

    $commercial = User::factory()->create();
    $commercial->assignRole('commercial_director');

    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');

    expect(Gate::forUser($commercial)->allows('create', Fare::class))->toBeTrue()
        ->and(Gate::forUser($agent)->allows('create', Fare::class))->toBeFalse()
        ->and(Gate::forUser($agent)->allows('create', CashSession::class))->toBeTrue()
        ->and(Gate::forUser($commercial)->allows('create', CashSession::class))->toBeFalse();
});

it('stores immutable price quote snapshots', function (): void {
    $data = koraBusScenario();

    $quote = PriceQuote::query()->create([
        'departure_id' => $data['departure']->id,
        'origin_stop_id' => $data['origin']->id,
        'destination_stop_id' => $data['destination']->id,
        'base_amount' => 12000,
        'coefficient' => 1.1500,
        'final_amount' => 13800,
        'currency' => 'XOF',
        'snapshot' => [
            'fare' => ['amount' => 12000],
            'rule' => ['type' => 'increase', 'percentage' => 15],
        ],
        'expires_at' => now()->addMinutes(10),
    ]);

    DB::table('pricing_rules')->insert([
        'uuid' => (string) Str::uuid(),
        'line_id' => $data['line']->id,
        'type' => 'increase',
        'conditions' => json_encode(['load_factor_min' => 80]),
        'percentage' => 25,
        'priority' => 1,
        'status' => 'active',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $quote->refresh();

    expect($quote->final_amount)->toBe(13800)
        ->and($quote->snapshot['rule']['percentage'])->toBe(15);
});

it('enforces idempotency keys for payments', function (): void {
    $data = koraBusScenario();
    $reservation = Reservation::query()->create([
        'departure_id' => $data['departure']->id,
        'status' => 'confirmed',
        'total_amount' => 10000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);

    DB::table('payments')->insert([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'provider' => 'sandbox-money',
        'provider_reference' => 'PAY-001',
        'idempotency_key' => 'payment-key-001',
        'amount' => 10000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('payments')->insert([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'provider' => 'sandbox-money',
        'provider_reference' => 'PAY-002',
        'idempotency_key' => 'payment-key-001',
        'amount' => 10000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('enforces idempotency keys for offline operations', function (): void {
    DB::table('sync_operations')->insert([
        'uuid' => (string) Str::uuid(),
        'operation_uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'operation_type' => 'boarding.scan',
        'local_recorded_at' => now(),
        'status' => 'pending',
        'payload' => json_encode(['ticket' => 'TST']),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('sync_operations')->insert([
        'uuid' => (string) Str::uuid(),
        'operation_uuid' => '550e8400-e29b-41d4-a716-446655440000',
        'operation_type' => 'boarding.scan',
        'local_recorded_at' => now(),
        'status' => 'pending',
        'payload' => json_encode(['ticket' => 'TST']),
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('keeps fiscal invoices unique per KORA reference and MECeF uid', function (): void {
    $data = koraBusScenario();
    $reservation = Reservation::query()->create([
        'departure_id' => $data['departure']->id,
        'status' => 'paid',
        'total_amount' => 10000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);

    $paymentId = DB::table('payments')->insertGetId([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $reservation->id,
        'provider' => 'sandbox-money',
        'provider_reference' => 'PAY-FISCAL-001',
        'idempotency_key' => 'payment-fiscal-key-001',
        'amount' => 10000,
        'currency' => 'XOF',
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    FiscalInvoice::query()->create([
        'reservation_id' => $reservation->id,
        'payment_id' => $paymentId,
        'kora_reference' => 'KORA-FISCAL-001',
        'mecef_uid' => 'MECEF-001',
        'status' => 'fiscalized',
        'total_without_tax' => 10000,
        'total_tax' => 0,
        'total_with_tax' => 10000,
        'tax_snapshot' => ['tax_code' => 'EXEMPT', 'tax_rate' => 0],
    ]);

    expect(fn () => FiscalInvoice::query()->create([
        'reservation_id' => $reservation->id,
        'payment_id' => $paymentId,
        'kora_reference' => 'KORA-FISCAL-001',
        'mecef_uid' => 'MECEF-002',
        'status' => 'pending',
        'total_without_tax' => 10000,
        'total_tax' => 0,
        'total_with_tax' => 10000,
        'tax_snapshot' => ['tax_code' => 'EXEMPT', 'tax_rate' => 0],
    ]))->toThrow(QueryException::class);
});

it('prevents overlapping seat segments on PostgreSQL', function (): void {
    if (DB::connection()->getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL exclusion constraints are validated on the pgsql connection.');
    }

    $data = koraBusScenario();
    $first = Reservation::query()->create([
        'departure_id' => $data['departure']->id,
        'status' => 'confirmed',
        'total_amount' => 10000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);
    $second = Reservation::query()->create([
        'departure_id' => $data['departure']->id,
        'status' => 'confirmed',
        'total_amount' => 10000,
        'currency' => 'XOF',
        'channel' => 'online',
    ]);

    DB::table('reservation_segments')->insert([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $first->id,
        'departure_id' => $data['departure']->id,
        'seat_id' => $data['seat']->id,
        'origin_stop_id' => $data['origin']->id,
        'destination_stop_id' => $data['destination']->id,
        'origin_stop_order' => 1,
        'destination_stop_order' => 4,
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('reservation_segments')->insert([
        'uuid' => (string) Str::uuid(),
        'reservation_id' => $second->id,
        'departure_id' => $data['departure']->id,
        'seat_id' => $data['seat']->id,
        'origin_stop_id' => $data['origin']->id,
        'destination_stop_id' => $data['destination']->id,
        'origin_stop_order' => 3,
        'destination_stop_order' => 5,
        'status' => 'confirmed',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

/**
 * @return array<string, mixed>
 */
function koraBusScenario(): array
{
    $line = Line::query()->create([
        'code' => 'L'.Str::random(8),
        'name' => 'Cotonou - Abidjan',
        'countries_crossed' => ['BJ', 'TG', 'GH', 'CI'],
        'status' => 'active',
    ]);

    $origin = Stop::query()->create([
        'line_id' => $line->id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
    ]);

    Stop::query()->create([
        'line_id' => $line->id,
        'name' => 'Lome',
        'city' => 'Lome',
        'country' => 'TG',
        'stop_order' => 3,
    ]);

    $destination = Stop::query()->create([
        'line_id' => $line->id,
        'name' => 'Abidjan',
        'city' => 'Abidjan',
        'country' => 'CI',
        'stop_order' => 5,
    ]);

    $bus = Bus::query()->create([
        'registration_number' => 'BUS-'.Str::random(8),
        'seat_count' => 55,
        'status' => 'active',
    ]);

    $seat = Seat::query()->create([
        'bus_id' => $bus->id,
        'code' => '2A',
        'type' => 'standard',
        'sellable' => true,
        'status' => 'active',
    ]);

    $departure = Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'status' => 'scheduled',
    ]);

    return compact('line', 'origin', 'destination', 'bus', 'seat', 'departure');
}
