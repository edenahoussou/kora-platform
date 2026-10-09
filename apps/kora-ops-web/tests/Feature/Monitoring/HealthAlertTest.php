<?php

use App\Models\User;
use App\Modules\Monitoring\Notifications\HealthCheckFailedNotification;
use App\Modules\Monitoring\Notifications\MonitoringNotifiable;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Facades\Health;

class FailingHealthProbe extends Check
{
    public function run(): Result
    {
        return Result::make()->failed('Panne simulée');
    }
}

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);

    Notification::fake();
});

it('sends a queued mail notification when a check fails', function (): void {
    Health::checks([FailingHealthProbe::new()]);

    $admin = User::factory()->create(['email' => 'ops-admin@kora.test']);
    $admin->assignRole('system_admin');

    $this->artisan('health:check')->assertSuccessful();

    Notification::assertSentTo(
        new MonitoringNotifiable,
        HealthCheckFailedNotification::class,
        fn (HealthCheckFailedNotification $notification) => $notification->tries === 3,
    );
});

it('throttles repeated alerts while the same failure persists', function (): void {
    Health::checks([FailingHealthProbe::new()]);

    $this->artisan('health:check')->assertSuccessful();
    $this->artisan('health:check')->assertSuccessful();

    Notification::assertSentToTimes(new MonitoringNotifiable, HealthCheckFailedNotification::class, 1);
});

it('routes health alert mails to active users with the monitoring permission', function (): void {
    $admin = User::factory()->create(['email' => 'ops-admin@kora.test']);
    $admin->assignRole('system_admin');

    $outsider = User::factory()->create(['email' => 'conductor@kora.test']);
    $outsider->assignRole('conductor');

    expect((new MonitoringNotifiable)->routeNotificationForMail())
        ->toContain('ops-admin@kora.test')
        ->not->toContain('conductor@kora.test');
});
