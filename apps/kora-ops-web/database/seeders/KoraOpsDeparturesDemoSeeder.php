<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Booking\Models\Reservation;
use App\Modules\Booking\Models\ReservationPassenger;
use App\Modules\Booking\Models\ReservationSegment;
use App\Modules\BusNetwork\Models\Bus;
use App\Modules\BusNetwork\Models\BusComposition;
use App\Modules\BusNetwork\Models\Line;
use App\Modules\BusNetwork\Models\PhysicalStop;
use App\Modules\BusNetwork\Models\Seat;
use App\Modules\BusNetwork\Models\Stop;
use App\Modules\Departures\Models\Departure;
use App\Modules\Notifications\Jobs\PublishManifestStaleNotificationJob;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus as BusFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Jeu de données de TEST : des départs du convoyeur de démo, dans tous les
 * statuts, avec leurs passagers dans tous les états de billet — de quoi
 * remplir Tournée, Manifeste, Scan, Descente anticipée et Clôture no-show de
 * KORA OPS Mobile, et les pages Départs / Manifeste d'OPS Web.
 *
 * Lancer (autonome, ré-exécutable sans doublon) :
 *
 *   php artisan db:seed --class=KoraOpsDeparturesDemoSeeder
 *
 * Prérequis : KoraDemoUserSeeder (convoyeur `conductor.marc@kora.demo`, ou
 * `border_demo.conductor_email` si défini). À la ré-exécution, les départs
 * sont recalés sur « aujourd'hui / demain… » sans recréer les passagers.
 *
 * Réseau créé : ligne nationale « Cotonou - Grand-Popo (démo OPS) » — Cotonou,
 * Calavi, Ouidah, Grand-Popo — et un bus de 56 places par départ.
 *
 * | Bus            | Quand          | Statut      | Passagers (segments)                                   |
 * |----------------|----------------|-------------|--------------------------------------------------------|
 * | DEMO-OPS-BUS-01| aujourd'hui 06:30 | arrived  | 20 embarqués, 4 descentes anticipées, 2 annulés        |
 * | DEMO-OPS-BUS-02| aujourd'hui 09:00 | departed | 20 embarqués, 5 absents (no-show à clôturer), 3 descentes, 2 annulés |
 * | DEMO-OPS-BUS-03| aujourd'hui 11:30 | boarding | 12 embarqués, 14 billets émis, 2 annulés, 2 impayés, 2 expirés |
 * | DEMO-OPS-BUS-04| aujourd'hui 16:00 | scheduled| 34 billets émis, 2 impayés, 2 annulés                  |
 * | DEMO-OPS-BUS-05| demain 07:00   | scheduled   | 18 billets émis                                        |
 * | DEMO-OPS-BUS-06| après-demain 08:30 | scheduled | 10 billets émis, retard de 25 min enregistré         |
 * | DEMO-OPS-BUS-07| hier 14:00     | arrived     | 18 embarqués, 2 descentes anticipées, 2 annulés        |
 * | DEMO-OPS-BUS-08| demain 18:00   | cancelled   | 8 billets remboursés                                   |
 *
 * Les heures réelles (embarquement, départ, arrivée) sont posées si les
 * colonnes de MEZ-98 existent. Aucune notification « manifeste périmé »
 * n'est produite (le job est neutralisé pendant le seeding).
 */
class KoraOpsDeparturesDemoSeeder extends Seeder
{
    private const string LINE = 'DEMO-OPS-CPN';

    private const string COMPOSITION = 'DEMO-OPS-COMP';

    private const int SEAT_COUNT = 56;

    private User $conductor;

    private Line $line;

    /** @var list<Stop> Arrêts dans l'ordre : Cotonou, Calavi, Ouidah, Grand-Popo. */
    private array $stops = [];

    private BusComposition $composition;

    private int $counter = 0;

    /** @var array<string, int> */
    private array $totals = [];

    public function run(): void
    {
        // Le job « manifeste périmé » (file différée + verrou unique) n'a aucun intérêt ici.
        $originalBus = BusFacade::getFacadeRoot();
        BusFacade::fake([PublishManifestStaleNotificationJob::class]);

        try {
            $this->withModelEvents($this->seedAll(...));
        } finally {
            BusFacade::swap($originalBus);
        }
    }

    /**
     * `DatabaseSeeder` utilise `WithoutModelEvents` : les événements Eloquent sont coupés pendant tout le
     * seeding, donc les hooks des modèles (uuid, référence de réservation…) ne s'exécutent pas. Ce seeder
     * rétablit le dispatcher le temps de son exécution, puis remet l'état d'origine.
     */
    private function withModelEvents(callable $callback): void
    {
        $previous = Model::getEventDispatcher();
        Model::setEventDispatcher(app('events'));

        try {
            $callback();
        } finally {
            if ($previous === null) {
                Model::unsetEventDispatcher();
            } else {
                Model::setEventDispatcher($previous);
            }
        }
    }

