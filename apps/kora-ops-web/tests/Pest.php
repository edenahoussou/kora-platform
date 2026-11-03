<?php

use App\Models\User;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\IdentityAccess\Data\OtpChallengeStatus;
use App\Modules\IdentityAccess\Data\OtpPurpose;
use App\Modules\IdentityAccess\Models\AuthOtpChallenge;
use App\Modules\Notifications\Jobs\SendPassengerDepartureReminderJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        // Coupe les appels réseau réels (MECeF, WAHA) : ces jobs partent en
        // sync pendant les tests de paiement et explosent le temps de suite.
        Queue::fake([
            FiscalizeReservationInvoiceJob::class,
            SendReservationWhatsAppNotificationJob::class,
            SendPassengerDepartureReminderJob::class,
        ]);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function commercialDirector(): User
{
    $user = User::factory()->create();
    $user->assignRole('commercial_director');

    return $user;
}

function lineWithStops(): array
{
    $line = Line::query()->create([
        'uuid' => Str::uuid()->toString(),
        'code' => 'L'.Str::random(6),
        'name' => 'Cotonou - Parakou',
        'type' => 'nationale',
        'status' => 'active',
    ]);

    $suffix = Str::random(6);

    $originPhysical = PhysicalStop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Cotonou Fares '.$suffix,
        'city' => 'Cotonou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $origin = Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'physical_stop_id' => $originPhysical->id,
        'name' => 'Cotonou',
        'city' => 'Cotonou',
        'country' => 'BJ',
        'stop_order' => 1,
        'km_from_origin' => 0,
        'status' => 'active',
    ]);

    $destinationPhysical = PhysicalStop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'name' => 'Parakou Fares '.$suffix,
        'city' => 'Parakou',
        'country' => 'BJ',
        'status' => 'active',
    ]);

    $destination = Stop::query()->create([
        'uuid' => Str::uuid()->toString(),
        'line_id' => $line->id,
        'physical_stop_id' => $destinationPhysical->id,
        'name' => 'Parakou',
        'city' => 'Parakou',
        'country' => 'BJ',
        'stop_order' => 2,
        'km_from_origin' => 420,
        'status' => 'active',
    ]);

    return [$line, $origin, $destination];
}

function pendingOtpChallenge(
    string $email,
    OtpPurpose $purpose,
    string $code,
    mixed $expiresAt = null,
    ?User $user = null,
): AuthOtpChallenge {
    return AuthOtpChallenge::query()->create([
        'identifier' => $email,
        'user_id' => $user?->id,
        'purpose' => $purpose,
        'code_hash' => Hash::make($code),
        'attempts' => 0,
        'expires_at' => $expiresAt ?: now()->addMinutes(5),
        'status' => OtpChallengeStatus::Pending,
    ]);
}

function pinUser(string $email, ?string $phone = null): User
{
    return User::factory()->create([
        'email' => $email,
        'phone' => $phone,
        'pin_hash' => Hash::make('1234'),
    ]);
}

require_once __DIR__.'/Support/BoardingHelpers.php';
require_once __DIR__.'/Support/WalletHelpers.php';
