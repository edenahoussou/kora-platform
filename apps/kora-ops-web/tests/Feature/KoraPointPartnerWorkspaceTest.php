<?php

use App\Models\User;
use App\Modules\IdentityAccess\Jobs\SendKoraPointWebAccessJob;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\KoraPoints\Models\KoraPartnerDocument;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Queue::fake([SendKoraPointWebAccessJob::class]);
});

function koraPartnerWorkspaceUser(?KoraPointOrganization $organization, string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->forceFill(['kora_point_organization_id' => $organization?->getKey()])->save();
    $user->assignRole($role);

    return $user;
}

it('routes organization members to the dedicated partner dashboard', function (string $role): void {
    $organization = KoraPointOrganization::factory()->create(['name' => 'Organisation Partenaire']);
    $user = koraPartnerWorkspaceUser($organization, $role);

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('kora-partner.dashboard'));

    $this->assertAuthenticatedAs($user);
    $this->get(route('kora-partner.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/dashboard')
            ->where('organization.name', 'Organisation Partenaire'));
})->with(['supervisor' => 'kora_point_supervisor', 'treasurer' => 'kora_point_treasurer']);

it('logs out a partner account with no recognized organization and flashes a clear toast', function (): void {
    $user = koraPartnerWorkspaceUser(null, 'kora_point_supervisor');

    $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
        ->assertRedirect(route('login'))
        ->assertSessionHas('inertia.flash_data.toast.message', 'Votre espace partenaire n’est pas reconnu. Votre session a été fermée. Contactez l’administrateur KORA.');

    $this->assertGuest();
});

it('logs out an active partner session as soon as the organization is suspended or closed', function (string $status, string $message): void {
    $organization = KoraPointOrganization::factory()->create();
    $user = koraPartnerWorkspaceUser($organization, 'kora_point_treasurer');

    $this->actingAs($user)->get(route('kora-partner.dashboard'))->assertOk();
    $organization->update(['status' => $status]);

    $this->get(route('kora-partner.dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHas('inertia.flash_data.toast.message', $message);

    $this->assertGuest();
})->with([
    'suspended' => ['suspended', 'L’accès à votre organisation est suspendu. Votre session a été fermée. Contactez l’administrateur KORA.'],
    'closed' => ['closed', 'L’espace de votre organisation est clôturé. Votre session a été fermée. Contactez l’administrateur KORA.'],
]);

it('keeps the session after an ordinary forbidden page requested after the partner landing', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $user = koraPartnerWorkspaceUser($organization, 'kora_point_supervisor');

    $this->actingAs($user)->get(route('kora-partner.dashboard'))->assertOk();
    $this->get(route('kora-points'))->assertForbidden();

    $this->assertAuthenticatedAs($user);
});

it('reuses the admin point list for partners while scoping it to their organization', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $otherOrganization = KoraPointOrganization::factory()->create();
    $closedPoint = KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'status' => 'closed',
    ]);
    KoraPoint::factory()->create(['organization_id' => $otherOrganization->getKey()]);
    $supervisor = koraPartnerWorkspaceUser($organization, 'kora_point_supervisor');

    $this->actingAs($supervisor)
        ->get(route('kora-partner.points', ['state' => 'closed']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/index')
            ->where('partnerWorkspace.organization_uuid', $organization->uuid)
            ->has('points.data', 1)
            ->where('points.data.0.uuid', $closedPoint->uuid)
            ->where('points.data.0.status', 'CLOSED')
            ->where('points.data.0.raw_status', 'closed'));
});

it('lets a supervisor create an allocated point and automatically issues its agent web access', function (): void {
    $organization = KoraPointOrganization::factory()->create(['ceiling_amount' => 500_000]);
    $supervisor = koraPartnerWorkspaceUser($organization, 'kora_point_supervisor');

    $this->actingAs($supervisor)
        ->post(route('kora-points.store'), [
            'organization_uuid' => $organization->uuid,
            'name' => 'Point Godomey',
            'city' => 'Abomey-Calavi',
            'department' => 'Atlantique',
            'country' => 'BJ',
            'responsible_name' => 'Afi Agent',
            'email' => 'afi.godomey@example.com',
            'ceiling_amount' => 200_000,
        ])
        ->assertRedirect(route('kora-partner.points'));

    $point = KoraPoint::query()->where('organization_id', $organization->getKey())->sole();
    $agent = User::query()->where('email', 'afi.godomey@example.com')->firstOrFail();

    expect($point->agency->name)->toBe('Point Godomey')
        ->and($point->ceiling_amount)->toBe(200_000)
        ->and($agent->hasRole('kora_point_agent'))->toBeTrue()
        ->and($agent->agency_id)->toBe($point->agency_id)
        ->and($agent->latestKoraPointWebAccessInvitation()->value('status'))->toBe(KoraPointWebAccessInvitation::STATUS_PENDING_DELIVERY);
});

