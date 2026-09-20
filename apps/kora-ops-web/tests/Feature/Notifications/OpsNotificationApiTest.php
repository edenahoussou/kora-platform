<?php

use App\Models\User;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\Notifications\Actions\PublishOpsNotificationAction;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Events\OpsNotificationPublished;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Models\OpsNotificationPreference;
use App\Modules\Notifications\Services\OpsNotifier;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Mail::fake();
    $this->autoOpenOpsSession = false;
});

function notifUser(string $role = 'conductor'): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/** @param  Collection<int, User>|array<int, User>  $recipients */
function notifDraft(OpsNotificationType $type, string $key, Collection|array $recipients, ?User $subject = null, array $data = [], ?Departure $departure = null, ?TerminalP501 $terminal = null): OpsNotificationDraft
{
    return new OpsNotificationDraft($type, $key, 'Message '.$type->value, $data, collect($recipients), $subject, $departure, $terminal, now());
}

function notifPublish(OpsNotificationDraft $draft): OpsNotificationEvent
{
    $event = app(PublishOpsNotificationAction::class)->execute($draft);
    expect($event)->not->toBeNull();

    return $event;
}

function notifOf(OpsNotificationEvent $event, User $user): ?OpsNotification
{
    return OpsNotification::query()->where('event_id', $event->id)->where('user_id', $user->id)->first();
}

/** Recherche récursive d'une clé interdite dans une réponse JSON. */
function notifFindKeys(mixed $node, array $forbidden, string $path = ''): array
{
    $found = [];

    if (is_array($node)) {
        foreach ($node as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), $forbidden, true)) {
                $found[] = $path.'.'.$key;
            }
            $found = [...$found, ...notifFindKeys($value, $forbidden, $path.'.'.$key)];
        }
    }

    return $found;
}

it('lists only the caller notifications, newest first, with uuids and codes but no numeric id', function (): void {
    $a = notifUser();
    $b = notifUser();

    $assigned = notifPublish(notifDraft(OpsNotificationType::DepartureAssigned, 'k-assigned', [$a], $a));
    notifPublish(notifDraft(OpsNotificationType::ScanConflict, 'k-conflict', [$a], $a));
    notifPublish(notifDraft(OpsNotificationType::SyncCompleted, 'k-sync-b', [$b], $b));

    notifOf($assigned, $a)->forceFill(['created_at' => now()->subMinutes(10)])->save();

    $response = $this->actingAs($a)->getJson('/api/v1/notifications')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('data.0.type'))->toBe('scan_conflict')
        ->and($response->json('data.0.severity'))->toBe('critical')
        ->and($response->json('data.0.action'))->toBe('review_scan_conflict')
        ->and($response->json('data.1.type'))->toBe('departure_assigned')
        ->and($response->json('meta.unread_count'))->toBe(2)
        ->and($response->json('meta.critical_unread_count'))->toBe(1)
        ->and(collect($response->json('data'))->pluck('type')->all())->not->toContain('sync_completed')
        ->and(Str::isUuid($response->json('data.0.uuid')))->toBeTrue()
        ->and(Str::isUuid($response->json('data.0.event_uuid')))->toBeTrue()
        ->and(notifFindKeys($response->json(), ['id', 'user_id', 'event_id', 'departure_id', 'terminal_p501_id']))->toBe([]);
});

it('tracks read and unread state per notification, idempotently', function (): void {
    $a = notifUser();
    $event = notifPublish(notifDraft(OpsNotificationType::DepartureAssigned, 'k1', [$a], $a));
    notifPublish(notifDraft(OpsNotificationType::SyncCompleted, 'k2', [$a], $a));
    $uuid = notifOf($event, $a)->uuid;

    $first = $this->actingAs($a)->postJson("/api/v1/notifications/{$uuid}/read")->assertOk();
    expect($first->json('data.is_read'))->toBeTrue();
    $readAt = $first->json('data.read_at');

    $this->travel(5)->minutes();

    $second = $this->actingAs($a)->postJson("/api/v1/notifications/{$uuid}/read")->assertOk();
    expect($second->json('data.read_at'))->toBe($readAt);

    $unread = $this->actingAs($a)->getJson('/api/v1/notifications?unread=1')->assertOk();
    expect($unread->json('data'))->toHaveCount(1)
        ->and($unread->json('data.0.type'))->toBe('sync_completed');

    $this->actingAs($a)->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.unread', 1)
        ->assertJsonPath('data.critical_unread', 0);
});

