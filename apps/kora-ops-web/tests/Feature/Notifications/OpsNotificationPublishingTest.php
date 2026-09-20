<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Actions\RevokeOpsSessionAction;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\IdentityAccess\Models\TerminalSession;
use App\Modules\Notifications\Actions\PublishOpsNotificationAction;
use App\Modules\Notifications\Data\OpsNotificationChannel;
use App\Modules\Notifications\Data\OpsNotificationDeliveryStatus;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Mail\OpsAlertMail;
use App\Modules\Notifications\Models\OpsNotification;
use App\Modules\Notifications\Models\OpsNotificationDelivery;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use App\Modules\Notifications\Models\OpsNotificationPreference;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Mail::fake();
});

function pubUser(string $role, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($role);

    return $user;
}

/**
 * Deux agences, un directeur des opérations chacune, un super-administrateur,
 * un convoyeur de l'agence A.
 *
 * @return array{agencyA: Agency, agencyB: Agency, directorA: User, directorB: User, admin: User, conductor: User}
 */
function pubWorld(): array
{
    $agencyA = Agency::factory()->create();
    $agencyB = Agency::factory()->create();

    return [
        'agencyA' => $agencyA,
        'agencyB' => $agencyB,
        'directorA' => pubUser('operations_director', $agencyA),
        'directorB' => pubUser('operations_director', $agencyB),
        'admin' => pubUser('system_admin'),
        'conductor' => pubUser('conductor', $agencyA),
    ];
}

function pubDeparture(array $crew = [], string $status = 'scheduled'): Departure
{
    $line = Line::query()->create([
        'code' => 'NT-'.Str::upper(Str::random(4)), 'name' => 'Cotonou - Porto-Novo',
        'type' => 'nationale', 'countries_crossed' => ['BJ'],
        'distance_km' => 40, 'estimated_duration_minutes' => 60, 'status' => 'active',
    ]);
    $bus = Bus::query()->create(['registration_number' => 'NT-BUS-'.Str::random(4), 'brand' => 'KORA', 'model' => 'Coach', 'seat_count' => 1, 'status' => 'active']);

    return Departure::query()->create([
        'line_id' => $line->id, 'bus_id' => $bus->id,
        'departure_date' => '2026-10-01', 'departure_time' => '08:00',
        'estimated_arrival_at' => now()->addHour(), 'status' => $status,
        ...$crew,
    ]);
}

function pubDraft(OpsNotificationType $type, string $key, User $subject, array $data = []): OpsNotificationDraft
{
    return new OpsNotificationDraft($type, $key, 'Message '.$type->value, $data, collect([$subject]), $subject, null, null, now());
}

function pubPublish(OpsNotificationDraft $draft): OpsNotificationEvent
{
    $event = app(PublishOpsNotificationAction::class)->execute($draft);
    expect($event)->not->toBeNull();

    return $event;
}

/** @return list<string> */
function pubTypesFor(User $user): array
{
    return OpsNotification::query()->where('user_id', $user->id)->with('event')->get()
        ->map(fn (OpsNotification $notification): string => $notification->event->type->value)
        ->sort()->values()->all();
}

function pubSession(User $user, TerminalP501 $terminal): TerminalSession
{
    $token = $user->createToken('device:field', ['*'], now()->addDay());

    return TerminalSession::query()->create([
        'terminal_p501_id' => $terminal->id, 'user_id' => $user->id, 'personal_access_token_id' => $token->accessToken->id,
        'started_at' => now(), 'last_seen_at' => now(), 'expires_at' => now()->addHours(12), 'absolute_expires_at' => now()->addHours(24), 'renewal_count' => 0,
    ]);
}

it('publishes an event once however many times it is replayed (notifications, realtime, e-mail)', function (): void {
    $w = pubWorld();
    $draft = pubDraft(OpsNotificationType::ScanConflict, 'dedupe-1', $w['conductor']);

    $first = pubPublish($draft);
    $second = pubPublish($draft);

    $channelCount = fn (OpsNotificationChannel $channel): int => OpsNotificationDelivery::query()
        ->where('event_id', $first->id)->where('channel', $channel->value)->count();

    expect($second->id)->toBe($first->id)
        ->and(OpsNotificationEvent::query()->count())->toBe(1)
        // convoyeur + directeur de son agence + super-administrateur
        ->and(OpsNotification::query()->count())->toBe(3)
        ->and($channelCount(OpsNotificationChannel::InApp))->toBe(3)
        ->and($channelCount(OpsNotificationChannel::Realtime))->toBe(3)
        ->and($channelCount(OpsNotificationChannel::Mail))->toBe(2);

    Mail::assertSent(OpsAlertMail::class, 1);
});

