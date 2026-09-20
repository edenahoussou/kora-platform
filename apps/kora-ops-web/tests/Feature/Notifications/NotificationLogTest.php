<?php

use App\Models\User;
use App\Modules\IdentityAccess\Models\Agency;
use App\Modules\Notifications\Actions\PublishOpsNotificationAction;
use App\Modules\Notifications\Data\OpsNotificationDraft;
use App\Modules\Notifications\Data\OpsNotificationType;
use App\Modules\Notifications\Mail\OpsAlertMail;
use App\Modules\Notifications\Models\NotificationLog;
use App\Modules\Notifications\Models\OpsNotificationEvent;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
    Mail::fake();
});

function logUser(string $role, ?Agency $agency = null): User
{
    $user = User::factory()->create(['agency_id' => $agency?->id]);
    $user->assignRole($role);

    return $user;
}

/** @return array{conductor: User, director: User, admin: User} */
function logWorld(): array
{
    $agency = Agency::factory()->create();

    return [
        'conductor' => logUser('conductor', $agency),
        'director' => logUser('operations_director', $agency),
        'admin' => logUser('system_admin'),
    ];
}

function logDraft(User $subject, string $key = 'log-1'): OpsNotificationDraft
{
    return new OpsNotificationDraft(OpsNotificationType::PrintError, $key, 'Message', ['error_kind' => 'out_of_paper'], collect([$subject]), $subject, null, null, now());
}

/**
 * Remplace le mailer par un transport qui échoue (« smtp down »). Ne touche pas au fake de la façade :
 * `Mail::shouldReceive()` puis `Mail::fake()` plante (TypeError), et un MailFake construit à la main
 * n'est pas fiable. Après la panne, `Mail::fake()` repart d'un MailManager réel.
 */
function logFailingMail(): void
{
    app()->forgetInstance('mail.manager');
    Mail::clearResolvedInstance('mail.manager');

    Mail::extend('failing', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new RuntimeException('smtp down');
        }

        public function __toString(): string
        {
            return 'failing://';
        }
    });

    config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);
}

it('writes one notification_logs row per mail recipient, with type, role and attempts but no message content', function (): void {
    $w = logWorld();

    $event = app(PublishOpsNotificationAction::class)->execute(logDraft($w['conductor']));

    $logs = NotificationLog::query()->orderBy('id')->get()->keyBy('recipient');

    expect($logs)->toHaveCount(2)
        ->and($logs[$w['director']->email]->channel)->toBe('mail')
        ->and($logs[$w['director']->email]->template)->toBe('ops_alert')
        ->and($logs[$w['director']->email]->status)->toBe('sent')
        ->and($logs[$w['director']->email]->sent_at)->not->toBeNull()
        ->and($logs[$w['director']->email]->reference_type)->toBe($event->getMorphClass())
        ->and($logs[$w['director']->email]->reference_id)->toBe($event->id)
        ->and($logs[$w['director']->email]->reference)->toBeInstanceOf(OpsNotificationEvent::class)
        ->and($logs[$w['director']->email]->payload)->toBe(['type' => 'print_error', 'severity' => 'warning', 'mail_role' => 'to', 'attempts' => 1])
        ->and($logs[$w['admin']->email]->payload['mail_role'])->toBe('cc');

    // Ni le message ni le contenu de `data` ne sont journalisés.
    expect(json_encode($logs->pluck('payload')))->not->toContain('Message')->not->toContain('out_of_paper');
});

it('does not log in-app or real-time deliveries, nor events without an e-mail', function (): void {
    $w = logWorld();

    $draft = new OpsNotificationDraft(OpsNotificationType::SyncCompleted, 'log-info', 'Sync', [], collect([$w['conductor']]), $w['conductor'], null, null, now());
    app(PublishOpsNotificationAction::class)->execute($draft);

    expect(NotificationLog::query()->count())->toBe(0);
});

it('updates the same rows on a retry instead of duplicating them', function (): void {
    $w = logWorld();
    $draft = logDraft($w['conductor'], 'log-retry');

    logFailingMail();
    expect(app(PublishOpsNotificationAction::class)->execute($draft))->toBeNull();

    $failed = NotificationLog::query()->get();
    expect($failed)->toHaveCount(2)
        ->and($failed->pluck('status')->unique()->all())->toBe(['failed'])
        ->and($failed->pluck('sent_at')->filter()->all())->toBe([])
        ->and($failed->first()->payload['error'])->toContain('smtp down')
        ->and($failed->first()->payload['attempts'])->toBe(1);

    Mail::fake();
    app(PublishOpsNotificationAction::class)->execute($draft);

    $sent = NotificationLog::query()->get();
    expect($sent)->toHaveCount(2)
        ->and($sent->pluck('status')->unique()->all())->toBe(['sent'])
        ->and($sent->pluck('id')->sort()->values()->all())->toBe($failed->pluck('id')->sort()->values()->all())
        ->and($sent->first()->payload['attempts'])->toBe(2)
        ->and(array_key_exists('error', $sent->first()->payload))->toBeFalse();

    Mail::assertSent(OpsAlertMail::class, 1);
});

it('never blocks or repeats an e-mail because the audit log is unavailable', function (): void {
    $w = logWorld();
    Schema::drop('notification_logs');

    $event = app(PublishOpsNotificationAction::class)->execute(logDraft($w['conductor'], 'log-down'));

    expect($event)->not->toBeNull();
    Mail::assertSent(OpsAlertMail::class, 1);

    // Un rejeu ne renvoie rien : les livraisons (source de vérité des relances) sont `sent`.
    app(PublishOpsNotificationAction::class)->execute(logDraft($w['conductor'], 'log-down'));
    Mail::assertSent(OpsAlertMail::class, 1);
});