it('marks everything read up to a cut-off and leaves later notifications unread', function (): void {
    $a = notifUser();
    $old = notifPublish(notifDraft(OpsNotificationType::DepartureAssigned, 'old', [$a], $a));
    notifOf($old, $a)->forceFill(['created_at' => now()->subHour()])->save();
    notifPublish(notifDraft(OpsNotificationType::SyncCompleted, 'recent', [$a], $a));

    $this->actingAs($a)->postJson('/api/v1/notifications/read-all', ['before' => now()->subMinutes(30)->toISOString()])
        ->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('meta.unread_count', 1);

    $this->actingAs($a)->postJson('/api/v1/notifications/read-all')
        ->assertOk()
        ->assertJsonPath('data.updated', 1)
        ->assertJsonPath('meta.unread_count', 0);
});

it('never lets a user read or mark the notification of someone else', function (): void {
    $a = notifUser();
    $b = notifUser();
    $event = notifPublish(notifDraft(OpsNotificationType::DepartureAssigned, 'k1', [$a], $a));
    $notification = notifOf($event, $a);

    $this->actingAs($b)->postJson("/api/v1/notifications/{$notification->uuid}/read")->assertNotFound();
    expect($notification->refresh()->read_at)->toBeNull();

    $this->actingAs($b)->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated', 0);
    expect($notification->refresh()->read_at)->toBeNull();

    $this->actingAs($b)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(0, 'data');
});

it('filters by type, severity and since (inclusive, so a resume after an outage misses nothing)', function (): void {
    $a = notifUser();
    $conflict = notifPublish(notifDraft(OpsNotificationType::ScanConflict, 'c', [$a], $a));
    $sync = notifPublish(notifDraft(OpsNotificationType::SyncCompleted, 's', [$a], $a));
    $moment = now()->subMinutes(20)->startOfSecond();
    notifOf($sync, $a)->forceFill(['created_at' => $moment])->save();

    $byType = $this->actingAs($a)->getJson('/api/v1/notifications?type=sync_completed')->assertOk();
    expect($byType->json('data'))->toHaveCount(1);

    $bySeverity = $this->actingAs($a)->getJson('/api/v1/notifications?severity=critical')->assertOk();
    expect($bySeverity->json('data'))->toHaveCount(1)->and($bySeverity->json('data.0.type'))->toBe('scan_conflict');

    $since = $this->actingAs($a)->getJson('/api/v1/notifications?since='.urlencode($moment->toISOString()))->assertOk();
    expect($since->json('data'))->toHaveCount(2);

    $later = $this->actingAs($a)->getJson('/api/v1/notifications?since='.urlencode($moment->copy()->addSecond()->toISOString()))->assertOk();
    expect($later->json('data'))->toHaveCount(1)->and($later->json('data.0.type'))->toBe('scan_conflict');

    $this->actingAs($a)->getJson('/api/v1/notifications?type=unknown')->assertUnprocessable();
    expect($conflict->id)->not->toBeNull();
});

it('requires authentication, an active account and the notifications.view permission', function (): void {
    $this->getJson('/api/v1/notifications')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_invalid');

    $passenger = notifUser('passenger');
    $this->actingAs($passenger)->getJson('/api/v1/notifications')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'permission_denied');

    $suspended = notifUser();
    $suspended->forceFill(['status' => UserStatus::Suspended])->save();
    $this->actingAs($suspended)->getJson('/api/v1/notifications')
        ->assertForbidden()
        ->assertJsonPath('error.code', 'account_suspended');
});

it('serves OPS Web users the same contract as OPS Mobile, without bypassing permissions', function (): void {
    $director = notifUser('operations_director');
    $conductor = notifUser('conductor');

    notifPublish(notifDraft(OpsNotificationType::DepartureAssigned, 'web-1', [$director, $conductor], $conductor));

    $web = $this->actingAs($director)->getJson('/api/v1/notifications')->assertOk();
    $mobileToken = $conductor->createToken('device:field', ['*'], now()->addDay())->plainTextToken;
    Auth::forgetGuards();
    $mobile = $this->withToken($mobileToken)->getJson('/api/v1/notifications')->assertOk();

    expect(array_keys($web->json('data.0')))->toBe(array_keys($mobile->json('data.0')))
        ->and(array_keys($web->json('meta')))->toBe(array_keys($mobile->json('meta')));
});

