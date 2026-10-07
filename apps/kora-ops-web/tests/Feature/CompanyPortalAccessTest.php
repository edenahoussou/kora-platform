<?php

use App\Models\User;
use App\Modules\CompanyAccounts\Models\CompanyAccount;
use App\Modules\CompanyAccounts\Models\CompanyAccountMember;
use App\Notifications\IdentityAccess\ResetPassword;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function portalAccount(array $attributes = []): CompanyAccount
{
    return CompanyAccount::query()->create([
        'name' => 'Société Horizon',
        'type' => 'company',
        'reference' => 'ORG-2026-HZN',
        'monthly_credit_limit' => 500000,
        'status' => 'active',
        ...$attributes,
    ]);
}

function portalUser(string $role = 'member', array $accountAttributes = []): array
{
    $user = User::factory()->create();
    $account = portalAccount($accountAttributes);

    $member = CompanyAccountMember::query()->create([
        'company_account_id' => $account->id,
        'user_id' => $user->id,
        'role' => $role,
        'status' => 'active',
    ]);

    return [$user, $account, $member];
}

it('redirects guests to login', function (): void {
    $this->get(route('company-portal.index'))->assertRedirect(route('login'));
});

it('denies the portal to a user without membership', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('company-portal.index'))->assertForbidden();
});

it('denies the portal to a non-referent member', function (): void {
    [$user] = portalUser('member');

    $this->actingAs($user)->get(route('company-portal.index'))->assertForbidden();
});

it('denies the portal when the company account is inactive', function (): void {
    [$user, $account] = portalUser('referent');
    $account->update(['status' => 'inactive']);

    $this->actingAs($user)->get(route('company-portal.index'))->assertForbidden();
});

it('renders the portal for the referent of an active account', function (): void {
    [$user, $account] = portalUser('referent');
    $account->travelers()->create([
        'first_name' => 'Aïcha',
        'last_name' => 'Mensah',
        'phone' => '+22997000050',
        'status' => 'active',
    ]);
    $account->travelers()->create([
        'first_name' => 'Brice',
        'last_name' => 'Soglo',
        'phone' => '+22997000051',
        'status' => 'inactive',
    ]);

    $this->actingAs($user)
        ->get(route('company-portal.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('company-portal/index')
            ->where('account.uuid', $account->uuid)
            ->where('account.reference', 'ORG-2026-HZN')
            ->where('credit.monthlyLimit', 500000)
            ->where('credit.available', 500000)
            ->where('stats.travelersTotal', 2)
            ->where('stats.travelersActive', 1)
            ->where('stats.monthlyReservations', 0)
            ->missing('account.id'),
        );
});

it('redirects a pure referent to the portal instead of the dashboard', function (): void {
    [$user] = portalUser('referent');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('company-portal.index'));
});

it('keeps the dashboard accessible to internal users who are also referents', function (): void {
    [$user] = portalUser('referent');
    $user->assignRole('daf');

    $this->actingAs($user)->get(route('dashboard'))->assertSuccessful();
});

it('routes referents to the portal on the home redirect', function (): void {
    [$user] = portalUser('referent');

    $this->actingAs($user)->get('/')->assertRedirect(route('company-portal.index'));
});

it('lands the referent on the portal right after login', function (): void {
    [$user] = portalUser('referent');

    $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('company-portal.index'));
});

it('routes internal users to the dashboard on the home redirect', function (): void {
    $user = User::factory()->create();
    $user->assignRole('daf');

    $this->actingAs($user)->get('/')->assertRedirect(route('dashboard'));
});

it('exposes the company portal shared prop to referents only', function (): void {
    [$user, $account] = portalUser('referent');
    $plain = User::factory()->create();

    $this->actingAs($user)
        ->get(route('company-portal.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyPortal.uuid', $account->uuid),
        );

    $this->actingAs($plain)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('companyPortal', null),
        );
});

