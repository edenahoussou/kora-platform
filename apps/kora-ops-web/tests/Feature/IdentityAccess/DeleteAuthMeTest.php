<?php

use App\Models\User;
use App\Modules\Passengers\Data\PassengerErasureStatus;
use App\Modules\Passengers\Models\PassengerErasureRequest;
use App\Notifications\Passengers\PassengerDeletionRequestedNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

function deleteMeUser(array $overrides = []): User
{
    return User::factory()->create([
        'email' => 'erasable@example.com',
        'phone' => '+22996000001',
        'pin_hash' => Hash::make('1234'),
        ...$overrides,
    ]);
}

it('rejects unauthenticated deletion requests', function (): void {
    $this->deleteJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects an incorrect pin', function (): void {
    Sanctum::actingAs(deleteMeUser());

    $this->deleteJson('/api/v1/auth/me', ['pin' => '9999'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pin');

    expect(PassengerErasureRequest::query()->count())->toBe(0);
});

it('rejects accounts without a pin', function (): void {
    Sanctum::actingAs(deleteMeUser(['pin_hash' => null]));

    $this->deleteJson('/api/v1/auth/me', ['pin' => '1234'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pin');
});

it('creates an in_app erasure request and notifies support', function (): void {
    Notification::fake();
    $user = deleteMeUser();

    Sanctum::actingAs($user);

    $this->deleteJson('/api/v1/auth/me', [
        'pin' => '1234',
        'reason' => 'Je ne voyage plus.',
    ])->assertAccepted()
        ->assertJsonPath('data.status', 'requested');

    $request = PassengerErasureRequest::query()->sole();

    expect($request->status)->toBe(PassengerErasureStatus::Requested)
        ->and($request->requested_by_user_id)->toBe($user->id)
        ->and($request->scope['channel'])->toBe('in_app')
        ->and($request->scope['reason'])->toBe('Je ne voyage plus.')
        ->and($request->requester_email)->toBe('erasable@example.com');

    Notification::assertSentOnDemand(PassengerDeletionRequestedNotification::class);
});

it('is idempotent on the same phone hash', function (): void {
    Notification::fake();
    $user = deleteMeUser();

    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/auth/me', ['pin' => '1234'])->assertAccepted();
    $this->deleteJson('/api/v1/auth/me', ['pin' => '1234', 'reason' => 'Updated'])->assertAccepted();

    expect(PassengerErasureRequest::query()->count())->toBe(1)
        ->and(PassengerErasureRequest::query()->sole()->scope['reason'])->toBe('Updated');
});

it('accepts email-only accounts without a phone', function (): void {
    Notification::fake();
    $user = deleteMeUser(['phone' => null]);

    Sanctum::actingAs($user);
    $this->deleteJson('/api/v1/auth/me', ['pin' => '1234'])->assertAccepted();

    $request = PassengerErasureRequest::query()->sole();
    expect($request->phone_hash)->toBe(hash_hmac('sha256', 'email:erasable@example.com', (string) config('app.key')));
});
