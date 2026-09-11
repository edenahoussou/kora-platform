<?php

namespace Database\Seeders;

use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Populates one upcoming and one already-departed departure with a spread
 * of passenger/ticket scenarios, purely so the Manifestes and Billets
 * screens have something realistic to look at without going through the
 * full counter-sale flow first.
 *
 * Self-contained: creates its own line/bus/stops rather than depending on
 * the exact shape of other seeders, so `php artisan db:seed
 * --class=KoraManifestDemoSeeder` works standalone and is safely re-runnable
 * (everything is firstOrCreate/firstOrFail on stable natural keys).
 */
class KoraManifestDemoSeeder extends Seeder
{
    public function run(): void
    {
        $line = Line::query()->firstOrCreate(
            ['code' => 'DEMO-MAN'],
            ['name' => 'Cotonou - Porto-Novo (démo manifeste)', 'distance_km' => 35, 'status' => 'active'],
        );

        $originPhysical = PhysicalStop::query()->firstOrCreate(
            ['code' => 'DEMO-CTN'],
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'status' => 'active'],
        );
        $destinationPhysical = PhysicalStop::query()->firstOrCreate(
            ['code' => 'DEMO-PNV'],
            ['name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ', 'status' => 'active'],
        );

        $origin = Stop::query()->firstOrCreate(
            ['line_id' => $line->id, 'physical_stop_id' => $originPhysical->id],
            ['name' => 'Cotonou', 'city' => 'Cotonou', 'country' => 'BJ', 'stop_order' => 1, 'km_from_origin' => 0, 'status' => 'active'],
        );
        $destination = Stop::query()->firstOrCreate(
            ['line_id' => $line->id, 'physical_stop_id' => $destinationPhysical->id],
            ['name' => 'Porto-Novo', 'city' => 'Porto-Novo', 'country' => 'BJ', 'stop_order' => 2, 'km_from_origin' => 35, 'status' => 'active'],
        );

        $composition = BusComposition::query()->firstOrCreate(
            ['code' => 'DEMO-COMP'],
            [
                'name' => 'Coach démo manifeste',
                'seat_count' => 8,
                'layout' => [['type' => 'standard', 'codes' => ['1A', '1B', '1C', '1D', '2A', '2B', '2C', '2D']]],
                'status' => 'active',
            ],
        );
        $bus = Bus::query()->firstOrCreate(
            ['registration_number' => 'BJ-DEMO-01'],
            ['composition_id' => $composition->id, 'seat_count' => 8, 'status' => 'active'],
        );

