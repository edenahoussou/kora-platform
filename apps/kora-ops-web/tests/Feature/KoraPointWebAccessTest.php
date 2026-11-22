<?php

use App\Models\User;
use App\Modules\IdentityAccess\Actions\IssueKoraPointWebAccessAction;
use App\Modules\IdentityAccess\Jobs\SendKoraPointWebAccessJob;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\IdentityAccess\Models\KoraPointWebAccessInvitation;
use App\Modules\IdentityAccess\Services\KoraPointWebAccessDelivery;
use App\Modules\IdentityAccess\Services\KoraPointWebAccessGuard;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointOrganization;
use App\Notifications\IdentityAccess\KoraPointWebAccessNotification;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    $this->withoutVite();
    Queue::fake([SendKoraPointWebAccessJob::class]);

    $this->commercial = User::factory()->create();
    $this->commercial->assignRole('commercial_director');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('system_admin');
});

function koraPointAccessInvitation(User $user, string $status = 'pending_delivery', ?string $password = null): KoraPointWebAccessInvitation
{
    if ($password !== null) {
        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();
    }

    return KoraPointWebAccessInvitation::query()->create([
        'user_id' => $user->getKey(),
        'status' => $status,
        'delivery_channel' => $status === 'sent' ? 'email' : null,
        'sent_at' => $status === 'sent' ? now() : null,
        'expires_at' => $status === 'sent' ? now()->addHours(72) : null,
    ]);
}

function koraPointAccessAccount(?int $agencyId = null, string $role = 'kora_point_agent', string $password = 'initial-temporary-password'): User
{
    $user = User::factory()->create([
        'agency_id' => $agencyId,
        'password' => $password,
        'must_change_password' => true,
    ]);
    $user->assignRole($role);

    return $user;
}

it('creates a standalone point agent with a tracked web access and no registration otp', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'code' => 'KP-ACCESS-1',
            'name' => 'Point Akpakpa',
            'city' => 'Cotonou',
            'country' => 'BJ',
            'responsible_name' => 'Afi Kora',
            'phone' => '+22997000001',
            'email' => 'afi.access@example.com',
        ])
        ->assertRedirect();

    $point = KoraPoint::query()->whereHas('agency', fn ($query) => $query->where('code', 'KP-ACCESS-1'))->sole();
    $agent = User::query()->where('email', 'afi.access@example.com')->firstOrFail();
    $invitation = $agent->latestKoraPointWebAccessInvitation()->firstOrFail();
    $flash = session()->get('inertia.flash_data', []);

    expect($agent->hasRole('kora_point_agent'))->toBeTrue()
        ->and($agent->agency_id)->toBe($point->agency_id)
        ->and($invitation->status)->toBe('pending_delivery')
        ->and(AuthOtpChallenge::query()->where('user_id', $agent->getKey())->exists())->toBeFalse()
        ->and($flash)->not->toHaveKey('temporaryPassword')
        ->and($flash)->not->toHaveKey('otpChallengeUuid')
        ->and($flash['toast']['message'])->toContain('Accès web initial');
});

it('creates a point member with the point agency and tracked web invitation', function (): void {
    $point = KoraPoint::factory()->create();
    $otherAgency = Agency::factory()->create(['country' => 'TG', 'kind' => 'partner']);

    $this->actingAs($this->admin)
        ->post(route('kora-points.members.store', $point->uuid), [
            'name' => 'Agent du Point',
            'email' => 'point.member@example.com',
            'phone' => '+22997000010',
            'role' => 'system_admin',
            'agency_uuid' => $otherAgency->uuid,
            'status' => 'suspended',
        ])
        ->assertRedirect();

    $member = User::query()->where('email', 'point.member@example.com')->firstOrFail();
    $invitation = $member->latestKoraPointWebAccessInvitation()->firstOrFail();

    expect($member->hasRole('kora_point_agent'))->toBeTrue()
        ->and($member->status->value)->toBe('active')
        ->and($member->agency_id)->toBe($point->agency_id)
        ->and($member->country_code)->toBe($point->agency->country)
        ->and($member->kora_point_organization_id)->toBeNull()
        ->and($invitation->status)->toBe('pending_delivery')
        ->and(AuthOtpChallenge::query()->where('user_id', $member->getKey())->exists())->toBeFalse()
        ->and(session('inertia.flash_data.toast.message'))->toContain('Invitation en attente de livraison');

    $this->get(route('kora-points.show', $point->uuid))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('permissions.canManageMembers', true)
            ->where('members.data.0.uuid', $member->uuid)
            ->missing('members.data.0.id')
            ->missing('point.id'));
});

