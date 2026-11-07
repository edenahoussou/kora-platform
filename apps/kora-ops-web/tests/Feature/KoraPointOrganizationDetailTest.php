<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\IdentityAccess\Jobs\SendKoraPointWebAccessJob;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointRefund;
use App\Modules\KoraPoints\Models\KoraPointRemittance;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Models\Payment;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
    Queue::fake([SendKoraPointWebAccessJob::class]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
});

function koraOrganizationDetailSale(KoraPoint $point, int $amount = 15000): KoraPointSale
{
    $agent = User::factory()->create(['agency_id' => $point->agency_id]);
    $agent->assignRole('kora_point_agent');
    $reservation = Reservation::query()->create([
        'created_by_user_id' => $agent->getKey(),
        'payer_user_id' => User::factory()->create()->getKey(),
        'selling_agency_id' => $point->agency_id,
        'channel' => 'counter',
        'status' => 'paid',
        'total_amount' => $amount,
        'currency' => 'XOF',
        'total_snapshot' => ['passenger_allocations' => [$amount]],
    ]);
    $reservation->passengers()->create([
        'first_name' => 'Paul',
        'last_name' => 'Kora',
        'passenger_index' => 0,
        'status' => 'active',
    ]);
    $payment = Payment::query()->create([
        'reservation_id' => $reservation->getKey(),
        'method' => 'cash',
        'provider' => 'counter',
        'status' => 'confirmed',
        'amount' => $amount,
        'currency' => 'XOF',
        'idempotency_key' => (string) Str::uuid(),
        'payer_phone' => '+22990000002',
        'confirmed_at' => now(),
    ]);

    return app(RecordKoraPointSaleAction::class)->execute($reservation, $payment, $agent);
}

it('renders an internal organization detail with UUID-only point and member props', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $point = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);

    $supervisor = User::factory()->create([
        'kora_point_organization_id' => $organization->getKey(),
    ]);
    $supervisor->assignRole('kora_point_supervisor');
    $treasurer = User::factory()->create([
        'kora_point_organization_id' => $organization->getKey(),
    ]);
    $treasurer->assignRole('kora_point_treasurer');
    $agent = User::factory()->create(['agency_id' => $point->agency_id]);
    $agent->forceFill(['kora_point_organization_id' => $organization->getKey()])->save();
    $agent->assignRole('kora_point_agent');

    $this->actingAs($this->admin)
        ->get(route('kora-points.organizations.show', $organization->uuid))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/organizations/show')
            ->where('organization.uuid', $organization->uuid)
            ->has('points.data', 1)
            ->where('points.data.0.uuid', $point->uuid)
            ->has('members.data', 2)
            ->where('members.total', 2)
            ->where('permissions.canManageMembers', true)
            ->missing('organization.id')
            ->missing('points.data.0.id')
            ->missing('members.data.0.id')
            ->missing('members.data.1.id'));

    $partner = User::factory()->create([
        'kora_point_organization_id' => $organization->getKey(),
    ]);
    $partner->assignRole('kora_point_supervisor');

    $this->actingAs($partner)
        ->get(route('kora-points.organizations.show', $organization->uuid))
        ->assertForbidden();
});

it('creates and invites an organization supervisor with server-bound country and organization', function (): void {
    $organization = KoraPointOrganization::factory()->create(['country' => 'TG']);

    $this->actingAs($this->admin)
        ->post(route('kora-points.organizations.members.store', $organization->uuid), [
            'name' => '  Afi Mensah  ',
            'email' => '  AFI.ORG@example.com ',
            'phone' => '+22890000013',
            'role' => 'kora_point_supervisor',
            'agency_uuid' => (string) Str::uuid(),
            'status' => 'suspended',
        ])
        ->assertRedirect();

    $member = User::query()->where('email', 'afi.org@example.com')->firstOrFail();
    $invitation = $member->latestKoraPointWebAccessInvitation()->firstOrFail();

    expect($member->hasRole('kora_point_supervisor'))->toBeTrue()
        ->and($member->status->value)->toBe('active')
        ->and($member->agency_id)->toBeNull()
        ->and($member->kora_point_organization_id)->toBe($organization->getKey())
        ->and($member->country_code)->toBe('TG')
        ->and($invitation)->toBeInstanceOf(KoraPointWebAccessInvitation::class)
        ->and($invitation->status)->toBe('pending_delivery')
        ->and(AuthOtpChallenge::query()->where('user_id', $member->getKey())->exists())->toBeFalse();
});

it('rejects unsupported organization roles, duplicate email and missing member permissions', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $existingUser = User::factory()->create(['email' => 'already.used@organization.test']);

    $this->from(route('kora-points.organizations.show', $organization->uuid))
        ->actingAs($this->admin)
        ->post(route('kora-points.organizations.members.store', $organization->uuid), [
            'name' => 'Agent refusé',
            'email' => 'invalid-email',
            'role' => 'system_admin',
        ])
        ->assertSessionHasErrors(['email', 'role']);

    $this->from(route('kora-points.organizations.show', $organization->uuid))
        ->actingAs($this->admin)
        ->post(route('kora-points.organizations.members.store', $organization->uuid), [
            'name' => 'Adresse déjà utilisée',
            'email' => $existingUser->email,
            'role' => 'kora_point_treasurer',
        ])
        ->assertSessionHasErrors('email');

    $usersOnly = User::factory()->create();
    $usersOnly->givePermissionTo('users.manage');
    $this->actingAs($usersOnly)
        ->post(route('kora-points.organizations.members.store', $organization->uuid), [
            'name' => 'Sans permission KORA POINT',
            'email' => 'no.permission@organization.test',
            'role' => 'kora_point_treasurer',
        ])
        ->assertForbidden();

    expect(User::query()->where('email', 'no.permission@organization.test')->exists())->toBeFalse();
});