        $upcoming = Departure::query()->firstOrCreate(
            ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => now()->addDays(2)->toDateString(), 'departure_time' => '08:00'],
            ['status' => 'scheduled'],
        );
        $departed = Departure::query()->firstOrCreate(
            ['line_id' => $line->id, 'bus_id' => $bus->id, 'departure_date' => now()->subDay()->toDateString(), 'departure_time' => '08:00'],
            ['status' => 'departed'],
        );

        // 1. Solo passenger, ticket already issued.
        $this->passenger($bus, $origin, $destination, $upcoming, '1A', [
            'first_name' => 'Adjoa', 'last_name' => 'Koudjo', 'phone' => '+22997000010',
        ], segmentStatus: 'ticketed', paymentStatus: 'confirmed', issueTicket: true);

        // 2. Solo passenger, payment still pending — no payment row yet.
        $this->passenger($bus, $origin, $destination, $upcoming, '1B', [
            'first_name' => 'Bertin', 'last_name' => 'Houngbo', 'phone' => '+22997000011',
        ], segmentStatus: 'confirmed', paymentStatus: null, issueTicket: false);

        // 3. Group purchase — two passengers in the same reservation, both ticketed.
        $group = Reservation::query()->create(['status' => 'confirmed', 'total_amount' => 10000, 'currency' => 'XOF', 'channel' => 'counter']);
        Payment::query()->create([
            'reservation_id' => $group->id, 'provider' => 'sandbox-money', 'provider_reference' => 'DEMO-GROUP-PAY',
            'idempotency_key' => 'demo-manifest-group', 'amount' => 10000, 'currency' => 'XOF', 'status' => 'confirmed', 'confirmed_at' => now(),
        ]);
        foreach ([['first_name' => 'Chantal', 'last_name' => 'Dossou', 'seat' => '1C'], ['first_name' => 'Didier', 'last_name' => 'Dossou', 'seat' => '1D']] as $member) {
            $passenger = ReservationPassenger::query()->create([
                'reservation_id' => $group->id, 'first_name' => $member['first_name'], 'last_name' => $member['last_name'], 'phone' => '+22997000012',
            ]);
            $seat = Seat::query()->firstOrCreate(['bus_id' => $bus->id, 'code' => $member['seat']], ['type' => 'standard', 'sellable' => true, 'status' => 'active']);
            $segment = ReservationSegment::query()->create([
                'reservation_id' => $group->id, 'reservation_passenger_id' => $passenger->id, 'leg_order' => 1,
                'departure_id' => $upcoming->id, 'seat_id' => $seat->id,
                'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id,
                'origin_stop_order' => $origin->stop_order, 'destination_stop_order' => $destination->stop_order,
                'status' => 'ticketed',
            ]);
            Ticket::query()->create([
                'uuid' => (string) Str::uuid(), 'reservation_id' => $group->id, 'reservation_passenger_id' => $passenger->id,
                'qr_code' => 'QR-'.Str::random(12), 'short_code' => 'DEMO-'.strtoupper(Str::random(6)),
                'status' => 'issued', 'issued_at' => now(),
            ]);
        }

        // 4. Boarded, on the departure that has already left.
        $this->passenger($bus, $origin, $destination, $departed, '2A', [
            'first_name' => 'Eudoxie', 'last_name' => 'Zannou', 'phone' => '+22997000013',
        ], segmentStatus: 'boarded', paymentStatus: 'confirmed', issueTicket: true);

        // 5. Absent — ticketed but never boarded, and the bus already left.
        $this->passenger($bus, $origin, $destination, $departed, '2B', [
            'first_name' => 'Firmin', 'last_name' => 'Agbessi', 'phone' => '+22997000014',
        ], segmentStatus: 'ticketed', paymentStatus: 'confirmed', issueTicket: true);
    }

    /** @param array{first_name: string, last_name: string, phone: string} $passengerData */
    private function passenger(Bus $bus, Stop $origin, Stop $destination, Departure $departure, string $seatCode, array $passengerData, string $segmentStatus, ?string $paymentStatus, bool $issueTicket): void
    {
        $reservation = Reservation::query()->create([
            'status' => 'confirmed', 'total_amount' => 5000, 'currency' => 'XOF', 'channel' => 'counter',
        ]);

        if ($paymentStatus !== null) {
            Payment::query()->create([
                'reservation_id' => $reservation->id, 'provider' => 'sandbox-money',
                'provider_reference' => 'DEMO-PAY-'.Str::random(8), 'idempotency_key' => 'demo-manifest-'.Str::random(8),
                'amount' => 5000, 'currency' => 'XOF', 'status' => $paymentStatus,
                'confirmed_at' => $paymentStatus === 'confirmed' ? now() : null,
            ]);
        }

        $passenger = ReservationPassenger::query()->create(array_merge(['reservation_id' => $reservation->id], $passengerData));

        $seat = Seat::query()->firstOrCreate(['bus_id' => $bus->id, 'code' => $seatCode], ['type' => 'standard', 'sellable' => true, 'status' => 'active']);

        ReservationSegment::query()->create([
            'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id, 'leg_order' => 1,
            'departure_id' => $departure->id, 'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id, 'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order, 'destination_stop_order' => $destination->stop_order,
            'status' => $segmentStatus,
        ]);

        if ($issueTicket) {
            Ticket::query()->create([
                'uuid' => (string) Str::uuid(), 'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
                'qr_code' => 'QR-'.Str::random(12), 'short_code' => 'DEMO-'.strtoupper(Str::random(6)),
                'status' => 'issued', 'issued_at' => now(),
            ]);
        }
    }
}