it('rejects invalid and duplicate e-mail addresses for point members', function (): void {
    $point = KoraPoint::factory()->create();
    $initialUsers = User::query()->count();
    $existingUser = User::factory()->create(['email' => 'already.used@example.com']);

    $this->from(route('kora-points.show', $point->uuid))
        ->actingAs($this->admin)
        ->post(route('kora-points.members.store', $point->uuid), [
            'name' => 'Adresse invalide',
            'email' => 'pas-une-adresse',
        ])
        ->assertSessionHasErrors('email');

    $this->from(route('kora-points.show', $point->uuid))
        ->actingAs($this->admin)
        ->post(route('kora-points.members.store', $point->uuid), [
            'name' => 'Adresse déjà utilisée',
            'email' => $existingUser->email,
        ])
        ->assertSessionHasErrors('email');

    expect(User::query()->count())->toBe($initialUsers + 1)
        ->and(KoraPoint::query()->whereKey($point->getKey())->exists())->toBeTrue();
});

it('requires both user and point management permissions to create a point member', function (): void {
    $point = KoraPoint::factory()->create();
    $usersOnly = User::factory()->create();
    $usersOnly->givePermissionTo('users.manage');

    $this->actingAs($this->commercial)
        ->post(route('kora-points.members.store', $point->uuid), [
            'name' => 'Sans gestion utilisateurs',
            'email' => 'no.users.permission@example.com',
        ])
        ->assertForbidden();

    $this->actingAs($usersOnly)
        ->post(route('kora-points.members.store', $point->uuid), [
            'name' => 'Sans gestion KORA POINT',
            'email' => 'no.point.permission@example.com',
        ])
        ->assertForbidden();

    expect(User::query()->whereIn('email', [
        'no.users.permission@example.com',
        'no.point.permission@example.com',
    ])->exists())->toBeFalse();
});

it('requires a unique email before creating a standalone point account', function (): void {
    $initialUsers = User::query()->count();

    $this->from('/kora-points')
        ->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Point sans courriel',
            'city' => 'Cotonou',
            'responsible_name' => 'Agent sans courriel',
            'phone' => '+22997000002',
        ])
        ->assertSessionHasErrors('email');

    expect(KoraPoint::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe($initialUsers);
});

it('blocks an existing email on point creation without changing that account password', function (): void {
    $existingUser = User::factory()->create([
        'email' => 'existing.agent@example.com',
        'password' => 'existing-account-password',
    ]);
    $existingUser->assignRole('agency_agent');

    $this->from('/kora-points')
        ->actingAs($this->commercial)
        ->post(route('kora-points.store'), [
            'name' => 'Point duplicate',
            'city' => 'Cotonou',
            'responsible_name' => 'Agent déjà présent',
            'phone' => '+22997000003',
            'email' => 'existing.agent@example.com',
        ])
        ->assertSessionHasErrors('email');

    $message = session('errors')->get('email')[0];

    expect($message)->toContain('Affectez ce compte existant')
        ->and(Hash::check('existing-account-password', $existingUser->refresh()->password))->toBeTrue()
        ->and(KoraPoint::query()->count())->toBe(0)
        ->and(AuthOtpChallenge::query()->where('user_id', $existingUser->getKey())->exists())->toBeFalse();
});

it('blocks an existing email on organization member creation without changing its password', function (): void {
    $existingUser = User::factory()->create([
        'email' => 'existing.supervisor@example.com',
        'password' => 'existing-supervisor-password',
    ]);

    $this->from(route('kora-points.organizations.index'))
        ->actingAs($this->commercial)
        ->post(route('kora-points.organizations.store'), [
            'name' => 'Organisation test',
            'members' => [[
                'name' => 'Superviseure déjà présente',
                'email' => 'existing.supervisor@example.com',
                'role' => 'kora_point_supervisor',
            ]],
        ])
        ->assertSessionHasErrors('members.0.email');

    expect(session('errors')->get('members.0.email')[0])->toContain('Affectez ce compte existant')
        ->and(Hash::check('existing-supervisor-password', $existingUser->refresh()->password))->toBeTrue()
        ->and(KoraPointOrganization::query()->count())->toBe(0);
});

