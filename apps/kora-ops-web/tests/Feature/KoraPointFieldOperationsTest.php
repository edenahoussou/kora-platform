<?php

use App\Models\User;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Modules\KoraPoints\Models\KoraPointPlvOrder;
use App\Modules\KoraPoints\Models\KoraPointVisit;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('private');
    $this->koraUser = User::factory()->create();
    $this->koraUser->assignRole('commercial_director');
    $this->point = KoraPoint::factory()->create();
    $this->agent = User::factory()->create(['agency_id' => $this->point->agency_id]);
    $this->agent->assignRole('kora_point_agent');
});

it('records immutable visits, secures photos, and closes after KORA validates assigned actions', function (): void {
    $this->actingAs($this->koraUser)
        ->post(route('kora-points.visits.store', $this->point->uuid), [
            'visit_type' => 'quality',
            'score' => 8,
            'report' => 'Le point est propre et les informations sont affichées.',
            'photos' => [UploadedFile::fake()->image('visite.jpg', 100, 100)],
            'actions' => [[
                'label' => 'Renouveler l’affiche des horaires',
                'assignee_uuid' => $this->agent->uuid,
                'due_date' => now()->addDay()->toDateString(),
            ]],
        ])
        ->assertRedirect();

    $visit = KoraPointVisit::query()->with('actions', 'photos')->sole();
    $action = $visit->actions->sole();
    $photo = $visit->photos->sole();

    expect($visit->status)->toBe('in_progress')
        ->and($visit->score)->toBe(8)
        ->and($photo->uuid)->not->toBeEmpty()
        ->and(Storage::disk('private')->exists($photo->path))->toBeTrue();

    $otherAgent = User::factory()->create(['agency_id' => $this->point->agency_id]);
    $otherAgent->assignRole('kora_point_agent');
    $this->actingAs($otherAgent)
        ->post(route('kora-point.visits.actions.realize', [$visit->uuid, $action->uuid]), [
            'realization_note' => 'Tentative de modification par un autre agent.',
        ])
        ->assertForbidden();

    $this->actingAs($this->agent)
        ->post(route('kora-point.visits.actions.realize', [$visit->uuid, $action->uuid]), [
            'realization_note' => 'Affiche remplacée et vérifiée sur place.',
        ])
        ->assertRedirect();

    expect($action->fresh()->status)->toBe('realized')
        ->and($visit->fresh()->status)->toBe('in_progress');

    $this->actingAs($this->koraUser)
        ->post(route('kora-points.visits.actions.review', [$visit->uuid, $action->uuid]), [
            'operation' => 'return',
            'reason' => 'La photographie ne montre pas la nouvelle affiche.',
        ])
        ->assertRedirect();

    expect($action->fresh()->status)->toBe('returned')
        ->and($visit->fresh()->status)->toBe('in_progress');

    $this->actingAs($this->agent)
        ->post(route('kora-point.visits.actions.realize', [$visit->uuid, $action->uuid]), [
            'realization_note' => 'Nouvelle photographie jointe après remplacement.',
        ])
        ->assertRedirect();

    $this->actingAs($this->koraUser)
        ->post(route('kora-points.visits.actions.review', [$visit->uuid, $action->uuid]), ['operation' => 'validate'])
        ->assertRedirect();

    expect($action->fresh()->status)->toBe('validated')
        ->and($visit->fresh()->status)->toBe('closed');

    $this->actingAs($this->agent)
        ->get(route('kora-point.visits.photos.show', [$visit->uuid, $photo->uuid]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/jpeg');

    $this->actingAs($this->koraUser)
        ->get(route('kora-points.show', $this->point->uuid))
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/show')
            ->has('visits.data.0.uuid')
            ->missing('visits.data.0.id')
            ->missing('visits.data.0.point_id')
            ->has('visits.data.0.photos.0.uuid')
            ->missing('visits.data.0.photos.0.id')
        );

    $this->actingAs($this->agent)
        ->post(route('kora-points.visits.store', $this->point->uuid), [
            'visit_type' => 'quality',
            'score' => 6,
            'report' => 'L’agent ne peut pas créer de visite.',
        ])
        ->assertForbidden();
});

it('limits partner visit and PLV views to the organization in read-only mode', function (): void {
    $organization = KoraPointOrganization::factory()->create();
    $this->point->update(['organization_id' => $organization->getKey()]);
    $otherPoint = KoraPoint::factory()->create();
    $partner = User::factory()->create(['kora_point_organization_id' => $organization->getKey()]);
    $partner->assignRole('kora_point_supervisor');

    $this->actingAs($this->koraUser)
        ->post(route('kora-points.visits.store', $this->point->uuid), [
            'visit_type' => 'commercial_follow_up',
            'score' => 9,
            'report' => 'Le point a confirmé une amélioration de la présentation.',
            'photos' => [UploadedFile::fake()->image('organisation.png', 100, 100)],
        ])
        ->assertRedirect();
    $visit = KoraPointVisit::query()->sole();

    $this->actingAs($partner)
        ->get(route('kora-partner.visits'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/field-operations')
            ->where('kind', 'visits')
            ->where('visits.total', 1)
            ->where('visits.data.0.uuid', $visit->uuid)
            ->missing('visits.data.0.id')
            ->missing('visits.data.0.point_id')
            ->has('visits.data.0.photos.0.uuid')
        );

    $this->actingAs($partner)
        ->get(route('kora-partner.visits', ['point_uuid' => $otherPoint->uuid]))
        ->assertNotFound();

    $this->actingAs($partner)
        ->post(route('kora-points.visits.store', $this->point->uuid), [
            'visit_type' => 'quality',
            'score' => 8,
            'report' => 'Un partenaire ne peut pas enregistrer de visite.',
        ])
        ->assertForbidden();

    $this->actingAs($this->koraUser)
        ->post(route('kora-points.plv-orders.store', $this->point->uuid), ['support' => 'bache', 'quantity' => 2])
        ->assertRedirect();

    $order = KoraPointPlvOrder::query()->sole();
    $this->actingAs($partner)
        ->get(route('kora-partner.plv'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/field-operations')
            ->where('kind', 'plv')
            ->where('plv_orders.total', 1)
            ->where('plv_orders.data.0.uuid', $order->uuid)
            ->missing('plv_orders.data.0.id')
            ->missing('plv_orders.data.0.point_id')
        );

    $photo = $visit->photos()->firstOrFail();
    $this->actingAs($partner)
        ->get(route('kora-partner.visits.photos.show', [$visit->uuid, $photo->uuid]))
        ->assertOk();
});

it('closes a visit immediately when no follow-up action is created', function (): void {
    $this->actingAs($this->koraUser)
        ->post(route('kora-points.visits.store', $this->point->uuid), [
            'visit_type' => 'plv_control',
            'score' => 7,
            'report' => 'Les supports PLV présents sont lisibles.',
        ])
        ->assertRedirect();

    expect(KoraPointVisit::query()->sole()->status)->toBe('closed');
});

it('tracks the PLV order lifecycle and retains an internal cancellation reason', function (): void {
    $this->actingAs($this->koraUser)
        ->post(route('kora-points.plv-orders.store', $this->point->uuid), [
            'support' => 'affiches_a3',
            'quantity' => 12,
        ])
        ->assertRedirect();

    $deliveredOrder = KoraPointPlvOrder::query()->sole();
    expect($deliveredOrder->status)->toBe('prepared');

    $this->actingAs($this->koraUser)
        ->patch(route('kora-points.plv-orders.update', $deliveredOrder->uuid), ['operation' => 'deliver'])
        ->assertRedirect();
    expect($deliveredOrder->fresh()->status)->toBe('delivered')
        ->and($deliveredOrder->fresh()->delivered_at)->not->toBeNull();

    $this->actingAs($this->koraUser)
        ->patch(route('kora-points.plv-orders.update', $deliveredOrder->uuid), ['operation' => 'install'])
        ->assertRedirect();
    expect($deliveredOrder->fresh()->status)->toBe('installed')
        ->and($deliveredOrder->fresh()->installed_at)->not->toBeNull();

    $this->actingAs($this->koraUser)
        ->post(route('kora-points.plv-orders.store', $this->point->uuid), [
            'support' => 'panneau',
            'quantity' => 1,
        ])
        ->assertRedirect();
    $cancelledOrder = KoraPointPlvOrder::query()->latest('id')->firstOrFail();

    $this->actingAs($this->koraUser)
        ->patch(route('kora-points.plv-orders.update', $cancelledOrder->uuid), [
            'operation' => 'cancel',
            'reason' => 'Le support demandé est indisponible.',
        ])
        ->assertRedirect();

    expect($cancelledOrder->fresh()->status)->toBe('cancelled')
        ->and($cancelledOrder->fresh()->cancellation_reason)->toBe('Le support demandé est indisponible.');

    $this->actingAs($this->agent)
        ->post(route('kora-points.plv-orders.store', $this->point->uuid), [
            'support' => 'autocollants',
            'quantity' => 5,
        ])
        ->assertForbidden();
});