    private function seedAll(): void
    {
        $email = (string) config('border_demo.conductor_email', 'conductor.marc@kora.demo');
        $conductor = User::query()->where('email', $email)->first();

        if ($conductor === null) {
            throw new RuntimeException("Convoyeur {$email} introuvable : lancez d'abord KoraDemoUserSeeder.");
        }

        $this->conductor = $conductor;
        $this->seedNetwork();

        $schedule = [
            ['DEMO-OPS-BUS-01', 'today', '06:30', 'arrived', ['boarded' => 20, 'released' => 4, 'cancelled' => 2]],
            // Départ en route : 5 billets émis jamais montés → « absents », à clôturer (no-show).
            ['DEMO-OPS-BUS-02', 'today', '09:00', 'departed', ['boarded' => 20, 'ticketed' => 5, 'released' => 3, 'cancelled' => 2]],
            ['DEMO-OPS-BUS-03', 'today', '11:30', 'boarding', ['boarded' => 12, 'ticketed' => 14, 'cancelled' => 2, 'unpaid' => 2, 'expired' => 2]],
            ['DEMO-OPS-BUS-04', 'today', '16:00', 'scheduled', ['ticketed' => 34, 'unpaid' => 2, 'cancelled' => 2]],
            ['DEMO-OPS-BUS-05', '+1 day', '07:00', 'scheduled', ['ticketed' => 18]],
            ['DEMO-OPS-BUS-06', '+2 days', '08:30', 'scheduled', ['ticketed' => 10], 25],
            ['DEMO-OPS-BUS-07', 'yesterday', '14:00', 'arrived', ['boarded' => 18, 'released' => 2, 'cancelled' => 2]],
            ['DEMO-OPS-BUS-08', '+1 day', '18:00', 'cancelled', ['cancelled' => 8]],
        ];

        foreach ($schedule as $row) {
            [$registration, $when, $time, $status, $mix] = $row;
            $delay = $row[5] ?? null;

            $departure = $this->departure($registration, $when, $time, $status, $delay);

            if ($departure->reservationSegments()->exists()) {
                continue;
            }

            $index = 0;
            foreach ($mix as $passengerStatus => $count) {
                for ($n = 0; $n < $count; $n++) {
                    $this->passenger($departure, $passengerStatus, $index++);
                }
            }
        }

        $this->command->info(sprintf(
            'KoraOpsDeparturesDemoSeeder : %d départs affectés à %s ; passagers créés : %s.',
            count($schedule),
            $this->conductor->email,
            $this->totals === [] ? 'aucun (déjà en base)' : collect($this->totals)->map(fn (int $n, string $s): string => "{$n} {$s}")->implode(', '),
        ));
    }

    // ------------------------------------------------------------------
    // Réseau
    // ------------------------------------------------------------------

    private function seedNetwork(): void
    {
        $this->line = Line::query()->firstOrCreate(
            ['code' => self::LINE],
            [
                'name' => 'Cotonou - Grand-Popo (démo OPS)',
                'type' => 'nationale',
                'countries_crossed' => ['BJ'],
                'distance_km' => 75,
                'estimated_duration_minutes' => 120,
                'status' => 'active',
            ],
        );

        $this->stops = [
            $this->stop('DEMO-OPS-CTN', 'Cotonou', 1, 0),
            $this->stop('DEMO-OPS-CAL', 'Abomey-Calavi', 2, 15),
            $this->stop('DEMO-OPS-OUI', 'Ouidah', 3, 42),
            $this->stop('DEMO-OPS-GPO', 'Grand-Popo', 4, 75),
        ];

        $layout = [];
        foreach (range(1, self::SEAT_COUNT / 4) as $row) {
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                $layout[] = $row.$letter;
            }
        }