it('mails the ops directors of the convoyeur agency (To) and the superadmins (Cc), and nobody from another agency', function (): void {
    $w = pubWorld();

    pubPublish(pubDraft(OpsNotificationType::PrintError, 'mail-routing', $w['conductor']));

    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo($w['directorA']->email)
        && $mail->hasCc($w['admin']->email)
        && ! $mail->hasTo($w['admin']->email)
        && ! $mail->hasTo($w['directorB']->email)
        && ! $mail->hasCc($w['directorB']->email));

    expect(pubTypesFor($w['directorA']))->toBe(['print_error'])
        ->and(pubTypesFor($w['admin']))->toBe(['print_error'])
        ->and(pubTypesFor($w['directorB']))->toBe([]);
});

it('addresses the superadmins directly when the agency has no ops director', function (): void {
    $w = pubWorld();
    $orphan = pubUser('conductor', Agency::factory()->create());

    pubPublish(pubDraft(OpsNotificationType::SyncFailed, 'no-director', $orphan));

    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo($w['admin']->email)
        && $mail->cc === []
        && ! $mail->hasTo($w['directorA']->email)
        && ! $mail->hasTo($w['directorB']->email));
});

it('sends nothing when there is neither a director nor a superadmin to warn', function (): void {
    $agency = Agency::factory()->create();
    $conductor = pubUser('conductor', $agency);

    $event = pubPublish(pubDraft(OpsNotificationType::PrintError, 'nobody', $conductor));

    Mail::assertNothingSent();
    expect(OpsNotificationDelivery::query()->where('event_id', $event->id)->where('channel', OpsNotificationChannel::Mail->value)->count())->toBe(0)
        ->and(pubTypesFor($conductor))->toBe(['print_error']);
});

it('keeps simple notifications free of e-mail and out of the superadmin inbox', function (): void {
    $w = pubWorld();

    pubPublish(pubDraft(OpsNotificationType::SyncCompleted, 'simple-sync', $w['conductor']));
    pubPublish(pubDraft(OpsNotificationType::DepartureAssigned, 'simple-assigned', $w['conductor']));

    Mail::assertNothingSent();
    expect(pubTypesFor($w['admin']))->toBe([])
        ->and(pubTypesFor($w['conductor']))->toBe(['departure_assigned', 'sync_completed'])
        // Les directeurs suivent les affectations de leur agence, pas les synchronisations.
        ->and(pubTypesFor($w['directorA']))->toBe(['departure_assigned']);
});

it('respects e-mail preferences, except for critical alerts', function (): void {
    $w = pubWorld();
    foreach (['print_error', 'scan_conflict'] as $type) {
        OpsNotificationPreference::query()->create(['user_id' => $w['directorA']->id, 'type' => $type, 'in_app' => true, 'mail' => false]);
    }

    pubPublish(pubDraft(OpsNotificationType::PrintError, 'pref-mail-1', $w['conductor']));
    pubPublish(pubDraft(OpsNotificationType::ScanConflict, 'pref-mail-2', $w['conductor']));

    // Erreur d'impression : le directeur a refusé l'e-mail, le super-administrateur devient destinataire.
    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->event->type === OpsNotificationType::PrintError
        && $mail->hasTo($w['admin']->email) && ! $mail->hasTo($w['directorA']->email));

    // Conflit de scan (critique) : la préférence est sans effet.
    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->event->type === OpsNotificationType::ScanConflict
        && $mail->hasTo($w['directorA']->email) && $mail->hasCc($w['admin']->email));
});

it('sends the alert e-mail only after the standard notifications are stored', function (): void {
    $w = pubWorld();

    $event = pubPublish(pubDraft(OpsNotificationType::PrintError, 'ordering', $w['conductor']));

    $lastStandard = OpsNotificationDelivery::query()->where('event_id', $event->id)
        ->whereIn('channel', [OpsNotificationChannel::InApp->value, OpsNotificationChannel::Realtime->value])->max('id');
    $firstMail = OpsNotificationDelivery::query()->where('event_id', $event->id)
        ->where('channel', OpsNotificationChannel::Mail->value)->min('id');

    expect($firstMail)->toBeGreaterThan($lastStandard);
});

