<?php

use App\Models\User;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\Wallet\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    if (DB::getDriverName() !== 'pgsql' || ! str_starts_with(DB::connection()->getDatabaseName(), 'kora_test_')) {
        $this->markTestSkipped('Requires the temporary PostgreSQL database created by scripts/test-pgsql.sh.');
    }
});

it('consumes a shared registration challenge once and attaches one referral', function (): void {
    $referrer = walletForUser(User::factory()->create());
    $token = Str::random(64);
    $challenge = AuthOtpChallenge::query()->create([
        'identifier' => Str::uuid().'@example.com',
        'purpose' => OtpPurpose::Registration,
        'status' => OtpChallengeStatus::Verified,
        'code_hash' => hash('sha256', '123456'),
        'verification_token_hash' => hash('sha256', $token),
        'verified_at' => now(),
        'expires_at' => now()->addMinutes(5),
    ]);

    $payload = [
        'otp_challenge_uuid' => $challenge->uuid,
        'verification_token' => $token,
        'pin' => '1234',
        'device_name' => 'Android',
        'platform' => 'android',
        'device_fingerprint' => 'device-fingerprint-concurrency',
        'referral_code' => $referrer->referral_code,
    ];
    $worker = <<<'CODE'
$payload = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$result = app(App\Modules\IdentityAccess\Services\AuthSessionService::class)->completeRegistration($payload);
echo $result['user']->uuid;
CODE;

    $replies = runConcurrentReferralWorkers($challenge, $worker, [[json_encode($payload, JSON_THROW_ON_ERROR)], [json_encode($payload, JSON_THROW_ON_ERROR)]]);
    $user = User::query()->where('uuid', $replies[0])->sole();

    expect($replies[1])->toBe('verification_token')
        ->and($challenge->refresh()->status)->toBe(OtpChallengeStatus::Consumed)
        ->and($user->tokens()->count())->toBe(1)
        ->and(Wallet::query()->where('referred_by_user_id', $referrer->user_id)->count())->toBe(1)
        ->and($referrer->user->notifications()->count())->toBe(1);
});

it('allows exactly one referrer when two codes are attached concurrently', function (): void {
    $referee = walletForUser(User::factory()->create());
    $first = walletForUser(User::factory()->create());
    $second = walletForUser(User::factory()->create());
    $worker = <<<'CODE'
$wallet = App\Modules\Wallet\Models\Wallet::query()->where('uuid', $argv[1])->firstOrFail();
app(App\Modules\Wallet\Actions\AttachReferralCodeAction::class)->handle($wallet, $argv[2]);
echo 'attached';
CODE;

    $replies = runConcurrentReferralWorkers($referee, $worker, [
        [$referee->uuid, $first->referral_code],
        [$referee->uuid, $second->referral_code],
    ]);

    expect($replies)->toBe(['attached', 'referral_code'])
        ->and($referee->refresh()->referred_by_user_id)->toBeIn([$first->user_id, $second->user_id])
        ->and($first->user->notifications()->count() + $second->user->notifications()->count())->toBe(1);
});

/**
 * @param  list<list<string>>  $arguments
 * @return list<string>
 */
function runConcurrentReferralWorkers(AuthOtpChallenge|Wallet $lockedRow, string $work, array $arguments): array
{
    $bootstrap = <<<'CODE'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$pid = Illuminate\Support\Facades\DB::selectOne('select pg_backend_pid() as pid')->pid;
fwrite(STDERR, "ready:$pid\n");
try {
CODE;
    $reportValidation = <<<'CODE'
} catch (Illuminate\Validation\ValidationException $exception) {
    echo array_key_first($exception->errors());
}
CODE;
    $worker = $bootstrap."\n".$work."\n".$reportValidation;
    $workers = [];
    try {
        $blockingPid = DB::selectOne('select pg_backend_pid() as pid')->pid;
        DB::beginTransaction();
        try {
            $lockedRow->refreshForUpdate();
            foreach ($arguments as $args) {
                $process = new Process([PHP_BINARY, '-r', $worker, ...$args], base_path(), timeout: 35);
                $process->start();
                $workers[] = $process;
                expect($process->waitUntil(fn (string $type, string $output): bool => preg_match('/ready:\d+/', $output) === 1))->toBeTrue();
                preg_match('/ready:(\d+)/', $process->getErrorOutput(), $matches);
                $workerPid = (int) $matches[1];
                $deadline = microtime(true) + 10;

                do {
                    $isBlocked = (bool) DB::selectOne(
                        'select cardinality(pg_blocking_pids(?)) > 0 as blocked',
                        [$workerPid],
                        useReadPdo: false,
                    )->blocked;
                    if ($isBlocked || ! $process->isRunning()) {
                        break;
                    }
                    usleep(50000);
                } while (microtime(true) < $deadline);

                expect($workerPid)->not->toBe($blockingPid)
                    ->and($isBlocked)->toBeTrue();
            }
        } finally {
            DB::rollBack();
        }

        $replies = [];
        foreach ($workers as $process) {
            expect($process->wait())->toBe(0, $process->getErrorOutput());
            $replies[] = $process->getOutput();
        }
        sort($replies);

        return $replies;
    } finally {
        foreach ($workers as $process) {
            $process->stop();
        }
    }
}
