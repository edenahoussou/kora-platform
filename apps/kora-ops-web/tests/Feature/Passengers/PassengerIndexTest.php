<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\IdentityAccess\Models\Agency;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PassengerIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(KoraBusRolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('passengers.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_passengers_list(): void
    {
        $agency = Agency::create([
            'code' => 'COTONOU-01',
            'name' => 'Agence Cotonou',
            'city' => 'Cotonou',
            'address' => 'Avenue Steinmetz',
            'phone' => '+22901234567',
        ]);

        $user = User::factory()->create([
            'agency_id' => $agency->id,
        ]);
        $user->assignRole('agency_agent');
        $this->actingAs($user);

        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'total_amount' => 12500,
            'status' => 'confirmed',
            'selling_agency_id' => $agency->id,
            'created_by_user_id' => $user->id,
        ]);

        ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'first_name' => 'Aïcha',
            'last_name' => 'Hounkpè',
            'phone' => '+22997241806',
            'nationality' => 'BJ',
            'document_type' => 'passport',
            'document_number' => 'PP123456',
        ]);

        $response = $this->get(route('passengers.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('passengers')
            ->has('passengers.data', 1)
            ->where('passengers.data.0.phone', '+22997241806')
            ->where('passengers.data.0.fullName', 'Aïcha Hounkpè')
            ->where('passengers.data.0.nationality', 'BJ')
            ->where('passengers.data.0.documentNumber', 'PP123456')
            ->where('passengers.data.0.ticketsCount', 0)
            ->has('filters')
        );
    }

    public function test_passengers_list_filters_by_search_query(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');
        $this->actingAs($user);

        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'total_amount' => 8500,
            'status' => 'confirmed',
            'created_by_user_id' => $user->id,
        ]);

        ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'first_name' => 'Jean',
            'last_name' => 'Kora',
            'phone' => '+22996824410',
            'nationality' => 'BJ',
        ]);

        ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'first_name' => 'Boris',
            'last_name' => 'Agossa',
            'phone' => '+22991257044',
            'nationality' => 'BJ',
        ]);

        $response = $this->get(route('passengers.index', ['q' => 'Agossa']));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('passengers')
            ->has('passengers.data', 1)
            ->where('passengers.data.0.fullName', 'Boris Agossa')
        );
    }

    public function test_passengers_show_returns_passenger_detail_json(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');
        $this->actingAs($user);

        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'total_amount' => 10000,
            'status' => 'confirmed',
            'created_by_user_id' => $user->id,
        ]);

        ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'first_name' => 'Fatou',
            'last_name' => 'Dossou',
            'phone' => '+22994113872',
            'nationality' => 'BJ',
            'document_type' => 'passport',
            'document_number' => 'PP998877',
        ]);

        $response = $this->get(route('passengers.show', ['phone' => '+22994113872']));

        $response->assertOk();
        $response->assertJson([
            'phone' => '+22994113872',
            'fullName' => 'Fatou Dossou',
            'ticketsCount' => 0,
            'tickets' => [],
            'nationality' => 'BJ',
            'documentNumber' => 'PP998877',
        ]);
    }

    public function test_passengers_show_returns_404_when_not_found(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');
        $this->actingAs($user);

        $response = $this->get(route('passengers.show', ['phone' => '+0000000000']));

        $response->assertNotFound();
    }

    public function test_passengers_list_filters_by_status(): void
    {
        $user = User::factory()->create();
        $user->assignRole('system_admin');
        $this->actingAs($user);

        $reservation = Reservation::create([
            'uuid' => (string) Str::uuid(),
            'total_amount' => 5000,
            'status' => 'confirmed',
            'created_by_user_id' => $user->id,
        ]);

        ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'first_name' => 'Complet',
            'last_name' => 'Voyageur',
            'phone' => '+22997000201',
            'nationality' => 'BJ',
            'document_type' => 'passport',
            'document_number' => 'PP554433',
        ]);

        ReservationPassenger::create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'first_name' => 'Incomplet',
            'last_name' => 'Voyageur',
            'phone' => '+22997000202',
            'nationality' => null,
            'document_type' => null,
            'document_number' => null,
        ]);

        $responseToVerify = $this->get(route('passengers.index', ['status' => 'to_verify']));
        $responseToVerify->assertOk();
        $responseToVerify->assertInertia(fn ($page) => $page
            ->component('passengers')
            ->where('passengers.data.0.phone', '+22997000202')
        );
    }
}
