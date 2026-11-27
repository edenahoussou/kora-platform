<?php

use App\Models\User;
use App\Modules\Settings\Settings\WalletSettings;
use App\Modules\Wallet\Models\WalletLoyaltyRuleVersion;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Database\Seeders\WalletLoyaltyRuleVersionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

function walletSettingsAdmin(): User
{
    $user = User::factory()->create();
    $user->assignRole('system_admin');

    return $user;
}

it('renders the wallet snapshot for the operations settings viewers', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('settings.operations.view');

    $this->actingAs($viewer)
        ->get(route('administration.settings.wallet'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/settings')
            ->where('group', 'wallet')
            ->where('permissions.canView', true)
            ->where('permissions.canManage', false)
            ->where('snapshot.values.walletCashEnabled', false)
            ->where('snapshot.values.walletPointsEnabled', true)
            ->where('snapshot.values.loyaltyEarnRateBasisPoints', 200),
        );
});

it('persists and audits wallet settings mutations', function (): void {
    $admin = walletSettingsAdmin();

    $this->actingAs($admin)
        ->patch(route('administration.settings.wallet.update'), [
            'values' => [
                'walletCashEnabled' => true,
                'topupAmountPresets' => [1000, 5000],
            ],
        ])
        ->assertRedirect(route('administration.settings.wallet'))
        ->assertSessionHasNoErrors();

    $settings = app(WalletSettings::class);

    expect($settings->walletCashEnabled)->toBeTrue()
        ->and($settings->topupAmountPresets)->toBe([1000, 5000]);

    $audit = Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail();
    expect($audit->properties['group'])->toBe('wallet')
        ->and($audit->properties['fields'])->toContain('walletCashEnabled');
});

it('keeps loyalty rule values out of direct settings updates', function (): void {
    $admin = walletSettingsAdmin();
    $settings = app(WalletSettings::class);

    $this->actingAs($admin)
        ->patch(route('administration.settings.wallet.update'), [
            'values' => ['bonusWelcomePoints' => $settings->bonusWelcomePoints + 100],
        ])
        ->assertSessionHasErrors(['values.bonusWelcomePoints' => 'Le champ « Bonus bienvenue » se modifie dans Wallet → Règles de fidélité.']);

    expect(app(WalletSettings::class)->bonusWelcomePoints)->toBe($settings->bonusWelcomePoints);
});

it('rejects incoherent wallet top-up bounds', function (): void {
    $admin = walletSettingsAdmin();

    $this->actingAs($admin)
        ->patch(route('administration.settings.wallet.update'), [
            'values' => [
                'topupMinAmount' => 10000,
                'topupMaxAmount' => 5000,
            ],
        ])
        ->assertSessionHasErrors(['topupMinAmount']);
});

it('refuses wallet settings mutations without the operations manage permission', function (): void {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('settings.operations.view');

    $this->actingAs($viewer)
        ->patch(route('administration.settings.wallet.update'), [
            'values' => ['walletCashEnabled' => true],
        ])
        ->assertForbidden();
});

it('opens the cash balance independently of historical loyalty fields', function (): void {
    $this->seed(WalletLoyaltyRuleVersionSeeder::class);
    $version = WalletLoyaltyRuleVersion::query()->sole();
    $settings = app(WalletSettings::class);
    $settings->pointsExpiryReminderDays = [];
    $settings->save();

    $this->actingAs(walletSettingsAdmin())
        ->patch(route('administration.settings.wallet.update'), ['values' => ['walletCashEnabled' => true]])
        ->assertSessionHasNoErrors();

    $this->get(route('administration.settings.wallet'))
        ->assertInertia(fn (Assert $page) => $page->where('snapshot.values.walletCashEnabled', true));

    expect($version->fresh()->getAttributes())->toBe($version->getAttributes())
        ->and(Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail()->properties['fields'])
        ->toBe(['walletCashEnabled']);
});
