<?php

use App\Models\User;
use App\Modules\IdentityAccess\Actions\RevokeWebSessionAction;
use App\Modules\IdentityAccess\Data\DevicePlatform;
use App\Modules\IdentityAccess\Data\UserDeviceStatus;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\IdentityAccess\Models\UserDevice;
use App\Notifications\IdentityAccess\SecurityAlertNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

function insertWebSession(User $user, ?string $id = null): string
{
    $id ??= bin2hex(random_bytes(20));

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => '127.0.0.1',
        'user_agent' => 'Mozilla/5.0 (Macintosh) Chrome/124 Safari/537.36',
        'payload' => base64_encode('x'),
        'last_activity' => time(),
    ]);

    return $id;
}

test('profile page exposes the unified hub props', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/profile')
            ->has('profile.uuid')
            ->has('profile.email')
            ->has('profile.permissions')
            ->has('security.canManageTwoFactor')
            ->has('security.passkeys')
            ->has('sessions')
            ->has('preferences.dispatchAlerts'));
});

test('legacy settings urls redirect to the profile hub', function (): void {
    $user = User::factory()->create();

    foreach (['/settings/security', '/settings/notifications', '/settings/appearance'] as $url) {
        $this->actingAs($user)
            ->get($url)
            ->assertRedirect('/settings/profile');
    }
});

test('phone change requires the current password', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'phone' => '+22997000001',
        ])
        ->assertSessionHasErrors('password');
});

test('phone change persists and sends a security alert', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'phone' => '+22997000001',
            'password' => 'password',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->toContain('97000001');

    Notification::assertSentTo(
        $user,
        SecurityAlertNotification::class,
        fn ($notification) => str_contains(
            $notification->toMail($user)->subject,
            'téléphone',
        ),
    );
});

test('profile update without phone change skips password confirmation', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nouveau Nom',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->not->toBeNull();
});

test('avatar upload stores a single media in the avatar collection', function (): void {
    Notification::fake();

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(1);
});

test('avatar upload replaces the previous avatar', function (): void {
    $user = User::factory()->create();

    foreach (['first.jpg', 'second.jpg'] as $file) {
        $this->actingAs(User::find($user->id))
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image($file, 400, 400),
            ]);
    }

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(1);
});

test('avatar deletion clears the collection', function (): void {
    $user = User::factory()->create();
    $user->addMedia(UploadedFile::fake()->image('a.jpg', 400, 400))->toMediaCollection('avatar');

    $this->actingAs($user)
        ->delete(route('profile.avatar.destroy'))
        ->assertSessionHasNoErrors();

    expect($user->fresh()->getMedia('avatar'))->toHaveCount(0);
});

test('avatar upload rejects non-image payloads', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');
});

test('preferences persist scoped to the authenticated user', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->put(route('profile.preferences.update'), [
            'push_notifications' => true,
            'dispatch_alerts' => false,
            'locale' => 'en',
        ])
        ->assertSessionHasNoErrors();

    $preference = $user->preference()->first();

    expect($preference)->not->toBeNull()
        ->and($preference->push_notifications)->toBeTrue()
        ->and($preference->dispatch_alerts)->toBeFalse()
        ->and($preference->locale)->toBe('en')
        ->and($other->preference()->first())->toBeNull();
});

test('web session revocation requires the current password', function (): void {
    $user = User::factory()->create();
    $sessionId = insertWebSession($user);

    $this->actingAs($user)
        ->delete(route('profile.sessions.destroy', [
            'session' => hash('sha256', $sessionId),
        ]), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeTrue();
});

test('web session revocation deletes the targeted session', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $sessionId = insertWebSession($user);

    $this->actingAs($user)
        ->delete(route('profile.sessions.destroy', [
            'session' => hash('sha256', $sessionId),
        ]), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', $sessionId)->exists())->toBeFalse();

    Notification::assertSentTo($user, SecurityAlertNotification::class);
});

test('a user cannot revoke another user session', function (): void {
    $user = User::factory()->create();
    $otherSessionId = insertWebSession(User::factory()->create());

    $this->actingAs($user)
        ->delete(route('profile.sessions.destroy', [
            'session' => hash('sha256', $otherSessionId),
        ]), ['password' => 'password'])
        ->assertNotFound();

    expect(DB::table('sessions')->where('id', $otherSessionId)->exists())->toBeTrue();
});

test('the current session can never be revoked through the action', function (): void {
    $user = User::factory()->create();

    app(RevokeWebSessionAction::class)
        ->execute($user, 'current-id', 'current-id');
})->throws(NotFoundHttpException::class);

test('revoke others clears every other web session of the user only', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $first = insertWebSession($user);
    $second = insertWebSession($user);
    $foreign = insertWebSession($other);

    $this->actingAs($user)
        ->delete(route('profile.sessions.destroy-others'), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->where('id', $foreign)->exists())->toBeTrue();
});

test('trusted device revocation is scoped to the owner', function (): void {
    $user = User::factory()->create();
    $foreign = UserDevice::create([
        'user_id' => User::factory()->create()->id,
        'name' => 'iPhone',
        'platform' => DevicePlatform::Ios,
        'device_fingerprint_hash' => hash('sha256', 'fp'),
        'status' => UserDeviceStatus::Active,
    ]);

    $this->actingAs($user)
        ->delete(route('profile.devices.destroy', [
            'device' => $foreign->uuid,
        ]), ['password' => 'password'])
        ->assertNotFound();
});

test('trusted device revocation marks the device revoked', function (): void {
    $user = User::factory()->create();
    $device = UserDevice::create([
        'user_id' => $user->id,
        'name' => 'iPhone',
        'platform' => DevicePlatform::Ios,
        'device_fingerprint_hash' => hash('sha256', 'fp'),
        'status' => UserDeviceStatus::Active,
    ]);

    $this->actingAs($user)
        ->delete(route('profile.devices.destroy', [
            'device' => $device->uuid,
        ]), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($device->refresh()->status)->toBe(UserDeviceStatus::Revoked);
});

test('terminal session revocation marks the session revoked', function (): void {
    $user = User::factory()->create();
    $terminal = TerminalP501::factory()->create();
    $session = TerminalSession::create([
        'terminal_p501_id' => $terminal->id,
        'user_id' => $user->id,
        'started_at' => now(),
    ]);

    $this->actingAs($user)
        ->delete(route('profile.terminals.destroy', [
            'terminal' => $session->uuid,
        ]), ['password' => 'password'])
        ->assertSessionHasNoErrors();

    expect($session->refresh()->revoked_at)->not->toBeNull();
});
