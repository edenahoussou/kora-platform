<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Features;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_security_url_redirects_to_the_profile_hub()
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('security.edit'))
            ->assertRedirect(route('profile.edit').'?tab=security');
    }

    public function test_two_factor_endpoints_still_require_password_confirmation()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('two-factor.qr-code'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($user)
            ->post(route('two-factor.enable'))
            ->assertRedirect(route('password.confirm'));
    }

    public function test_profile_hub_exposes_security_props()
    {
        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]);
        Features::passkeys([
            'confirmPassword' => true,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('settings/profile')
                ->where('security.canManagePasskeys', true)
                ->where('security.passkeys', [])
                ->where('security.canManageTwoFactor', true)
                ->where('security.twoFactorEnabled', false),
            );
    }

    public function test_password_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'Kora@Express2026',
                'password_confirmation' => 'Kora@Express2026',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue(Hash::check('Kora@Express2026', $user->refresh()->password));
    }

    public function test_correct_password_must_be_provided_to_update_password()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'Kora@Express2026',
                'password_confirmation' => 'Kora@Express2026',
            ]);

        $response
            ->assertSessionHasErrors('current_password')
            ->assertRedirect(route('profile.edit'));
    }
}