it('lets a supervisor update a point without submitting its read-only responsible name', function (): void {
    $organization = KoraPointOrganization::factory()->create([
        'ceiling_amount' => 4_500_000,
    ]);
    $point = KoraPoint::factory()->create([
        'organization_id' => $organization->getKey(),
        'ceiling_amount' => 4_500_000,
        'commission_basis_points' => null,
        'settlement_calendar' => null,
    ]);
    $supervisor = koraPartnerWorkspaceUser($organization, 'kora_point_supervisor');
    $originalResponsibleName = $point->responsible_name;

    $this->from(route('kora-partner.points'))
        ->actingAs($supervisor)
        ->put(route('kora-points.update', $point->uuid), [
            'organization_uuid' => $organization->uuid,
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'department' => $point->agency->department,
            'phone' => $point->phone,
            'address' => $point->address,
            'ceiling_amount' => 25_000,
        ])
        ->assertRedirect(route('kora-partner.points'))
        ->assertSessionHasNoErrors()
        ->assertSessionHas('inertia.flash_data.toast.message', 'KORA Point enregistré.');

    expect($point->refresh()->ceiling_amount)->toBe(25_000)
        ->and($point->responsible_name)->toBe($originalResponsibleName);
});

it('blocks supervisors from transferring a point or editing organization management pages', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $otherOrganization = KoraPointOrganization::factory()->create();
    $point = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);
    $supervisor = koraPartnerWorkspaceUser($organization, 'kora_point_supervisor');

    $this->actingAs($supervisor)
        ->put(route('kora-points.update', $point->uuid), [
            'organization_uuid' => $otherOrganization->uuid,
            'name' => $point->agency->name,
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'responsible_name' => $point->responsible_name,
            'ceiling_amount' => $point->ceiling_amount,
        ])
        ->assertForbidden();

    $this->get(route('kora-points.organizations.index'))->assertForbidden();
    $this->assertAuthenticatedAs($supervisor);
    expect($point->refresh()->organization_id)->toBe($organization->getKey());
});

it('lets both organization roles upload documents to their own organization only', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $otherOrganization = KoraPointOrganization::factory()->create();
    $treasurer = koraPartnerWorkspaceUser($organization, 'kora_point_treasurer');

    $this->actingAs($treasurer)
        ->post(route('kora-points.organizations.documents', $organization->uuid), [
            'kind' => 'agreement',
            'title' => 'Convention signée',
            'file' => UploadedFile::fake()->create('convention.pdf', 30, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->post(route('kora-points.organizations.documents', $otherOrganization->uuid), [
        'kind' => 'agreement',
        'title' => 'Autre organisation',
        'file' => UploadedFile::fake()->create('autre.pdf', 30, 'application/pdf'),
    ])->assertForbidden();

    expect(KoraPartnerDocument::query()->where('organization_id', $organization->getKey())->count())->toBe(1)
        ->and(KoraPartnerDocument::query()->where('organization_id', $otherOrganization->getKey())->count())->toBe(0);
});

it('does not let a treasurer manage point allocations', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $point = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);
    $treasurer = koraPartnerWorkspaceUser($organization, 'kora_point_treasurer');

    $this->actingAs($treasurer)
        ->put(route('kora-points.update', $point->uuid), [
            'name' => 'Point modifié',
            'city' => $point->agency->city,
            'country' => $point->agency->country,
            'responsible_name' => $point->responsible_name,
            'ceiling_amount' => $point->ceiling_amount,
        ])
        ->assertForbidden();

    expect($point->refresh()->agency->name)->not->toBe('Point modifié');
});