        $this->composition = BusComposition::query()->firstOrCreate(
            ['code' => self::COMPOSITION],
            [
                'name' => 'Coach démo OPS (56 places)',
                'seat_count' => self::SEAT_COUNT,
                'layout' => [['type' => 'standard', 'codes' => $layout]],
                'status' => 'active',
            ],
        );
    }

    private function stop(string $physicalCode, string $city, int $order, int $km): Stop
    {
        $physical = PhysicalStop::query()->firstOrCreate(
            ['code' => $physicalCode],
            ['name' => $city, 'city' => $city, 'country' => 'BJ', 'status' => 'active'],
        );

        return Stop::query()->firstOrCreate(
            ['line_id' => $this->line->id, 'physical_stop_id' => $physical->id],
            ['name' => $city, 'city' => $city, 'country' => 'BJ', 'stop_order' => $order, 'km_from_origin' => $km, 'status' => 'active'],
        );
    }

    // ------------------------------------------------------------------
    // Départs
    // ------------------------------------------------------------------

    private function departure(string $registration, string $when, string $time, string $status, ?int $delayMinutes): Departure
    {
        $bus = Bus::query()->firstOrCreate(
            ['registration_number' => $registration],
            ['composition_id' => $this->composition->id, 'seat_count' => self::SEAT_COUNT, 'status' => 'active'],
        );

        // Immuable quoi qu'en dise la config de l'app : addMinutes()/subMinutes() ne modifient jamais $scheduled.
        $scheduled = CarbonImmutable::parse(now()->modify($when)->toDateString().' '.$time);

        $departure = Departure::query()->where('bus_id', $bus->id)->where('line_id', $this->line->id)->first()
            ?? Departure::query()->create([
                'line_id' => $this->line->id,
                'bus_id' => $bus->id,
                'departure_date' => $scheduled->toDateString(),
                'departure_time' => $time,
                'estimated_arrival_at' => $scheduled->addMinutes(120),
                'status' => $status,
            ]);

        $attributes = [
            'departure_date' => $scheduled->toDateString(),
            'departure_time' => $time,
            'estimated_arrival_at' => $scheduled->addMinutes(120),
            'status' => $status,
            'conductor_id' => $this->conductor->id,
        ] + $this->progress($status, $scheduled);

        if ($delayMinutes !== null) {
            $attributes += [
                'delay_minutes' => $delayMinutes,
                'delay_reason' => 'Attente du bus de correspondance',
                'delay_recorded_at' => now(),
                'delay_recorded_by' => $this->conductor->id,
            ];
        }

        // Recalage à chaque exécution, sans déclencher de notification « départ modifié ».
        Departure::withoutEvents(fn () => $departure->forceFill($attributes)->save());

        return $departure;
    }

    /**
     * Heures réelles cohérentes avec le statut (colonnes de MEZ-98, si migrées).
     *
     * @return array<string, mixed>
     */
    private function progress(string $status, CarbonInterface $scheduled): array
    {
        if (! Schema::hasColumn('departures', 'departed_at')) {
            return [];
        }

        return match ($status) {
            'boarding' => ['boarding_started_at' => $scheduled->subMinutes(30)],
            'departed' => ['boarding_started_at' => $scheduled->subMinutes(30), 'departed_at' => $scheduled->addMinutes(8)],
            'arrived' => [
                'boarding_started_at' => $scheduled->subMinutes(30),
                'departed_at' => $scheduled->addMinutes(8),
                'arrived_at' => $scheduled->addMinutes(128),
            ],
            default => ['boarding_started_at' => null, 'departed_at' => null, 'arrived_at' => null],
        };
    }

    // ------------------------------------------------------------------
    // Passagers
    // ------------------------------------------------------------------

    private const array FIRST_NAMES = [
        'Adjoa',
        'Bernard',
        'Chantal',
        'Didier',
        'Estelle',
        'Fidèle',
        'Grâce',
        'Hervé',
        'Inès',
        'Jérémie',
        'Karim',
        'Léonie',
        'Maxime',
        'Nadège',
        'Olivier',
        'Pélagie',
        'Quentin',
        'Rachidatou',
        'Sylvain',
        'Thérèse',
        'Urbain',
        'Victoire',
        'Wilfrid',
        'Yasmine',
        'Zacharie',
        'Aïcha',
        'Boniface',
        'Carine',
        'Dieudonné',
        'Élodie',
    ];

    private const array LAST_NAMES = [
        'Agbodjan',
        'Behanzin',
        'Chabi',
        'Dossou-Yovo',
        'Fassinou',
        'Gbaguidi',
        'Hounsou',
        'Idohou',
        'Kiki',
        'Lokossou',
        'Mama',
        'Nouatin',
        'Oussou',
        'Padonou',
        'Quenum',
        'Sagbo',
        'Tokpanou',
        'Vodounou',
        'Yèhouénou',
        'Zinsou',
        'Alladatin',
        'Bonou',
        'Codjia',
        'Djossou',
        'Ehouzou',
    ];

    private const array NATIONALITIES = ['BJ', 'BJ', 'BJ', 'BJ', 'BJ', 'BJ', 'BJ', 'TG', 'NG', 'GH', 'CI', 'BF'];

    /**
     * Un passager : réservation, paiement, segment, billet — selon l'état voulu :
     * boarded | ticketed | released (descente anticipée) | cancelled | unpaid | expired.
     */
    private function passenger(Departure $departure, string $state, int $index): void
    {
        [$origin, $destination, $dropoff] = $this->journey($index, $state);
        $fare = max(500, (int) round((($destination->km_from_origin - $origin->km_from_origin) * 50 + 500) / 100) * 100);
        $number = ++$this->counter;
        $nationality = self::NATIONALITIES[$number % count(self::NATIONALITIES)];

        [$reservationStatus, $segmentStatus, $paymentStatus, $ticketStatus] = match ($state) {
            'boarded' => ['boarded', 'boarded', 'confirmed', 'used'],
            'released' => ['boarded', 'released', 'confirmed', 'used'],
            'ticketed' => ['ticketed', 'ticketed', 'confirmed', 'issued'],
            'cancelled' => ['cancelled', 'cancelled', 'refunded', 'refunded'],
            'unpaid' => ['confirmed', 'confirmed', 'pending', null],
            'expired' => ['expired', 'expired', null, null],
            default => throw new RuntimeException("État de passager inconnu : {$state}"),
        };

        $reservation = Reservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => $reservationStatus,
            'total_amount' => $fare,
            'currency' => 'XOF',
            'channel' => 'counter',
        ]);

        if ($paymentStatus !== null) {
            Payment::query()->create([
                'uuid' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'provider' => 'cash',
                'idempotency_key' => (string) Str::uuid(),
                'amount' => $fare,
                'currency' => 'XOF',
                'status' => $paymentStatus,
                'confirmed_at' => in_array($paymentStatus, ['confirmed', 'refunded'], true) ? now() : null,
            ]);
        }

        $isNational = $nationality === 'BJ';

        $passenger = ReservationPassenger::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'status' => 'active',
            'passenger_index' => 0,
            'first_name' => self::FIRST_NAMES[$number % count(self::FIRST_NAMES)],
            'last_name' => self::LAST_NAMES[intdiv($number, 2) % count(self::LAST_NAMES)],
            'phone' => '+22996'.str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'nationality' => $nationality,
            'document_type' => $isNational ? 'national_id' : 'passport',
            'document_number' => ($isNational ? 'CNI' : 'PP').str_pad((string) (700000 + $number), 7, '0', STR_PAD_LEFT),
            'birth_date' => now()->subYears(20 + ($number % 35))->toDateString(),
            'yellow_fever_vaccinated' => true,
        ]);

        ReservationSegment::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'leg_order' => 1,
            'departure_id' => $departure->id,
            'seat_id' => $this->seat($departure, $index)->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => $segmentStatus,
            'actual_dropoff_stop_id' => $dropoff?->id,
            'released_at' => $state === 'released' ? now() : null,
        ]);

        if ($ticketStatus !== null) {
            Ticket::query()->create([
                'uuid' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'reservation_passenger_id' => $passenger->id,
                'qr_code' => 'QR-'.Str::random(24),
                'short_code' => 'OPS'.Str::upper(Str::random(5)),
                'status' => $ticketStatus,
                'issued_at' => now(),
            ]);
        }

        $this->totals[$state] = ($this->totals[$state] ?? 0) + 1;
    }

    /**
     * Trajet du passager. Une descente anticipée part de Cotonou vers Grand-Popo
     * et descend à Calavi ou Ouidah ; les autres varient sur les 4 arrêts.
     *
     * @return array{0: Stop, 1: Stop, 2: Stop|null} Origine, destination, arrêt de descente réel.
     */
    private function journey(int $index, string $state): array
    {
        [$cotonou, $calavi, $ouidah, $grandPopo] = $this->stops;

        if ($state === 'released') {
            return [$cotonou, $grandPopo, $index % 2 === 0 ? $ouidah : $calavi];
        }

        $patterns = [
            [$cotonou, $grandPopo],
            [$cotonou, $ouidah],
            [$cotonou, $calavi],
            [$calavi, $grandPopo],
            [$calavi, $ouidah],
            [$ouidah, $grandPopo],
            [$cotonou, $grandPopo],
            [$cotonou, $ouidah],
        ];
        [$origin, $destination] = $patterns[$index % count($patterns)];

        return [$origin, $destination, null];
    }

    private function seat(Departure $departure, int $index): Seat
    {
        $row = intdiv($index, 4) + 1;
        $letter = ['A', 'B', 'C', 'D'][$index % 4];

        return Seat::query()->firstOrCreate(
            ['bus_id' => $departure->bus_id, 'code' => $row.$letter],
            ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
        );
    }
}