it('keeps an audit trail per event, recipient and channel', function (): void {
    $w = pubWorld();
    $draft = pubDraft(OpsNotificationType::ScanConflict, 'audit-1', $w['conductor'], ['code' => 'boarding_conflict']);

    $event = pubPublish($draft);

    $mail = OpsNotificationDelivery::query()->where('event_id', $event->id)->where('channel', OpsNotificationChannel::Mail->value)->get()->keyBy('recipient_user_id');

    expect($event->dedupe_key)->toBe('audit-1')
        ->and($event->type)->toBe(OpsNotificationType::ScanConflict)
        ->and($event->subject_user_id)->toBe($w['conductor']->id)
        ->and($event->data)->toBe(['code' => 'boarding_conflict'])
        ->and($mail[$w['directorA']->id]->mail_role)->toBe('to')
        ->and($mail[$w['admin']->id]->mail_role)->toBe('cc')
        ->and($mail->pluck('status')->unique()->all())->toBe([OpsNotificationDeliveryStatus::Sent])
        ->and($mail->pluck('attempts')->unique()->all())->toBe([1])
        ->and($mail[$w['admin']->id]->delivered_at)->not->toBeNull()
        ->and(OpsNotificationDelivery::query()->where('event_id', $event->id)->where('channel', OpsNotificationChannel::InApp->value)->pluck('status')->unique()->all())
        ->toBe([OpsNotificationDeliveryStatus::Sent]);
});

it('retries a failed e-mail on replay without duplicating notifications or resending delivered mail', function (): void {
    $w = pubWorld();
    $draft = pubDraft(OpsNotificationType::PrintError, 'mail-retry', $w['conductor']);

    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp down'));

    // Le transport échoue : la publication ne lève pas, les notifications sont déjà en base.
    expect(app(PublishOpsNotificationAction::class)->execute($draft))->toBeNull();

    $event = OpsNotificationEvent::query()->where('dedupe_key', 'mail-retry')->firstOrFail();
    $mailDeliveries = fn () => OpsNotificationDelivery::query()->where('event_id', $event->id)->where('channel', OpsNotificationChannel::Mail->value)->get();

    expect(OpsNotification::query()->count())->toBe(3)
        ->and($mailDeliveries()->pluck('status')->unique()->all())->toBe([OpsNotificationDeliveryStatus::Failed])
        ->and($mailDeliveries()->first()->error)->toContain('smtp down')
        ->and($mailDeliveries()->first()->attempts)->toBe(1);

    Mail::fake();

    pubPublish($draft);

    expect(OpsNotification::query()->count())->toBe(3)
        ->and(OpsNotificationEvent::query()->count())->toBe(1)
        ->and($mailDeliveries()->pluck('status')->unique()->all())->toBe([OpsNotificationDeliveryStatus::Sent])
        ->and($mailDeliveries()->first()->attempts)->toBe(2);

    Mail::assertSent(OpsAlertMail::class, 1);

    // Une fois livré, un nouveau rejeu ne renvoie rien.
    pubPublish($draft);
    Mail::assertSent(OpsAlertMail::class, 1);
});

it('raises a critical alert with e-mail when a terminal session is revoked, once per session', function (): void {
    $w = pubWorld();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-PUB-01', 'assigned_user_id' => $w['conductor']->id]);
    $session = pubSession($w['conductor'], $terminal);

    app(RevokeOpsSessionAction::class)->execute($session, 'terminal_lost');
    app(RevokeOpsSessionAction::class)->execute($session->refresh(), 'terminal_lost');

    $event = OpsNotificationEvent::query()->where('type', OpsNotificationType::TerminalRevoked->value)->sole();

    expect($event->dedupe_key)->toBe('terminal_revoked:'.$session->uuid)
        ->and($event->data)->toBe(['reason' => 'terminal_lost', 'serial_number' => 'P501-PUB-01'])
        ->and(pubTypesFor($w['conductor']))->toBe(['terminal_revoked'])
        ->and(pubTypesFor($w['directorA']))->toBe(['terminal_revoked'])
        ->and(pubTypesFor($w['admin']))->toBe(['terminal_revoked'])
        ->and(pubTypesFor($w['directorB']))->toBe([]);

    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo($w['directorA']->email) && $mail->hasCc($w['admin']->email));
    Mail::assertSent(OpsAlertMail::class, 1);
});