it('invites a referent, creates the account and sends a password reset link', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = portalAccount();

    $this->actingAs($admin)
        ->post(route('companies.referent.store', $account->uuid), [
            'name' => 'Awa Gnonlonfoun',
            'email' => 'awa@horizon.bj',
            'phone' => '97000001',
        ])
        ->assertRedirect(route('companies.index'));

    $referent = User::query()->where('email', 'awa@horizon.bj')->firstOrFail();

    expect($referent->hasRole('passenger'))->toBeTrue()
        ->and($referent->phone)->toBe('+22997000001');

    $member = CompanyAccountMember::query()
        ->where('company_account_id', $account->id)
        ->where('user_id', $referent->id)
        ->firstOrFail();

    expect($member->role->value)->toBe('referent')
        ->and($member->status->value)->toBe('active');

    Notification::assertSentTo($referent, ResetPassword::class);
});

it('reuses an existing passenger account for the referent invitation', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $account = portalAccount();
    $existing = User::factory()->create(['email' => 'bala@horizon.bj']);

    $this->actingAs($admin)
        ->post(route('companies.referent.store', $account->uuid), [
            'name' => 'Bala',
            'email' => 'bala@horizon.bj',
        ])
        ->assertRedirect(route('companies.index'));

    expect(
        CompanyAccountMember::query()
            ->where('company_account_id', $account->id)
            ->where('user_id', $existing->id)
            ->where('role', 'referent')
            ->where('status', 'active')
            ->exists(),
    )->toBeTrue();

    Notification::assertSentTo($existing, ResetPassword::class);
});

it('replaces the previous referent when inviting a new one', function (): void {
    Notification::fake();

    $admin = User::factory()->create();
    $admin->assignRole('daf');
    [$oldReferent, $account] = portalUser('referent');

    $this->actingAs($admin)
        ->post(route('companies.referent.store', $account->uuid), [
            'name' => 'Nouveau Responsable',
            'email' => 'nouveau@horizon.bj',
        ])
        ->assertRedirect(route('companies.index'));

    $oldMembership = CompanyAccountMember::query()
        ->where('company_account_id', $account->id)
        ->where('user_id', $oldReferent->id)
        ->firstOrFail();

    expect($oldMembership->status->value)->toBe('inactive');
});

it('rejects an internal user as referent', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('daf');
    $internal = User::factory()->create(['email' => 'agent@kora.bj']);
    $internal->assignRole('agency_agent');
    $account = portalAccount();

    $this->actingAs($admin)
        ->post(route('companies.referent.store', $account->uuid), [
            'name' => 'Agent',
            'email' => 'agent@kora.bj',
        ])
        ->assertSessionHasErrors('user_uuid');
});

it('requires the manage permission to invite a referent', function (): void {
    $user = User::factory()->create();
    $account = portalAccount();

    $this->actingAs($user)
        ->post(route('companies.referent.store', $account->uuid), [
            'name' => 'Awa',
            'email' => 'awa@horizon.bj',
        ])
        ->assertForbidden();
});

it('denies the companies admin index to a portal-only referent', function (): void {
    [$user] = portalUser('referent');
    $user->assignRole('passenger');

    $this->actingAs($user)->get(route('companies.index'))->assertForbidden();
});

it('denies the companies admin index to a passenger-only user', function (): void {
    $user = User::factory()->create();
    $user->assignRole('passenger');

    $this->actingAs($user)->get(route('companies.index'))->assertForbidden();
});

it('keeps the companies index accessible to internal staff', function (): void {
    $daf = User::factory()->create();
    $daf->assignRole('daf');
    $agent = User::factory()->create();
    $agent->assignRole('agency_agent');
    [$internalReferent] = portalUser('referent', ['reference' => 'ORG-2026-INT']);
    $internalReferent->assignRole('daf');

    $this->actingAs($daf)->get(route('companies.index'))->assertSuccessful();
    $this->actingAs($agent)->get(route('companies.index'))->assertSuccessful();
    $this->actingAs($internalReferent)->get(route('companies.index'))->assertSuccessful();
});

it('flags a portal-only referent in the shared props', function (): void {
    [$user] = portalUser('referent');
    $user->assignRole('passenger');
    [$internalReferent] = portalUser('referent', ['reference' => 'ORG-2026-INT']);
    $internalReferent->assignRole('daf');

    $this->actingAs($user)
        ->get(route('company-portal.index'))
        ->assertInertia(fn (Assert $page) => $page->where('portalOnly', true));

    $this->actingAs($internalReferent)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('portalOnly', false));
});