it('keeps notifications reachable from an OPS session without demanding the terminal serial', function (): void {
    $user = notifUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-NOTIF-01', 'assigned_user_id' => $user->id]);
    $token = $user->createToken('device:field', ['*'], now()->addDay());
    TerminalSession::query()->create([
        'terminal_p501_id' => $terminal->id, 'user_id' => $user->id, 'personal_access_token_id' => $token->accessToken->id,
        'started_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addHours(12), 'absolute_expires_at' => now()->addHours(24), 'renewal_count' => 0,
    ]);
    notifPublish(notifDraft(OpsNotificationType::DepartureAssigned, 'k1', [$user], $user));

    // Pas d'en-tête X-Terminal-Serial : la lecture des notifications ne l'exige pas.
    $this->withToken($token->plainTextToken)->getJson('/api/v1/notifications')->assertOk()->assertJsonCount(1, 'data');

    // Session expirée : mêmes codes que le reste de l'API OPS.
    $this->travel(13)->hours();
    Auth::forgetGuards();
    $this->withToken($token->plainTextToken)->getJson('/api/v1/notifications')
        ->assertUnauthorized()
        ->assertJsonPath('error.code', 'session_expired')
        ->assertJsonPath('error.action', 'reauthenticate');
});

it('lets the convoyeur recover a critical alert raised while offline, after reconnecting with a new session', function (): void {
    $user = notifUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-NOTIF-02', 'assigned_user_id' => $user->id]);
    $first = $user->createToken('device:field', ['*'], now()->addDay());
    $session = TerminalSession::query()->create([
        'terminal_p501_id' => $terminal->id, 'user_id' => $user->id, 'personal_access_token_id' => $first->accessToken->id,
        'started_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addHours(12), 'absolute_expires_at' => now()->addHours(24), 'renewal_count' => 0,
    ]);

    // Le terminal est déclaré perdu pendant que le mobile est hors ligne.
    app(RevokeOpsSessionAction::class)->execute($session, 'terminal_lost');

    Auth::forgetGuards();
    $this->withToken($first->plainTextToken)->getJson('/api/v1/notifications')->assertUnauthorized();

    // Reconnexion (nouveau jeton, nouvelle session) : l'alerte est toujours là, non lue.
    $second = $user->createToken('device:field', ['*'], now()->addDay());
    Auth::forgetGuards();
    $response = $this->withToken($second->plainTextToken)->getJson('/api/v1/notifications?unread=1')->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.type'))->toBe('terminal_revoked')
        ->and($response->json('data.0.severity'))->toBe('critical')
        ->and($response->json('data.0.action'))->toBe('reauthenticate')
        ->and($response->json('data.0.data.reason'))->toBe('terminal_lost')
        ->and($response->json('data.0.terminal.serial_number'))->toBe('P501-NOTIF-02')
        ->and($response->json('meta.critical_unread_count'))->toBe(1);
});

it('never exposes financial data or unlisted context in a notification', function (): void {
    $user = notifUser();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-NOTIF-03', 'assigned_user_id' => $user->id]);

    app(OpsNotifier::class)->printError($terminal, $user, [
        'result' => 'copy_failed',
        'error_kind' => 'outOfPaper',
        'copy_number' => 2,
        'job_uuid' => (string) Str::uuid(),
        'amount' => 583917,
        'ticket_price' => 748213,
        'payment' => ['provider' => 'cash'],
        'passenger_name' => 'Fatou Diop',
    ]);

    $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();
    $json = json_encode($response->json());

    expect($response->json('data.0.type'))->toBe('print_error')
        ->and($response->json('data.0.data.error_kind'))->toBe('out_of_paper')
        ->and(array_keys($response->json('data.0.data')))->toBe(['result', 'error_kind', 'copy_number', 'copies_confirmed', 'mode'])
        ->and($json)->not->toContain('583917')->not->toContain('748213')->not->toContain('cash')->not->toContain('Fatou')
        // La pagination (`meta.total`) n'est pas une donnée financière : seul `data` est scanné.
        ->and(notifFindKeys($response->json('data'), ['amount', 'price', 'ticket_price', 'payment', 'total', 'currency']))->toBe([]);
});

