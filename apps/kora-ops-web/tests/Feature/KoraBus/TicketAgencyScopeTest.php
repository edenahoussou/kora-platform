<?php

use App\Jobs\SendReservationWhatsAppNotificationJob;
use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Ticketing\Actions\GenerateTicketPdfAction;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Queue::fake();
});

/** @return array{seller: User, reservation: Reservation, ticket: Ticket} */
function agencyTicketScenario(?Agency $agency, int $amount = 7000): array
{
    $seller = User::factory()->create(['agency_id' => $agency?->id]);
    $seller->assignRole('agency_agent');
    $reservation = Reservation::query()->create([
        'created_by_user_id' => $seller->id, 'selling_agency_id' => $agency?->id,
        'status' => 'ticketed', 'total_amount' => $amount, 'currency' => 'XOF', 'channel' => 'counter',
        'total_snapshot' => ['passenger_allocations' => [$amount]],
    ]);
    $passenger = ReservationPassenger::query()->create([
        'reservation_id' => $reservation->id, 'first_name' => 'Ada', 'last_name' => 'Kora',
        'passenger_index' => 0, 'status' => 'active',
    ]);
    $ticket = Ticket::query()->create([
        'reservation_id' => $reservation->id, 'reservation_passenger_id' => $passenger->id,
        'short_code' => Str::upper(Str::random(12)), 'qr_code' => 'qr-'.Str::uuid(), 'status' => 'issued', 'issued_at' => now(),
    ]);

    return compact('seller', 'reservation', 'ticket');
}

it('limits agency lists and all direct ticket actions to the frozen selling agency', function (): void {
    $agency = Agency::factory()->create();
    $own = agencyTicketScenario($agency);
    $other = agencyTicketScenario(Agency::factory()->create(), 9000);
    agencyTicketScenario(null, 5000);
    $colleague = User::factory()->create(['agency_id' => $agency->id]);
    $colleague->assignRole('agency_agent');
    $this->actingAs($colleague)->get(route('tickets'))->assertInertia(fn ($page) => $page
        ->where('tickets.total', 1)->where('tickets.data.0.uuid', $own['ticket']->uuid)
        ->where('kpis.total', 1)->where('kpis.totalRevenue', 7000)
        ->missing('tickets.data.0.id')->missing('tickets.data.0.selling_agency_id'));
    $this->get(route('tickets.show', $own['ticket']))->assertOk();
    $this->mock(GenerateTicketPdfAction::class)->shouldReceive('execute')->once()->withArgs(fn (Ticket $ticket) => $ticket->is($own['ticket']))
        ->andReturn(['content' => '%PDF-test', 'filename' => 'ticket.pdf']);
    $this->get(route('tickets.pdf', $own['ticket']))->assertOk();
    $this->post(route('tickets.whatsapp', $own['ticket']))->assertRedirect();
    $this->get(route('tickets.show', $other['ticket']))->assertForbidden();
    $this->get(route('tickets.pdf', $other['ticket']))->assertForbidden();
    $this->post(route('tickets.whatsapp', $other['ticket']))->assertForbidden();
    Queue::assertPushed(SendReservationWhatsAppNotificationJob::class, 1);
    $this->get(route('tickets', ['q' => $other['ticket']->short_code]))->assertInertia(fn ($page) => $page->where('tickets.total', 0));

    $own['seller']->forceFill(['agency_id' => $other['seller']->agency_id])->save();
    $this->get(route('tickets.show', $own['ticket']))->assertOk()->assertSee($agency->name);
    $this->actingAs($own['seller']->fresh())->get(route('tickets.show', $own['ticket']))->assertForbidden();
});

it('requires a distinct network permission and always prioritizes an assigned agency', function (): void {
    $agency = Agency::factory()->create();
    agencyTicketScenario($agency);
    $unknown = agencyTicketScenario(null);
    $user = User::factory()->create(['agency_id' => null]);
    foreach (['passenger', 'conductor', 'agency_agent'] as $role) {
        $user->syncRoles($role);
        $this->actingAs($user)->get(route('tickets'))->assertForbidden();
    }
    $user->syncRoles('general_management');
    $this->get(route('tickets'))->assertInertia(fn ($page) => $page->where('tickets.total', 2)->where('kpis.totalRevenue', 14000));
    $this->get(route('tickets.show', $unknown['ticket']))->assertOk();
    $user->forceFill(['agency_id' => $agency->id])->save();
    $this->get(route('tickets'))->assertInertia(fn ($page) => $page->where('tickets.total', 1));
    $this->get(route('tickets.show', $unknown['ticket']))->assertForbidden();
    $user->syncRoles([]);
    $this->get(route('tickets'))->assertForbidden();
});

it('sums issued and used passenger allocations once and matches the printable ticket', function (): void {
    $scenario = agencyTicketScenario(Agency::factory()->create());
    $scenario['reservation']->update(['total_amount' => 12000, 'total_snapshot' => ['passenger_allocations' => [7000, 5000]]]);
    $passenger = ReservationPassenger::query()->create(['reservation_id' => $scenario['reservation']->id, 'first_name' => 'Ben', 'last_name' => 'Kora', 'passenger_index' => 1, 'status' => 'active']);
    $ticket = Ticket::query()->create(['reservation_id' => $scenario['reservation']->id, 'reservation_passenger_id' => $passenger->id, 'short_code' => 'GROUP-SECOND', 'qr_code' => 'qr-'.Str::uuid(), 'status' => 'used', 'issued_at' => now()]);
    $this->actingAs($scenario['seller'])->get(route('tickets'))->assertInertia(fn ($page) => $page->where('kpis.totalRevenue', 12000)->where('tickets.total', 2));
    $this->get(route('tickets', ['q' => 'GROUP-SECOND']))->assertInertia(fn ($page) => $page->where('tickets.data.0.amount', 5000));
    $this->get(route('tickets.show', $ticket))->assertOk()->assertSee('5 000 XOF');
    $ticket->update(['status' => 'cancelled']);
    $this->get(route('tickets'))->assertInertia(fn ($page) => $page->where('kpis.totalRevenue', 7000)->where('kpis.cancelled', 1));
});
