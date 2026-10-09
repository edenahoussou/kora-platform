<?php

use App\Models\User;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Facades\Health;

class AlwaysFailingCheck extends Check
{
    public function run(): Result
    {
        return Result::make()->failed('Échec simulé pour le test');
    }
}

beforeEach(function (): void {
    $this->seed(KoraBusRolePermissionSeeder::class);
});

it('surfaces a failing check as failed on the monitoring page', function (): void {
    Health::checks([AlwaysFailingCheck::new()]);

    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->get(route('administration.monitoring'))
        ->assertSuccessful()
        ->assertInertia(function (Assert $page): Assert {
            $failing = collect($page->toArray()['props']['checks'])
                ->firstWhere('name', 'AlwaysFailing');

            expect($failing)->not->toBeNull()
                ->and($failing['status'])->toBe('failed')
                ->and($failing['message'])->toBe('Échec simulé pour le test');

            return $page->component('administration/monitoring');
        });
});

it('exposes the failed jobs count on the monitoring page', function (): void {
    DB::table('failed_jobs')->insert([
        'uuid' => (string) str()->uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    $admin = User::factory()->create();
    $admin->assignRole('system_admin');

    $this->actingAs($admin)
        ->get(route('administration.monitoring'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('administration/monitoring')
            ->where('queues.failedJobs', 1),
        );
});
