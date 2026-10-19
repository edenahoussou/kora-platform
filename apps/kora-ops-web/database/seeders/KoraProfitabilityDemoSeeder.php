<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Expenses\Models\Expense;
use App\Modules\Expenses\Models\ExpenseCategory;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Payments\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Jeu de démonstration de la rentabilité : départs passés, ventes, dépenses
 * avec centre de coût et champs de maintenance. Idempotent.
 */
class KoraProfitabilityDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(KoraExpenseCategorySeeder::class);

        $actor = User::query()->orderBy('id')->first() ?? User::factory()->create();
        $agency = $this->agency();
        [$line, $origin, $destination] = $this->line();
        $buses = [$this->bus('DEMO-P1'), $this->bus('DEMO-P2')];
        $categories = ExpenseCategory::query()->get()->keyBy('code');

        foreach ($buses as $bus) {
            $this->seats($bus);
        }

        $today = CarbonImmutable::now('Africa/Porto-Novo')->startOfDay();

        foreach (range(1, 5) as $offset) {
            $date = $today->subDays($offset);
            $departure = $this->departure($line, $buses[$offset % 2], $date);
            $this->sales($departure, $line, $origin, $destination, $agency, $date);

            $this->expense($agency, $actor, $categories, 'fuel', [
                'bus_id' => $buses[$offset % 2]->id,
                'amount' => 18_000 + $offset * 1_500,
                'occurred_at' => $date->setTime(7, 0),
                'payee_label' => 'Station KORA Cotonou',
                'description' => 'Plein avant départ',
            ]);
        }

        $this->expense($agency, $actor, $categories, 'maintenance', [
            'bus_id' => $buses[0]->id,
            'amount' => 65_000,
            'occurred_at' => $today->subDays(2)->setTime(14, 0),
            'payee_label' => 'Garage Akpakpa',
            'description' => 'Remplacement plaquettes de frein',
            'maintenance' => [
                'maintenance_type' => 'corrective',
                'garage_label' => 'Garage Akpakpa',
                'parts_amount' => 45_000,
                'labor_amount' => 20_000,
            ],
        ]);
        $this->expense($agency, $actor, $categories, 'insurance', [
            'bus_id' => $buses[1]->id,
            'amount' => 90_000,
            'occurred_at' => $today->subDays(4)->setTime(9, 0),
            'payee_label' => 'Assurance NSIA',
            'description' => 'Prime mensuelle flotte',
        ]);
        $this->expense($agency, $actor, $categories, 'leasing', [
            'line_id' => $line->id,
            'amount' => 240_000,
            'occurred_at' => $today->subDays(3)->setTime(9, 0),
            'payee_label' => 'Leasing KORA',
            'description' => 'Échéance mensuelle bus',
        ]);
        $this->expense($agency, $actor, $categories, 'agency_supplies', [
            'amount' => 14_000,
            'occurred_at' => $today->subDays(1)->setTime(11, 0),
            'payee_label' => 'Papeterie Étoile',
            'description' => 'Fournitures guichet',
        ]);
        $this->expense($agency, $actor, $categories, 'fuel', [
            'bus_id' => $buses[0]->id,
            'amount' => 22_000,
            'occurred_at' => $today->setTime(6, 30),
            'payee_label' => 'Station KORA Porto-Novo',
            'description' => 'Plein à valider',
            'status' => 'pending',
        ]);
    }

    private function agency(): Agency
    {
        return Agency::query()->firstOrCreate(
            ['code' => 'AG-DEMO-PROFIT'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Agence Démo Rentabilité',
                'city' => 'Cotonou',
                'country' => 'BJ',
                'status' => 'active',
            ],
        );
    }

    /**
     * @return array{0: Line, 1: Stop, 2: Stop}
     */
    private function line(): array
    {
        $line = Line::query()->firstOrCreate(
            ['code' => 'DEMO-PROFIT'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Cotonou — Parakou (démo)',
                'type' => 'nationale',
                'bidirectional' => false,
                'distance_km' => 420,
                'estimated_duration_minutes' => 480,
                'status' => 'active',
            ],
        );

        $originPhysical = PhysicalStop::query()->firstOrCreate(
            ['code' => 'DEMO-PROFIT-CTN'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Cotonou Démo',
                'city' => 'Cotonou',
                'country' => 'BJ',
                'status' => 'active',
            ],
        );
        $destinationPhysical = PhysicalStop::query()->firstOrCreate(
            ['code' => 'DEMO-PROFIT-PKO'],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'Parakou Démo',
                'city' => 'Parakou',
                'country' => 'BJ',
                'status' => 'active',
            ],
        );

        $origin = Stop::query()->firstOrCreate(
            ['line_id' => $line->id, 'stop_order' => 1],
            [
                'uuid' => (string) Str::uuid(),
                'physical_stop_id' => $originPhysical->id,
                'name' => 'Cotonou',
                'city' => 'Cotonou',
                'country' => 'BJ',
                'km_from_origin' => 0,
                'status' => 'active',
            ],
        );
        $destination = Stop::query()->firstOrCreate(
            ['line_id' => $line->id, 'stop_order' => 2],
            [
                'uuid' => (string) Str::uuid(),
                'physical_stop_id' => $destinationPhysical->id,
                'name' => 'Parakou',
                'city' => 'Parakou',
                'country' => 'BJ',
                'km_from_origin' => 420,
                'status' => 'active',
            ],
        );

        return [$line, $origin, $destination];
    }

    private function bus(string $registration): Bus
    {
        return Bus::query()->firstOrCreate(
            ['registration_number' => $registration],
            [
                'uuid' => (string) Str::uuid(),
                'brand' => 'Toyota',
                'model' => 'Coaster',
                'seat_count' => 30,
                'status' => 'active',
            ],
        );
    }

    private function seats(Bus $bus): void
    {
        foreach (['1A', '1B', '2A', '2B'] as $code) {
            Seat::query()->firstOrCreate(
                ['bus_id' => $bus->id, 'code' => $code],
                [
                    'uuid' => (string) Str::uuid(),
                    'type' => 'standard',
                    'sellable' => true,
                    'status' => 'active',
                ],
            );
        }
    }

    private function departure(Line $line, Bus $bus, CarbonImmutable $date): Departure
    {
        return Departure::query()->firstOrCreate(
            [
                'line_id' => $line->id,
                'bus_id' => $bus->id,
                'departure_date' => $date->toDateString(),
                'departure_time' => '08:00',
            ],
            [
                'uuid' => (string) Str::uuid(),
                'departure_timezone' => 'Africa/Porto-Novo',
                'status' => 'departed',
                'departed_at' => $date->setTime(8, 5),
                'arrived_at' => $date->setTime(16, 10),
            ],
        );
    }

    private function sales(
        Departure $departure,
        Line $line,
        Stop $origin,
        Stop $destination,
        Agency $agency,
        CarbonImmutable $date,
    ): void {
        $seats = Seat::query()->where('bus_id', $departure->bus_id)->orderBy('code')->take(3)->get();

        foreach ($seats as $index => $seat) {
            $reference = 'KBR-DEMO-'.$departure->id.'-'.$index;
            $reservation = Reservation::query()->firstOrCreate(
                ['reference' => $reference],
                [
                    'uuid' => (string) Str::uuid(),
                    'selling_agency_id' => $agency->id,
                    'status' => 'paid',
                    'total_amount' => 8_500,
                    'currency' => 'XOF',
                    'channel' => 'counter',
                    'total_snapshot' => [
                        'passenger_allocations' => [8_500],
                        'legs' => [[
                            'leg_order' => 1,
                            'departure_uuid' => $departure->uuid,
                            'final_amount' => 8_500,
                        ]],
                    ],
                ],
            );

            ReservationSegment::query()->firstOrCreate(
                ['reservation_id' => $reservation->id, 'seat_id' => $seat->id, 'departure_id' => $departure->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'leg_order' => 1,
                    'passenger_index' => 0,
                    'origin_stop_id' => $origin->id,
                    'destination_stop_id' => $destination->id,
                    'origin_stop_order' => 1,
                    'destination_stop_order' => 2,
                    'status' => 'ticketed',
                ],
            );

            Payment::query()->firstOrCreate(
                ['reservation_id' => $reservation->id, 'provider_reference' => $reference],
                [
                    'uuid' => (string) Str::uuid(),
                    'provider' => 'demo',
                    'idempotency_key' => 'demo-'.$reference,
                    'amount' => 8_500,
                    'currency' => 'XOF',
                    'method' => 'cash',
                    'status' => 'confirmed',
                    'confirmed_at' => $date->setTime(7, 30)->utc(),
                ],
            );
        }
    }

    /**
     * @param  Collection<string, ExpenseCategory>  $categories
     * @param  array<string, mixed>  $attributes
     */
    private function expense(Agency $agency, User $actor, Collection $categories, string $code, array $attributes): void
    {
        $category = $categories->get($code);
        if ($category === null) {
            return;
        }

        $maintenance = $attributes['maintenance'] ?? null;
        unset($attributes['maintenance']);

        $payeeReference = 'DEMO-'.$code.'-'.$attributes['occurred_at']->format('YmdHis');
        $status = (string) ($attributes['status'] ?? 'approved');
        $expense = Expense::query()->firstOrCreate(
            [
                'agency_id' => $agency->id,
                'payee_reference' => $payeeReference,
            ],
            array_merge([
                'uuid' => (string) Str::uuid(),
                'expense_category_id' => $category->id,
                'status' => $status,
                'payment_channel' => 'cash',
                'currency' => 'XOF',
                'created_by' => $actor->id,
                'approved_by' => $status === 'approved' ? $actor->id : null,
                'approved_at' => $status === 'approved' ? $attributes['occurred_at'] : null,
            ], $attributes, ['payee_reference' => $payeeReference]),
        );

        if (is_array($maintenance) && $expense->maintenanceDetails === null) {
            $expense->maintenanceDetails()->create($maintenance);
        }
    }
}
