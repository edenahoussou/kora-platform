<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Ticketing\Models\Ticket;
use App\Notifications\Passengers\PassengerAccountErasedNotification;
use Carbon\CarbonImmutable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassengerErasureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KoraBusRolePermissionSeeder::class);
        Notification::fake();
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');

        return $user;
    }

    private function agent(): User
    {
        $user = User::factory()->create();
        $user->assignRole('agency_agent');

        return $user;
    }

    /**
     * @return array{reservation: Reservation, passenger: ReservationPassenger, account: User, ticket: Ticket}
     */
    private function passengerFixture(User $actor): array
    {
        $account = User::factory()->create([
            'name' => 'Awa Bio',
            'phone' => '+22997000300',
            'email' => 'awa.bio@example.com',
        ]);
        $account->assignRole('passenger');

        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'total_amount' => 9000,
            'status' => 'confirmed',
            'created_by_user_id' => $actor->id,
            'payer_user_id' => $account->id,
            'payer_snapshot' => [
                'type' => 'personal',
                'display_name' => 'Awa Bio',
                'phone' => '+22997000300',
                'account_uuid' => $account->uuid,
            ],
        ]);

        $passenger = ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'user_id' => $account->id,
            'first_name' => 'Awa',
            'last_name' => 'Bio',
            'phone' => '+22997000300',
            'email' => 'awa.bio@example.com',
            'nationality' => 'BJ',
            'document_type' => 'passport',
            'document_number' => 'PP778899',
        ]);

        $ticket = Ticket::create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'qr_code' => 'qr-'.Str::uuid(),
            'short_code' => 'KBR-TEST-01',
            'status' => 'issued',
            'issued_at' => now(),
        ]);

        return compact('reservation', 'passenger', 'account', 'ticket');
    }

    public function test_export_requires_sensitive_data_permission(): void
    {
        $agent = $this->agent();
        $this->actingAs($agent);
        $this->passengerFixture($agent);

        $this->get(route('passengers.export', ['phone' => '+22997000300']))
            ->assertForbidden();
    }

    public function test_export_returns_zip_and_registers_erasure_request(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->passengerFixture($admin);

        $response = $this->get(route('passengers.export', ['phone' => '+22997000300']));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');

        $this->assertDatabaseHas('passenger_erasure_requests', [
            'status' => 'exported',
            'requested_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'passenger_data_exported',
        ]);
    }

    public function test_export_includes_segment_departure_schedule(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        ['reservation' => $reservation, 'passenger' => $passenger] = $this->passengerFixture($admin);
        $departure = $this->departureFixture($reservation, $passenger);

        $response = $this->get(route('passengers.export', ['phone' => '+22997000300']));

        $response->assertOk();

        $zip = new ZipArchive;
        $zip->open($response->getFile()->getPathname());
        $profile = json_decode((string) $zip->getFromName('profile.json'), true);
        $zip->close();

        $this->assertSame(
            $departure->refresh()->scheduledDepartureAt()->toIso8601String(),
            $profile['reservations'][0]['segments'][0]['departure_time'],
        );
    }

    private function departureFixture(Reservation $reservation, ReservationPassenger $passenger): Departure
    {
        $originPhysical = PhysicalStop::create([
            'name' => 'Cotonou Gare',
            'code' => 'COT'.strtoupper(bin2hex(random_bytes(2))),
            'city' => 'Cotonou',
            'country' => 'BJ',
            'status' => 'active',
        ]);
        $destinationPhysical = PhysicalStop::create([
            'name' => 'Djougou Centre',
            'code' => 'DJG'.strtoupper(bin2hex(random_bytes(2))),
            'city' => 'Djougou',
            'country' => 'BJ',
            'status' => 'active',
        ]);

        $line = Line::create([
            'code' => 'K'.strtoupper(bin2hex(random_bytes(4))),
            'name' => 'Cotonou-Djougou',
            'countries_crossed' => ['BJ'],
            'status' => 'active',
        ]);
        $origin = Stop::create([
            'line_id' => $line->id,
            'physical_stop_id' => $originPhysical->id,
            'name' => 'Cotonou',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'stop_order' => 1,
            'status' => 'active',
        ]);
        $destination = Stop::create([
            'line_id' => $line->id,
            'physical_stop_id' => $destinationPhysical->id,
            'name' => 'Djougou',
            'city' => 'Djougou',
            'country' => 'BJ',
            'stop_order' => 2,
            'status' => 'active',
        ]);

        $bus = Bus::create([
            'registration_number' => 'BJ-'.uniqid(),
            'brand' => 'KORA',
            'model' => 'Coach',
            'seat_count' => 2,
            'status' => 'active',
        ]);
        $seat = Seat::create([
            'bus_id' => $bus->id,
            'code' => '12A',
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]);

        $departure = Departure::create([
            'line_id' => $line->id,
            'bus_id' => $bus->id,
            'departure_date' => '2026-09-07',
            'departure_time' => '08:00',
            'scheduled_at' => CarbonImmutable::parse('2026-09-07 08:00', 'Africa/Porto-Novo'),
            'departure_timezone' => 'Africa/Porto-Novo',
            'stop_times' => [
                ['stop_uuid' => $origin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
                ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 240, 'timezone' => 'Africa/Porto-Novo'],
            ],
            'estimated_arrival_at' => CarbonImmutable::parse('2026-09-07 12:00', 'Africa/Porto-Novo'),
            'status' => 'scheduled',
        ]);

        ReservationSegment::create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => 1,
            'destination_stop_order' => 2,
            'leg_order' => 1,
            'direction' => 'outbound',
            'passenger_index' => 0,
            'status' => 'ticketed',
        ]);

        return $departure;
    }

    public function test_destroy_requires_permission(): void
    {
        $agent = $this->agent();
        $this->actingAs($agent);
        $this->passengerFixture($agent);

        $this->delete(route('passengers.destroy', ['phone' => '+22997000300']), [
            'password' => 'password',
        ])->assertForbidden();
    }

    public function test_destroy_rejects_wrong_password(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $fixture = $this->passengerFixture($admin);

        $this->delete(route('passengers.destroy', ['phone' => '+22997000300']), [
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseHas('reservation_passengers', [
            'id' => $fixture['passenger']->id,
            'phone' => '+22997000300',
        ]);
    }

    public function test_destroy_anonymizes_passenger_and_erases_account(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $fixture = $this->passengerFixture($admin);

        $response = $this->delete(route('passengers.destroy', ['phone' => '+22997000300']), [
            'password' => 'password',
        ]);

        $response->assertRedirect(route('passengers.index'));

        $record = $fixture['passenger']->fresh();
        $this->assertNull($record->phone);
        $this->assertNull($record->email);
        $this->assertNull($record->document_number);
        $this->assertNull($record->nationality);
        $this->assertNull($record->user_id);
        $this->assertSame('Passager', $record->first_name);
        $this->assertSame('Supprimé', $record->last_name);
        $this->assertNotNull($record->anonymized_at);

        $account = User::withTrashed()->find($fixture['account']->id);
        $this->assertTrue($account->trashed());
        $this->assertSame('Compte supprimé', $account->name);
        $this->assertStringStartsWith('erased+', $account->email);
        $this->assertNull($account->phone);
        $this->assertCount(0, $account->tokens);

        $snapshot = $fixture['reservation']->fresh()->payer_snapshot;
        $this->assertSame('Compte supprimé', $snapshot['display_name']);
        $this->assertNull($snapshot['phone']);

        // Intégrité financière : le billet est conservé.
        $this->assertDatabaseHas('tickets', ['id' => $fixture['ticket']->id]);

        $this->assertDatabaseHas('passenger_erasure_requests', [
            'status' => 'executed',
            'requested_by_user_id' => $admin->id,
        ]);
        $this->assertDatabaseHas('activity_log', [
            'description' => 'passenger_account_erased',
        ]);

        Notification::assertSentOnDemand(
            PassengerAccountErasedNotification::class,
            fn (PassengerAccountErasedNotification $notification, array $channels, object $notifiable): bool => ($notifiable->routes['mail'] ?? null) === 'awa.bio@example.com'
                && is_string($notifiable->routes['whatsapp'] ?? null),
        );
    }

    public function test_destroy_removes_passenger_from_directory(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $this->passengerFixture($admin);

        $this->delete(route('passengers.destroy', ['phone' => '+22997000300']), [
            'password' => 'password',
        ]);

        $this->get(route('passengers.show', ['phone' => '+22997000300']))
            ->assertNotFound();

        $this->get(route('passengers.index'))
            ->assertInertia(fn ($page) => $page->has('passengers.data', 0));
    }

    public function test_destroy_returns_404_for_unknown_phone(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        $this->delete(route('passengers.destroy', ['phone' => '+0000000000']), [
            'password' => 'password',
        ])->assertNotFound();

        Notification::assertNothingSent();
    }
}
