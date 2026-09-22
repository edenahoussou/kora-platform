<?php

use Inertia\Testing\AssertableInertia as Assert;

it('serves the privacy policy publicly without authentication', function (): void {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('public/privacy-policy')
            ->where('supportEmail', config('legal.support_email'))
            ->where('lastUpdated', config('legal.last_updated_at')));
});

it('serves the account deletion instructions publicly without authentication', function (): void {
    $this->get(route('account-deletion'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('public/account-deletion')
            ->where('supportEmail', config('legal.support_email'))
            ->where('lastUpdated', config('legal.last_updated_at')));
});
