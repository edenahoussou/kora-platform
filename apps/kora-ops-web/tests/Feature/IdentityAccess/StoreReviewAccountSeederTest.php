<?php

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\Departures\Models\Departure;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Database\Seeders\StoreReviewAccountSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function (): void {
    config([
        'services.store_review.enabled' => true,
        'services.store_review.otp_code' => '654321',
        'services.store_review.identifiers' => [
            'apple-review@koraexpress.app',
            'apple-review+signup@koraexpress.app',
        ],
        'services.store_review.login_identifier' => 'apple-review@koraexpress.app',
        'services.store_review.pin' => '2468',
    ]);
});

function reviewDeparture(): Departure
{
    [$line, $origin, $destination] = lineWithStops();

    $bus = Bus::query()->create([
        'registration_number' => 'BJ-REV-'.Str::upper(Str::random(5)),
        'brand' => 'KORA',
        'model' => 'Coach',
        'seat_count' => 2,
        'status' => 'active',
    ]);

    foreach (['12A', '12B'] as $code) {
        Seat::query()->create([
            'bus_id' => $bus->id,
            'code' => $code,
            'type' => 'standard',
            'sellable' => true,
            'status' => 'active',
        ]);
    }

    return Departure::query()->create([
        'line_id' => $line->id,
        'bus_id' => $bus->id,
        'departure_date' => now()->addDay()->toDateString(),
        'departure_time' => '08:00',
        'scheduled_at' => now()->addDay()->setTime(8, 0),
        'departure_timezone' => 'Africa/Porto-Novo',
        'stop_times' => [
            ['stop_uuid' => $origin->uuid, 'departure_minutes' => 0, 'arrival_minutes' => null, 'timezone' => 'Africa/Porto-Novo'],
            ['stop_uuid' => $destination->uuid, 'departure_minutes' => null, 'arrival_minutes' => 240, 'timezone' => 'Africa/Porto-Novo'],
        ],
        'estimated_arrival_at' => now()->addDay()->setTime(12, 0),
        'status' => 'scheduled',
    ]);
}

it('creates the store review login account idempotently', function (): void {
    $this->seed(StoreReviewAccountSeeder::class);
    $this->seed(StoreReviewAccountSeeder::class);

    $user = User::query()->where('email', 'apple-review@koraexpress.app')->sole();

    expect($user->name)->toBe('App Store Review')
        ->and($user->phone)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and(Hash::check('2468', (string) $user->pin_hash))->toBeTrue()
        ->and($user->hasRole('passenger'))->toBeTrue()
        ->and(User::query()->where('email', 'apple-review@koraexpress.app')->count())->toBe(1);
});

it('never pre-creates the account reserved for the signup walkthrough', function (): void {
    $this->seed(StoreReviewAccountSeeder::class);

    expect(User::query()->where('email', 'apple-review+signup@koraexpress.app')->exists())->toBeFalse();
});

it('seeds paid trips and tickets outside production, idempotently', function (): void {
    reviewDeparture();

    $this->seed(StoreReviewAccountSeeder::class);

    $user = User::query()->where('email', 'apple-review@koraexpress.app')->sole();
    $reservations = Reservation::query()->where('created_by_user_id', $user->id)->get();

    expect($reservations)->toHaveCount(2)
        ->and($reservations->every(fn (Reservation $reservation): bool => str_starts_with((string) $reservation->reference, 'REV-')))->toBeTrue()
        ->and(Payment::query()->whereIn('reservation_id', $reservations->pluck('id'))->where('status', PaymentStatus::Confirmed)->count())->toBe(2)
        ->and(Ticket::query()->whereIn('reservation_id', $reservations->pluck('id'))->where('status', TicketStatus::Issued)->count())->toBe(2)
        ->and(ReservationSegment::query()->whereIn('reservation_id', $reservations->pluck('id'))->count())->toBe(2);

    $tickets = Ticket::query()->whereIn('reservation_id', $reservations->pluck('id'))->get();

    expect($tickets->every(fn (Ticket $ticket): bool => str_contains((string) $ticket->qr_code, '.')))->toBeTrue();

    $this->seed(StoreReviewAccountSeeder::class);

    expect(Reservation::query()->where('created_by_user_id', $user->id)->count())->toBe(2)
        ->and(Ticket::query()->whereIn('reservation_id', $reservations->pluck('id'))->count())->toBe(2);
});

it('creates only the account in production', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');
    reviewDeparture();

    $this->artisan('db:seed', [
        '--class' => StoreReviewAccountSeeder::class,
        '--force' => true,
    ])->assertExitCode(0);

    expect(User::query()->where('email', 'apple-review@koraexpress.app')->exists())->toBeTrue()
        ->and(Reservation::query()->count())->toBe(0)
        ->and(Ticket::query()->count())->toBe(0);
});

it('fails loudly when the configuration is incomplete', function (): void {
    config(['services.store_review.identifiers' => []]);
    expect(fn () => (new StoreReviewAccountSeeder)->run())
        ->toThrow(RuntimeException::class, 'STORE_REVIEW_IDENTIFIERS est vide');

    config(['services.store_review.identifiers' => ['someone-else@example.com']]);
    expect(fn () => (new StoreReviewAccountSeeder)->run())
        ->toThrow(RuntimeException::class, 'doit figurer dans STORE_REVIEW_IDENTIFIERS');

    config([
        'services.store_review.identifiers' => ['apple-review@koraexpress.app'],
        'services.store_review.pin' => '12',
    ]);
    expect(fn () => (new StoreReviewAccountSeeder)->run())
        ->toThrow(RuntimeException::class, 'STORE_REVIEW_PIN');
});