it('exposes effective preferences and refuses to disable critical alerts, saving nothing', function (): void {
    $user = notifUser();

    $preferences = $this->actingAs($user)->getJson('/api/v1/notifications/preferences')->assertOk();
    $byType = collect($preferences->json('data'))->keyBy('type');

    expect($byType)->toHaveCount(count(OpsNotificationType::cases()))
        ->and($byType['scan_conflict']['disableable'])->toBeFalse()
        ->and($byType['terminal_revoked']['disableable'])->toBeFalse()
        ->and($byType['sync_completed']['disableable'])->toBeTrue()
        ->and($byType['print_error']['mail_supported'])->toBeTrue()
        ->and($byType['sync_completed']['mail_supported'])->toBeFalse();

    $this->actingAs($user)->putJson('/api/v1/notifications/preferences', ['preferences' => [
        ['type' => 'sync_completed', 'in_app' => false],
        ['type' => 'scan_conflict', 'in_app' => false],
    ]])
        ->assertUnprocessable()
        ->assertJsonPath('error.code', 'notification_not_disableable')
        ->assertJsonPath('error.types', ['scan_conflict']);

    expect(OpsNotificationPreference::query()->count())->toBe(0);

    $this->actingAs($user)->putJson('/api/v1/notifications/preferences', ['preferences' => [
        ['type' => 'sync_completed', 'in_app' => false],
    ]])
        ->assertOk()
        ->assertJsonPath('data.'.array_search('sync_completed', array_column($preferences->json('data'), 'type'), true).'.in_app', false);
});

it('honours in-app preferences for optional types but always delivers critical alerts', function (): void {
    $user = notifUser();

    $this->actingAs($user)->putJson('/api/v1/notifications/preferences', ['preferences' => [
        ['type' => 'sync_completed', 'in_app' => false],
    ]])->assertOk();

    // Une ligne de préférence hostile posée directement en base n'éteint pas une alerte critique.
    OpsNotificationPreference::query()->create(['user_id' => $user->id, 'type' => 'scan_conflict', 'in_app' => false, 'mail' => false]);

    $skipped = notifPublish(notifDraft(OpsNotificationType::SyncCompleted, 'pref-sync', [$user], $user));
    $critical = notifPublish(notifDraft(OpsNotificationType::ScanConflict, 'pref-conflict', [$user], $user));

    expect(notifOf($skipped, $user))->toBeNull()
        ->and(OpsNotificationDelivery::query()->where('event_id', $skipped->id)->where('channel', OpsNotificationChannel::InApp->value)->value('status'))
        ->toBe(OpsNotificationDeliveryStatus::Skipped)
        ->and(notifOf($critical, $user))->not->toBeNull();
});

it('broadcasts the API payload on the private channel of each recipient', function (): void {
    Event::fake([OpsNotificationPublished::class]);
    $a = notifUser();

    $event = notifPublish(notifDraft(OpsNotificationType::ScanConflict, 'rt-1', [$a], $a));

    Event::assertDispatched(OpsNotificationPublished::class, fn (OpsNotificationPublished $broadcast): bool => $broadcast->userUuid === $a->uuid
        && $broadcast->notification['type'] === 'scan_conflict'
        && $broadcast->notification['event_uuid'] === $event->uuid
        && $broadcast->broadcastAs() === 'ops.notification.published'
        && $broadcast->broadcastOn()[0]->name === 'private-ops-notifications.'.$a->uuid);

    expect(OpsNotificationDelivery::query()->where('event_id', $event->id)->where('channel', OpsNotificationChannel::Realtime->value)->value('status'))
        ->toBe(OpsNotificationDeliveryStatus::Sent);
});

it('keeps the notification retrievable when the real-time channel is down', function (): void {
    Event::listen(OpsNotificationPublished::class, function (): void {
        throw new RuntimeException('socket down');
    });
    $a = notifUser();

    $event = notifPublish(notifDraft(OpsNotificationType::ScanConflict, 'rt-down', [$a], $a));

    expect(notifOf($event, $a))->not->toBeNull();

    $realtime = OpsNotificationDelivery::query()->where('event_id', $event->id)->where('channel', OpsNotificationChannel::Realtime->value)->first();
    expect($realtime->status)->toBe(OpsNotificationDeliveryStatus::Failed)
        ->and($realtime->error)->toContain('socket down');

    $this->actingAs($a)->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('data.0.type', 'scan_conflict');
});
