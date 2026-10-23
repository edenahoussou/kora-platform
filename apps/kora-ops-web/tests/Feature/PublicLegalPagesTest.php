<?php

use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Passengers\Models\PassengerErasureRequest;
use App\Notifications\Passengers\PassengerDeletionRequestedNotification;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

it('serves the privacy policy publicly without authentication', function (): void {
    $this->get(route('privacy-policy'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('public/privacy-policy')
            ->where('supportEmail', config('legal.support_email'))
            ->has('company'));
});

it('serves the account deletion instructions publicly without authentication', function (): void {
    $this->get(route('account-deletion'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('public/account-deletion')
            ->where('supportEmail', config('legal.support_email'))
            ->has('company'));
});

it('registers a public deletion request and notifies the support address', function (): void {
    Notification::fake();

    $this->post(route('account-deletion.store'), [
        'phone' => '+229 53 23 40 60',
        'name' => 'Test Passager',
        'email' => 'passager@example.com',
    ])->assertRedirect(route('account-deletion'));

    $request = PassengerErasureRequest::query()->latest()->first();

    expect($request)->not->toBeNull()
        ->and($request->status)->toBe(PassengerErasureStatus::Requested)
        ->and($request->requested_by_user_id)->toBeNull()
        ->and($request->requester_email)->toBe('passager@example.com')
        ->and($request->scope['channel'])->toBe('web_form')
        ->and($request->requester_phone)->not->toBeNull();

    Notification::assertSentOnDemand(
        PassengerDeletionRequestedNotification::class,
        fn ($notification, $channels, $notifiable): bool => $notifiable->routes['mail'] === config('legal.support_email'),
    );
});

it('rejects a deletion request without a phone number', function (): void {
    $this->post(route('account-deletion.store'), [
        'name' => 'Test',
    ])->assertSessionHasErrors('phone');

    expect(PassengerErasureRequest::query()->where('status', PassengerErasureStatus::Requested)->count())->toBe(0);
});

it('rejects a deletion request when the honeypot is filled', function (): void {
    $this->post(route('account-deletion.store'), [
        'phone' => '+229 53 23 40 60',
        'website' => 'https://spam.example',
    ])->assertSessionHasErrors('website');
});