it('does not raise a terminal alert for an ordinary logout', function (): void {
    $w = pubWorld();
    $terminal = TerminalP501::query()->create(['serial_number' => 'P501-PUB-02', 'assigned_user_id' => $w['conductor']->id]);

    app(RevokeOpsSessionAction::class)->execute(pubSession($w['conductor'], $terminal), 'logout');

    expect(OpsNotificationEvent::query()->where('type', OpsNotificationType::TerminalRevoked->value)->count())->toBe(0);
    Mail::assertNothingSent();
});

it('notifies crew members of their assignment and of every change to their departure, and nobody else', function (): void {
    $first = pubUser('conductor');
    $second = pubUser('conductor');
    $outsider = pubUser('conductor');

    $departure = pubDeparture(['conductor_id' => $first->id]);
    pubDeparture(['conductor_id' => $outsider->id]);

    expect(pubTypesFor($first))->toBe(['departure_assigned']);

    // Changement de convoyeur : le nouveau est affecté, l'ancien est informé qu'il est retiré.
    $departure->update(['conductor_id' => $second->id]);
    $departure->update(['departure_time' => '09:30']);
    $departure->update(['status' => 'cancelled']);

    expect(pubTypesFor($first))->toBe(['departure_assigned', 'departure_modified'])
        ->and(pubTypesFor($second))->toBe(['departure_assigned', 'departure_modified', 'departure_modified', 'departure_modified'])
        ->and(pubTypesFor($outsider))->toBe(['departure_assigned']);

    $changes = OpsNotificationEvent::query()->where('type', OpsNotificationType::DepartureModified->value)->orderBy('id')->get()
        ->map(fn (OpsNotificationEvent $event): array => $event->data['changes'])->all();

    expect($changes)->toBe([['crew_removed'], ['time'], ['cancelled']]);
});

it('does not notify when a departure is saved without any meaningful change', function (): void {
    $conductor = pubUser('conductor');
    $departure = pubDeparture(['conductor_id' => $conductor->id]);

    $departure->update(['delay_minutes' => 10, 'delay_reason' => 'Trafic']);
    $departure->update(['conductor_id' => $conductor->id]);

    expect(pubTypesFor($conductor))->toBe(['departure_assigned']);
});

it('turns a printing failure reported by the terminal into an alert, once per failure', function (): void {
    $w = pubWorld();
    $token = $w['conductor']->createToken('device:field', ['*'], now()->addDay())->plainTextToken;
    TerminalP501::query()->create(['serial_number' => 'P501-PUB-03', 'assigned_user_id' => $w['conductor']->id]);
    $own = pubDeparture(['conductor_id' => $w['conductor']->id]);
    $other = pubDeparture(['conductor_id' => pubUser('conductor')->id]);

    $post = fn (array $context) => $this->withToken($token)
        ->withHeaders(['X-Terminal-Serial' => 'P501-PUB-03'])
        ->postJson('/api/v1/ops-mobile/terminal/activity', ['serial_number' => 'P501-PUB-03', 'type' => 'print', 'context' => $context]);

    $failure = ['result' => 'copy_failed', 'error_kind' => 'outOfPaper', 'copy_number' => 2, 'job_uuid' => (string) Str::uuid(), 'recorded_at' => now()->toISOString(), 'departure_uuid' => $own->uuid];

    $post($failure)->assertSuccessful();
    $post($failure)->assertSuccessful(); // retry réseau du même échec

    $post(['result' => 'copy_printed', 'copy_number' => 1, 'departure_uuid' => $own->uuid])->assertSuccessful();
    $post(['result' => 'copy_failed', 'error_kind' => 'notConnected', 'copy_number' => 1, 'job_uuid' => (string) Str::uuid(), 'departure_uuid' => $other->uuid])->assertSuccessful();

    $events = OpsNotificationEvent::query()->where('type', OpsNotificationType::PrintError->value)->orderBy('id')->get();

    expect($events)->toHaveCount(2)
        ->and($events[0]->data['error_kind'])->toBe('out_of_paper')
        ->and($events[0]->departure_id)->toBe($own->id)
        // Départ d'un autre convoyeur : jamais rattaché à l'alerte.
        ->and($events[1]->data['error_kind'])->toBe('not_connected')
        ->and($events[1]->departure_id)->toBeNull();

    Mail::assertSent(OpsAlertMail::class, fn (OpsAlertMail $mail): bool => $mail->hasTo($w['directorA']->email) && $mail->hasCc($w['admin']->email));
    Mail::assertSent(OpsAlertMail::class, 2);
});