it('creates organization members with tracked web access for supervisor and treasurer roles', function (): void {
    $this->actingAs($this->commercial)
        ->post(route('kora-points.organizations.store'), [
            'name' => 'Organisation accès web',
            'country' => 'BJ',
            'commission_basis_points' => 300,
            'ceiling_amount' => 1500000,
            'settlement_calendar' => [
                'frequency' => 'weekly',
                'weekday' => 1,
                'time' => '18:00',
                'timezone' => 'Africa/Porto-Novo',
            ],
            'members' => [
                ['name' => 'Ada Superviseure', 'email' => 'ada.supervisor@example.com', 'phone' => '+22997000004', 'role' => 'kora_point_supervisor'],
                ['name' => 'Ben Trésorier', 'email' => 'ben.treasurer@example.com', 'phone' => '+22997000005', 'role' => 'kora_point_treasurer'],
            ],
        ])
        ->assertRedirect();

    $organization = KoraPointOrganization::query()->where('name', 'Organisation accès web')->firstOrFail();
    $supervisor = User::query()->where('email', 'ada.supervisor@example.com')->firstOrFail();
    $treasurer = User::query()->where('email', 'ben.treasurer@example.com')->firstOrFail();

    expect($supervisor->hasRole('kora_point_supervisor'))->toBeTrue()
        ->and($treasurer->hasRole('kora_point_treasurer'))->toBeTrue()
        ->and($supervisor->kora_point_organization_id)->toBe($organization->getKey())
        ->and($treasurer->kora_point_organization_id)->toBe($organization->getKey())
        ->and($supervisor->latestKoraPointWebAccessInvitation()->value('status'))->toBe('pending_delivery')
        ->and($treasurer->latestKoraPointWebAccessInvitation()->value('status'))->toBe('pending_delivery')
        ->and(AuthOtpChallenge::query()->whereIn('user_id', [$supervisor->getKey(), $treasurer->getKey()])->exists())->toBeFalse()
        ->and(session('inertia.flash_data.toast.message'))->toContain('0 délivré(s), 2 en attente');
});

it('uses the same initial web access flow when creating KORA POINT roles in Users', function (): void {
    $agency = Agency::factory()->create(['kind' => 'partner']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Agent créé depuis Utilisateurs',
            'email' => 'users.kora.agent@example.com',
            'phone' => '+22997000006',
            'country_code' => 'BJ',
            'status' => 'active',
            'agency_uuid' => $agency->uuid,
            'role' => 'kora_point_agent',
        ])
        ->assertRedirect(route('users', absolute: false));

    $user = User::query()->where('email', 'users.kora.agent@example.com')->firstOrFail();
    $flash = session()->get('inertia.flash_data', []);

    expect($user->latestKoraPointWebAccessInvitation()->value('status'))->toBe('pending_delivery')
        ->and(AuthOtpChallenge::query()->where('user_id', $user->getKey())->exists())->toBeFalse()
        ->and($flash)->not->toHaveKey('temporaryPassword')
        ->and($flash)->not->toHaveKey('otpChallengeUuid');
});

it('requires and assigns an organization when creating a KORA POINT supervisor from Users', function (): void {
    $organization = KoraPointOrganization::factory()->create(['name' => 'Organisation depuis Utilisateurs']);

    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Superviseur depuis Utilisateurs',
            'email' => 'users.kora.supervisor@example.com',
            'status' => 'active',
            'role' => 'kora_point_supervisor',
            'kora_point_organization_uuid' => $organization->uuid,
        ])
        ->assertRedirect(route('users', absolute: false));

    $user = User::query()->where('email', 'users.kora.supervisor@example.com')->firstOrFail();

    expect($user->kora_point_organization_id)->toBe($organization->getKey())
        ->and($user->latestKoraPointWebAccessInvitation()->value('status'))->toBe('pending_delivery')
        ->and(AuthOtpChallenge::query()->where('user_id', $user->getKey())->exists())->toBeFalse();
});

it('does not create a KORA POINT supervisor from Users without an organization', function (): void {
    $this->actingAs($this->admin)
        ->post(route('users.store'), [
            'name' => 'Superviseur sans organisation',
            'email' => 'users.kora.unassigned@example.com',
            'status' => 'active',
            'role' => 'kora_point_supervisor',
        ])
        ->assertSessionHasErrors('kora_point_organization_uuid');

    expect(User::query()->where('email', 'users.kora.unassigned@example.com')->exists())->toBeFalse();
});