it('keeps organization-filtered KPIs independent of the visible pagination page', function (): void {
    $organizations = KoraPointOrganization::factory()->count(31)->create([
        'name' => 'KPI Network',
        'status' => 'active',
        'ceiling_amount' => 10000,
    ]);
    $expectedExposure = 0;

    foreach ($organizations as $index => $organization) {
        $point = KoraPoint::factory()->create([
            'organization_id' => $organization->getKey(),
            'ceiling_amount' => 10000,
            'status' => $index === 30 ? 'closed' : 'active',
        ]);

        if ($index === 0) {
            $sale = koraOrganizationDetailSale($point, 5000);
            KoraPointEntry::query()->create([
                'point_id' => $point->getKey(),
                'sale_id' => $sale->getKey(),
                'actor_id' => $this->admin->getKey(),
                'event_key' => 'organization-kpi-exposure',
                'type' => 'sale',
                'amount' => 3000,
                'commission_delta' => 0,
            ]);
            $expectedExposure = (int) $point->entries()->sum('amount');
        }
    }

    $this->actingAs($this->admin)
        ->get(route('kora-points.organizations.index', ['q' => 'KPI Network', 'status' => 'active']))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('organizations.total', 31)
            ->has('organizations.data', 30)
            ->where('index_summary.organizations', 31)
            ->where('index_summary.active_points', 30)
            ->where('index_summary.exposure', $expectedExposure)
            ->where('index_summary.available_allocation', 10000));
});

it('attributes grouped remittances per point and exposes refund commission corrections', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $firstPoint = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);
    $secondPoint = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);
    $firstSale = koraOrganizationDetailSale($firstPoint);
    $secondSale = koraOrganizationDetailSale($secondPoint);

    $remittance = KoraPointRemittance::query()->create([
        'organization_id' => $organization->getKey(),
        'declared_by' => $this->admin->getKey(),
        'status' => 'submitted',
        'amount' => 12000,
        'method' => 'bank_transfer',
        'reference' => 'ORG-REMIT-01',
        'submitted_at' => now(),
    ]);
    $remittance->lines()->create(['sale_id' => $firstSale->getKey(), 'amount' => 7000]);
    $remittance->lines()->create(['sale_id' => $secondSale->getKey(), 'amount' => 5000]);

    $refund = KoraPointRefund::query()->create([
        'sale_id' => $firstSale->getKey(),
        'requested_by' => $this->admin->getKey(),
        'status' => 'confirmed',
        'amount' => 1000,
        'reason' => 'Remboursement test',
    ]);
    KoraPointEntry::query()->create([
        'point_id' => $firstPoint->getKey(),
        'sale_id' => $firstSale->getKey(),
        'actor_id' => $this->admin->getKey(),
        'event_key' => 'refund:'.$refund->uuid,
        'type' => 'refund',
        'amount' => 0,
        'commission_delta' => -300,
    ]);

    $this->actingAs($this->admin)
        ->get(route('kora-points.organizations.show', $organization->uuid))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->where('remittances.data.0.group_amount', 12000)
            ->has('remittances.data.0.points', 2)
            ->where('remittances.data.0.points.0.amount', 7000)
            ->where('remittances.data.0.points.1.amount', 5000)
            ->where('refunds.data.0.uuid', $refund->uuid)
            ->where('refunds.data.0.commission_reversed', 300)
            ->missing('remittances.data.0.id')
            ->missing('remittances.data.0.points.0.id'));
});

it('blocks organization closure while any point has exposure and never closes points implicitly', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $indebtedPoint = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);
    $sale = koraOrganizationDetailSale($indebtedPoint);
    KoraPointEntry::query()->create([
        'point_id' => $indebtedPoint->getKey(),
        'sale_id' => $sale->getKey(),
        'actor_id' => $this->admin->getKey(),
        'event_key' => 'organization-closure-exposure',
        'type' => 'sale',
        'amount' => 1200,
        'commission_delta' => 0,
    ]);

    $this->from(route('kora-points.organizations.show', $organization->uuid))
        ->actingAs($this->admin)
        ->patch(route('kora-points.organizations.status', $organization->uuid), ['status' => 'closed'])
        ->assertSessionHasErrors('status');

    $clearOrganization = KoraPointOrganization::factory()->create();
    $activePoint = KoraPoint::factory()->create([
        'organization_id' => $clearOrganization->getKey(),
        'status' => 'active',
        'ceiling_amount' => 25000,
    ]);

    $this->actingAs($this->admin)
        ->patch(route('kora-points.organizations.status', $clearOrganization->uuid), ['status' => 'closed'])
        ->assertRedirect();

    expect($clearOrganization->refresh()->status)->toBe('closed')
        ->and($activePoint->refresh()->status)->toBe('active')
        ->and($activePoint->ceiling_amount)->toBe(25000);
});
