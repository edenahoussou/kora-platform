<?php

use App\Models\User;
use App\Modules\Wallet\Models\Wallet;
use App\Modules\Wallet\Models\WalletTopUp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

it('creates a single recharge when two PostgreSQL requests replay concurrently', function (): void {
    if (DB::getDriverName() !== 'pgsql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'kora_test_')) {
        $this->markTestSkipped('Requires the temporary PostgreSQL database created by scripts/test-pgsql.sh.');
    }

    walletCashEnabled();
    $wallet = walletForUser(User::factory()->create());
    $key = (string) Str::uuid();
    $worker = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$manager = new App\Modules\Payments\PaymentManager;
(new ReflectionClass($manager))->getProperty('resolved')->setValue($manager, [
    'fedapay' => new App\Modules\Payments\Providers\Fake\FakePaymentProvider,
]);
$app->instance(App\Modules\Payments\PaymentManager::class, $manager);
$wallet = App\Modules\Wallet\Models\Wallet::query()->where('uuid', $argv[1])->firstOrFail();
fwrite(STDERR, "ready\n");
$result = app(App\Modules\Wallet\Actions\InitiateWalletTopUpAction::class)->handle($wallet, 5000, 'card', 'fedapay', $argv[2]);
echo json_encode(['uuid' => $result['topup']->uuid, 'replay' => $result['is_replay']]);
CODE;

    $workers = [];
    DB::beginTransaction();
    try {
        Wallet::query()->lockForUpdate()->findOrFail($wallet->getKey());
        for ($index = 0; $index < 2; $index++) {
            $process = new Process([PHP_BINARY, '-r', $worker, $wallet->uuid, $key], base_path(), timeout: 35);
            $process->start();
            $workers[] = $process;
        }
        $deadline = microtime(true) + 15;
        do {
            $ready = count(array_filter($workers, fn (Process $process): bool => str_contains($process->getErrorOutput(), 'ready')));
            if ($ready !== 2) {
                usleep(50000);
            }
        } while ($ready !== 2 && microtime(true) < $deadline);
        expect($ready)->toBe(2);
        expect($workers[0]->isRunning())->toBeTrue()->and($workers[1]->isRunning())->toBeTrue();
    } finally {
        DB::rollBack();
    }

    $replies = [];
    foreach ($workers as $process) {
        expect($process->wait())->toBe(0, $process->getErrorOutput());
        $replies[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
    expect($replies[0]['uuid'])->toBe($replies[1]['uuid'])
        ->and($replies[0]['replay'])->not->toBe($replies[1]['replay'])
        ->and(WalletTopUp::query()->where('wallet_id', $wallet->getKey())->count())->toBe(1);
});