it('includes all required credentials and first login instructions in email', function (): void {
    $notification = new KoraPointWebAccessNotification(
        name: 'Afi Kora',
        role: 'Agent KORA POINT',
        pointOrOrganization: 'Point Akpakpa (KP-001)',
        email: 'afi@example.com',
        temporaryPassword: 'known-temporary-password',
        loginUrl: 'https://ops.example.com/login',
    );
    $email = $notification->toMail(new AnonymousNotifiable);
    $emailContent = implode(' ', [...$email->introLines, ...$email->outroLines]);

    foreach (['Agent KORA POINT', 'Point Akpakpa', '72 heures', 'première connexion'] as $expected) {
        expect($emailContent)->toContain($expected);
    }

    expect($email->actionUrl)->toBe('https://ops.example.com/login')
        ->and($email->greeting)->toContain('Afi Kora')
        ->and($email->viewData['welcomeCredentials'])->toMatchArray([
            'Identifiant' => 'afi@example.com',
            'Mot de passe provisoire' => 'known-temporary-password',
        ]);
});

it('sends email and starts the 72 hour expiry only after accepted delivery', function (): void {
    $point = KoraPoint::factory()->create();
    $user = koraPointAccessAccount($point->agency_id, password: 'email-first-password');
    $invitation = koraPointAccessInvitation($user, password: 'email-first-password');
    $delivery = Mockery::mock(KoraPointWebAccessDelivery::class);
    $delivery->shouldReceive('sendEmail')->once()->ordered()->with($user->email, Mockery::type(KoraPointWebAccessNotification::class));

    $this->travelTo(now()->startOfSecond());
    (new SendKoraPointWebAccessJob($invitation->uuid, 'email-first-password'))->handle($delivery);

    $sent = $invitation->refresh();
    expect($sent->status)->toBe('sent')
        ->and($sent->delivery_channel)->toBe('email')
        ->and($sent->sent_at)->toEqual(now())
        ->and($sent->expires_at)->toEqual(now()->addHours(72))
        ->and($sent->getAttributes())->not->toHaveKey('temporary_password');
});

it('marks an email delivery failure without sending SMS or logging credentials', function (): void {
    $point = KoraPoint::factory()->create();
    $user = koraPointAccessAccount($point->agency_id, password: 'never-log-this-password');
    $invitation = koraPointAccessInvitation($user, password: 'never-log-this-password');
    $delivery = Mockery::mock(KoraPointWebAccessDelivery::class);
    $delivery->shouldReceive('sendEmail')->once()->andThrow(new RuntimeException('never-log-this-password'));
    Log::spy();

    $job = new SendKoraPointWebAccessJob($invitation->uuid, 'never-log-this-password');
    expect(fn () => $job->handle($delivery))->toThrow(RuntimeException::class, 'La livraison de l’accès web KORA POINT a échoué.');
    expect($invitation->refresh()->status)->toBe('pending_delivery');

    $job->failed();

    Log::shouldHaveReceived('warning')->once()->with(
        'KORA POINT web access could not be delivered.',
        Mockery::on(fn (array $context): bool => ! str_contains(json_encode($context), 'never-log-this-password')),
    );

    expect($invitation->refresh()->status)->toBe('failed')
        ->and($invitation->delivery_channel)->toBeNull()
        ->and($invitation->expires_at)->toBeNull()
        ->and(Hash::check('never-log-this-password', $user->refresh()->password))->toBeTrue();
});

it('encrypts the queued job payload and does not persist a temporary password in invitation records', function (): void {
    expect(is_subclass_of(SendKoraPointWebAccessJob::class, ShouldBeEncrypted::class))->toBeTrue();

    $user = koraPointAccessAccount(password: 'encrypted-job-password');
    $invitation = app(IssueKoraPointWebAccessAction::class)->execute($user, $this->admin, 'encrypted-job-password');

    expect($invitation->getAttributes())->not->toHaveKey('temporary_password')
        ->and($invitation->getAttributes())->not->toHaveKey('password')
        ->and(Hash::check('encrypted-job-password', $user->refresh()->password))->toBeTrue();
});

it('rotates the password and replaces the prior invitation when an admin reissues access', function (): void {
    $user = koraPointAccessAccount(password: 'old-temporary-password');
    $oldInvitation = koraPointAccessInvitation($user, 'sent', 'old-temporary-password');

    $this->actingAs($this->admin)
        ->post(route('users.password.reset', ['user' => $user->uuid]))
        ->assertRedirect();

    $newInvitation = $user->latestKoraPointWebAccessInvitation()->firstOrFail();
    $flash = session()->get('inertia.flash_data', []);

    expect($oldInvitation->refresh()->status)->toBe('replaced')
        ->and($newInvitation->status)->toBe('pending_delivery')
        ->and($user->refresh()->must_change_password)->toBeTrue()
        ->and(Hash::check('old-temporary-password', $user->password))->toBeFalse()
        ->and($flash)->not->toHaveKey('temporaryPassword')
        ->and($flash['toast']['message'])->toContain('renvoyez l’accès depuis Utilisateurs');
});

