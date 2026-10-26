<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Booking\Data\ReservationChannel;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\IdentityAccess\Data\SystemRole;
use App\Modules\IdentityAccess\Data\UserStatus;
use App\Modules\IdentityAccess\Models\Role;
use App\Modules\Payments\Data\PaymentStatus;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Actions\BuildTicketQrPayloadAction;
use App\Modules\Ticketing\Data\TicketStatus;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Compte passager de revue App Store / Play Store (MEZ-152).
 *
 * - Crée (ou réinitialise) le compte de connexion whitelisté, avec le PIN
 *   de `STORE_REVIEW_PIN` et un email vérifié : le reviewer peut se
 *   connecter sans recevoir d'OTP (code fixe accepté par le gate).
 * - Hors production uniquement : crée 1 à 2 réservations payées + billets
 *   sur un départ à venir, pour que l'écran « Mes voyages » ne soit pas vide
 *   pendant la recette.
 *
 * Idempotent : re-exécuter le seeder réapplique le PIN et ne duplique ni
 * les réservations ni les billets. En production, il ne crée que le compte.
 *
 * Les autres identifiants whitelistés (comptes d'inscription) ne sont
 * jamais créés ici, sinon le parcours d'inscription renverrait
 * `account_exists` (409).
 */
class StoreReviewAccountSeeder extends Seeder
{
    private const DEMO_TRIP_COUNT = 2;

    private const DEMO_TICKET_AMOUNT = 7000;

    public function run(): void
    {
        $user = $this->seedAccount();

        if (app()->isProduction()) {
            $this->command->info('Store review : compte seul créé (données démo désactivées en production).');

            return;
        }

        $this->seedDemoTrips($user);
    }

    private function seedAccount(): User
    {
        $identifiers = $this->identifiers();
        $email = $this->loginIdentifier();

        if ($identifiers === []) {
            throw new RuntimeException(
                'STORE_REVIEW_IDENTIFIERS est vide : impossible de créer le compte de revue.',
            );
        }

        if (! in_array($email, $identifiers, true)) {
            throw new RuntimeException(sprintf(
                'STORE_REVIEW_LOGIN_IDENTIFIER (%s) doit figurer dans STORE_REVIEW_IDENTIFIERS.',
                $email,
            ));
        }

        $pin = (string) config('services.store_review.pin', '');

        if (preg_match('/^\d{4}$/', $pin) !== 1) {
            throw new RuntimeException('STORE_REVIEW_PIN doit contenir exactement 4 chiffres.');
        }

        $user = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) Str::uuid(),
                'name' => 'App Store Review',
                'phone' => null,
                'country_code' => '+229',
                'status' => UserStatus::Active,
                'password' => Hash::make(Str::random(32)),
            ],
        );

        $user->forceFill([
            'name' => 'App Store Review',
            // Le challenge OTP porte le téléphone quand il existe : on le
            // laisse vide pour que la whitelist email fasse foi.
            'phone' => null,
            'pin_hash' => Hash::make($pin),
            'status' => UserStatus::Active,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        $role = Role::query()->firstOrCreate(
            ['name' => SystemRole::Passenger->value, 'guard_name' => 'web'],
            [
                'is_system' => true,
                'label' => SystemRole::Passenger->translatedLabel(),
                'category' => SystemRole::Passenger->category()->value,
                'is_active' => true,
            ],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        $this->command->info("Store review : compte {$email} prêt (PIN configuré, email vérifié).");

        return $user;
    }

    private function seedDemoTrips(User $user): void
    {
        $context = $this->findDepartureContext();

        if ($context === null) {
            $this->command->warn(
                'Store review : aucun départ à venir avec des sièges libres, données démo ignorées.',
            );

            return;
        }

        ['departure' => $departure, 'origin' => $origin, 'destination' => $destination, 'seats' => $seats] = $context;

        $created = 0;

        foreach ($seats as $index => $seat) {
            $reference = $this->reference($user->email, $index);

            if (Reservation::query()->where('reference', $reference)->exists()) {
                continue;
            }

            $this->createTrip($user, $departure, $origin, $destination, $seat, $reference, $index);
            $created++;
        }

        $this->command->info($created > 0
            ? "Store review : {$created} réservation(s) démo créée(s) sur le départ {$departure->uuid}."
            : 'Store review : données démo déjà présentes, rien à faire.');
    }

    /**
     * @return array{departure: Departure, origin: Stop, destination: Stop, seats: Collection<int, Seat>}|null
     */
    private function findDepartureContext(): ?array
    {
        $departures = Departure::query()
            ->where('status', 'scheduled')
            ->where('scheduled_at', '>', now())
            ->with(['line.stops', 'bus.seats'])
            ->orderBy('scheduled_at')
            ->limit(20)
            ->get();

        foreach ($departures as $departure) {
            $stops = $departure->line?->stops?->sortBy('stop_order')->values();

            if ($stops === null || $stops->count() < 2) {
                continue;
            }

            $seats = $this->freeSeats($departure);

            if ($seats->isEmpty()) {
                continue;
            }

            return [
                'departure' => $departure,
                'origin' => $stops->first(),
                'destination' => $stops->last(),
                'seats' => $seats->take(self::DEMO_TRIP_COUNT)->values(),
            ];
        }

        return null;
    }

    /**
     * @return Collection<int, Seat>
     */
    private function freeSeats(Departure $departure): Collection
    {
        $takenSeatIds = ReservationSegment::query()
            ->where('departure_id', $departure->id)
            ->whereNull('released_at')
            ->pluck('seat_id');

        return $departure->bus
            ? $departure->bus->seats
                ->where('sellable', true)
                ->where('status', 'active')
                ->reject(fn (Seat $seat): bool => $takenSeatIds->contains($seat->id))
                ->values()
            : collect();
    }

    private function createTrip(
        User $user,
        Departure $departure,
        Stop $origin,
        Stop $destination,
        Seat $seat,
        string $reference,
        int $index,
    ): void {
        $reservation = Reservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'reference' => $reference,
            'created_by_user_id' => $user->id,
            'status' => 'ticketed',
            'ticket_issuance_status' => 'issued',
            'ticket_issued_at' => now(),
            'total_amount' => self::DEMO_TICKET_AMOUNT,
            'currency' => 'XOF',
            'channel' => ReservationChannel::Online->value,
            'total_snapshot' => ['passenger_allocations' => [self::DEMO_TICKET_AMOUNT]],
        ]);

        Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'provider' => 'cash',
            'idempotency_key' => 'store-review-payment-'.$reference,
            'amount' => self::DEMO_TICKET_AMOUNT,
            'currency' => 'XOF',
            'status' => PaymentStatus::Confirmed,
            'confirmed_at' => now(),
        ]);

        $passenger = ReservationPassenger::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'status' => 'active',
            'passenger_index' => 0,
            'first_name' => 'App Store',
            'last_name' => 'Review',
            'phone' => null,
        ]);

        ReservationSegment::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'leg_order' => 1,
            'direction' => 'outbound',
            'passenger_index' => 0,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => 'ticketed',
        ]);

        $ticket = Ticket::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'qr_code' => (string) Str::uuid(),
            'short_code' => 'REV'.strtoupper(Str::random(6)),
            'status' => TicketStatus::Issued,
            'issued_at' => now(),
        ]);

        $this->signTicketQr($reservation, $ticket, $index);
    }

    private function signTicketQr(Reservation $reservation, Ticket $ticket, int $index): void
    {
        try {
            $payload = app(BuildTicketQrPayloadAction::class)->execute($reservation, $ticket);
        } catch (\Throwable $exception) {
            $this->command->warn(
                "Store review : QR du billet {$index} non signé (clé Ed25519 absente ?) : {$exception->getMessage()}",
            );

            return;
        }

        $ticket->forceFill(['qr_code' => $payload])->save();
    }

    private function reference(string $email, int $index): string
    {
        return 'REV-'.strtoupper(substr(hash('sha256', $email.'#'.$index), 0, 8));
    }

    /**
     * @return list<string>
     */
    private function identifiers(): array
    {
        $identifiers = config('services.store_review.identifiers', []);

        if (is_string($identifiers)) {
            $identifiers = explode(',', $identifiers);
        }

        return array_values(array_filter(array_map(
            static fn (mixed $value): string => strtolower(trim((string) $value)),
            (array) $identifiers,
        ), static fn (string $value): bool => $value !== ''));
    }

    private function loginIdentifier(): string
    {
        return strtolower(trim((string) config('services.store_review.login_identifier', '')));
    }
}
