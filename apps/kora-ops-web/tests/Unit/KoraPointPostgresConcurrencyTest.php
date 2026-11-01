<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\KoraPoints\Models\KoraPoint;
use App\Modules\KoraPoints\Models\KoraPointEntry;
use App\Modules\KoraPoints\Models\KoraPointSale;
use App\Modules\Payments\Models\Payment;
use Database\Seeders\KoraBusRolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('serializes concurrent point sales against the same PostgreSQL ceiling', function (): void {
    if (DB::getDriverName() !== 'pgsql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'kora_test_')) {
        $this->markTestSkipped('Requires the temporary PostgreSQL database created by scripts/test-pgsql.sh.');
    }
    $this->seed(KoraBusRolePermissionSeeder::class);
    $point = KoraPoint::factory()->create(['commission_basis_points' => 300, 'ceiling_amount' => 9700, 'settlement_calendar' => ['frequency' => 'daily', 'time' => '23:59', 'timezone' => 'UTC']]);
    $agent = User::factory()->create(['agency_id' => $point->agency_id]);
    $agent->assignRole('kora_point_agent');
    $payments = [];
    for ($index = 0; $index < 2; $index++) {
        $reservation = Reservation::query()->create(['created_by_user_id' => $agent->getKey(), 'channel' => 'counter', 'status' => 'paid', 'total_amount' => 10000, 'currency' => 'XOF']);
        $payments[] = Payment::query()->create(['reservation_id' => $reservation->getKey(), 'method' => 'cash', 'provider' => 'counter', 'amount' => 10000, 'currency' => 'XOF', 'status' => 'confirmed', 'confirmed_at' => now(), 'idempotency_key' => (string) Str::uuid()]);
    }
    $worker = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
    Illuminate\Support\Facades\DB::transaction(function () use ($argv) {
        $payment = App\Modules\Payments\Models\Payment::query()->where('uuid', $argv[1])->firstOrFail();
        $agent = App\Models\User::query()->where('uuid', $argv[2])->firstOrFail();
        fwrite(STDERR, "ready\n");
        app(App\Modules\KoraPoints\Actions\RecordKoraPointSaleAction::class)->execute($payment->reservation, $payment, $agent);
    });
} catch (Illuminate\Validation\ValidationException $exception) {
    exit(10);
}
CODE;
    $processes = [];
    DB::beginTransaction();
    try {
        KoraPoint::query()->whereKey($point->getKey())->lockForUpdate()->firstOrFail();
        foreach ($payments as $payment) {
            $process = new Process([PHP_BINARY, '-r', $worker, $payment->uuid, $agent->uuid], base_path());
            $process->setTimeout(60);
            $process->start();
            $processes[] = $process;
        }
        $deadline = microtime(true) + 30;
        do {
            $ready = count(array_filter($processes, fn (Process $p): bool => str_contains($p->getErrorOutput(), 'ready')));
            if ($ready === 2) {
                break;
            }
            usleep(50000);
        } while (microtime(true) < $deadline);
        expect($ready)->toBe(2);
        usleep(200000);
        expect($processes[0]->isRunning())->toBeTrue()->and($processes[1]->isRunning())->toBeTrue();
    } finally {
        DB::rollBack();
    }
    $statuses = [];
    foreach ($processes as $process) {
        $statuses[] = $process->wait();
        expect(trim($process->getErrorOutput()))->toBe('ready');
    }
    sort($statuses);
    expect($statuses)->toBe([0, 10])
        ->and(KoraPointSale::query()->where('point_id', $point->id)->count())->toBe(1)
        ->and((int) KoraPointEntry::query()->where('point_id', $point->id)->sum('amount'))->toBe(9700);
});