it('rejects login with an expired temporary password and persists the expired state', function (): void {
    $user = koraPointAccessAccount(password: 'expired-temporary-password');
    $invitation = koraPointAccessInvitation($user, 'sent', 'expired-temporary-password');
    $invitation->update(['expires_at' => now()->addHours(72)]);
    $this->travel(73)->hours();

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'expired-temporary-password',
    ])
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect($invitation->refresh()->status)->toBe('expired');
});

it('requires a first password change and marks the invitation used after the change', function (): void {
    $point = KoraPoint::factory()->create();
    $user = koraPointAccessAccount($point->agency_id, password: 'first-login-password');
    $invitation = koraPointAccessInvitation($user, 'sent', 'first-login-password');

    $this->actingAs($user)
        ->get(route('kora-points'))
        ->assertRedirect(route('security.edit'));

    $this->actingAs($user)
        ->put(route('user-password.update'), [
            'current_password' => 'first-login-password',
            'password' => 'Kora@Access2026',
            'password_confirmation' => 'Kora@Access2026',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and(Hash::check('Kora@Access2026', $user->password))->toBeTrue()
        ->and($invitation->refresh()->status)->toBe('used')
        ->and($invitation->used_at)->not->toBeNull();
});

it('routes organization members through first password change to a workspace scoped to their organization', function (): void {
    $organization = KoraPointOrganization::factory()->create(['name' => 'Organisation du superviseur']);
    $otherOrganization = KoraPointOrganization::factory()->create(['name' => 'Autre organisation']);
    $ownPoint = KoraPoint::factory()->create(['organization_id' => $organization->getKey()]);
    KoraPoint::factory()->create(['organization_id' => $otherOrganization->getKey()]);

    $user = koraPointAccessAccount(null, 'kora_point_supervisor', 'org-temporary-password');
    $user->forceFill(['kora_point_organization_id' => $organization->getKey()])->save();
    $invitation = koraPointAccessInvitation($user, 'sent', 'org-temporary-password');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'org-temporary-password',
    ])->assertRedirect(route('security.edit'));

    $securityRedirect = $this->get(route('security.edit'));
    $securityRedirect->assertRedirect('/settings/profile?tab=security');
    $this->get('/settings/profile?tab=security')->assertOk();

    $this->put(route('user-password.update'), [
        'current_password' => 'org-temporary-password',
        'password' => 'Kora@Access2026',
        'password_confirmation' => 'Kora@Access2026',
    ])->assertRedirect(route('kora-partner.dashboard'));

    expect($user->refresh()->must_change_password)->toBeFalse()
        ->and($invitation->refresh()->status)->toBe('used');

    $this->get(route('kora-partner.dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/partner/dashboard')
            ->where('organization.uuid', $organization->uuid)
            ->has('points', 1)
            ->where('points.0.uuid', $ownPoint->uuid));

    $this->get(route('kora-partner.points'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kora-points/index')
            ->where('partnerWorkspace.organization_uuid', $organization->uuid)
            ->has('points.data', 1)
            ->where('points.data.0.uuid', $ownPoint->uuid));

    $this->get(route('kora-points.organizations.index'))->assertForbidden();
    $this->assertAuthenticatedAs($user);

    $this->post(route('logout'));

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'Kora@Access2026',
    ])->assertRedirect(route('kora-partner.dashboard'));
});

it('routes a KORA POINT agent to the agent dashboard after login', function (): void {
    $agency = Agency::factory()->create(['kind' => 'partner']);
    KoraPoint::factory()->create(['agency_id' => $agency->getKey()]);
    $user = User::factory()->create([
        'agency_id' => $agency->getKey(),
        'password' => 'existing-agent-password',
        'must_change_password' => false,
    ]);
    $user->assignRole('kora_point_agent');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'existing-agent-password',
    ])->assertRedirect(route('kora-point.dashboard'));
});

it('keeps historical KORA POINT accounts without invitations able to use their existing password', function (): void {
    $legacyUser = koraPointAccessAccount(password: 'legacy-password');

    expect(app(KoraPointWebAccessGuard::class)->canUseTemporaryPassword($legacyUser))->toBeTrue()
        ->and($legacyUser->latestKoraPointWebAccessInvitation()->exists())->toBeFalse();
});
