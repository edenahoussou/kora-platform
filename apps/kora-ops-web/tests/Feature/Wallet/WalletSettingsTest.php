<?php

use App\Models\User;
use App\Modules\Settings\Settings\WalletSettings;
use Database\Seeders\KoraBusRolePermissionSeeder;
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
                'bonusWelcomePoints' => 750,
                'topupAmountPresets' => [1000, 5000],
            ],
        ])
        ->assertRedirect(route('administration.settings.wallet'));

    $settings = app(WalletSettings::class);

    expect($settings->walletCashEnabled)->toBeTrue()
        ->and($settings->bonusWelcomePoints)->toBe(750)
        ->and($settings->topupAmountPresets)->toBe([1000, 5000]);

    $audit = Activity::query()->where('log_name', 'settings')->latest('id')->firstOrFail();
    expect($audit->properties['group'])->toBe('wallet')
        ->and($audit->properties['fields'])->toContain('walletCashEnabled');
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
