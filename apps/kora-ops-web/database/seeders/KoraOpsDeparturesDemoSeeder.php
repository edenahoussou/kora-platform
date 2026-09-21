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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Bus as BusFacade;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Jeu de données de TEST : tournées INTERNATIONALES du convoyeur de démo —
 * Bénin → Togo et Bénin → Côte d'Ivoire — avec des passagers dont TOUS les
 * billets sont « en attente » d'embarquement. De quoi tester le manifeste
 * frontière (impression P501 / PDF téléphone), le scan et la synchro sur des
 * lignes internationales.
 *
 * Lancer (autonome, ré-exécutable sans doublon) :
 *
 *   php artisan db:seed --class=KoraOpsInternationalToursDemoSeeder
 *
 * Prérequis : KoraDemoUserSeeder (convoyeur `conductor.marc@kora.demo`, ou
 * `border_demo.conductor_email` si défini). À la ré-exécution, les départs
 * sont recalés sur « aujourd'hui / demain… » sans recréer les passagers.
 *
 * « Billet en attente » = billet ÉMIS (`issued`), passager pas encore monté
 * (segment `ticketed`) : le cas d'un départ programmé avant l'embarquement.
 * Pour plutôt des réservations en attente de PAIEMENT (sans billet), mettez
 * la constante PASSENGER_STATE à 'unpaid'.
 *
 * Tous les passagers sont COMPLETS (nom, nationalité, type et numéro de
 * document) : le manifeste frontière s'imprime sans refus. Nationalités
 * variées (BJ, TG, GH, CI, NG, BF), pièces variées (passeport, carte
 * biométrique, carte d'identité).
 *
 * | Bus              | Ligne              | Quand              | Passagers |
 * |------------------|--------------------|--------------------|-----------|
 * | DEMO-INTL-BUS-01 | Cotonou - Lomé     | aujourd'hui 07:30  | 38        |
 * | DEMO-INTL-BUS-02 | Cotonou - Lomé     | aujourd'hui 14:00  | 30        |
 * | DEMO-INTL-BUS-03 | Cotonou - Lomé     | demain 07:30       | 45        |
 * | DEMO-INTL-BUS-04 | Cotonou - Lomé     | après-demain 09:00 | 24        |
 * | DEMO-INTL-BUS-05 | Cotonou - Abidjan  | demain 05:00       | 40        |
 * | DEMO-INTL-BUS-06 | Cotonou - Abidjan  | aujourd'hui 20:00  | 35        |
 * | DEMO-INTL-BUS-07 | Cotonou - Abidjan  | dans 3 jours 05:00 | 50        |
 *
 * Cotonou - Lomé : Cotonou, Ouidah, Hillacondji (frontière), Lomé.
 * Cotonou - Abidjan : Cotonou, Lomé, Accra, Abidjan (BJ, TG, GH, CI).
 */
class KoraOpsInternationalToursDemoSeeder extends Seeder
{
    /** État des passagers : 'ticketed' (billet émis, en attente d'embarquement) ou 'unpaid' (paiement en attente). */
    private const string PASSENGER_STATE = 'ticketed';

    private const string LINE_TOGO = 'DEMO-INTL-BJ-TG';

    private const string LINE_IVOIRE = 'DEMO-INTL-BJ-CI';

    private const string COMPOSITION = 'DEMO-INTL-COMP';

    private const int SEAT_COUNT = 56;

    private User $conductor;

    private Line $togo;

    private Line $ivoire;

    /** @var list<Stop> Cotonou, Ouidah, Hillacondji, Lomé. */
    private array $togoStops = [];

    /** @var list<Stop> Cotonou, Lomé, Accra, Abidjan. */
    private array $ivoireStops = [];

    private BusComposition $composition;

    private int $counter = 0;

    private int $created = 0;

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
            ['DEMO-INTL-BUS-01', $this->togo, 'today', '07:30', 38],
            ['DEMO-INTL-BUS-02', $this->togo, 'today', '14:00', 30],
            ['DEMO-INTL-BUS-03', $this->togo, '+1 day', '07:30', 45],
            ['DEMO-INTL-BUS-04', $this->togo, '+2 days', '09:00', 24],
            ['DEMO-INTL-BUS-05', $this->ivoire, '+1 day', '05:00', 40],
            ['DEMO-INTL-BUS-06', $this->ivoire, 'today', '20:00', 35],
            ['DEMO-INTL-BUS-07', $this->ivoire, '+3 days', '05:00', 50],
        ];

        foreach ($schedule as [$registration, $line, $when, $time, $count]) {
            $departure = $this->departure($registration, $line, $when, $time);

            if ($departure->reservationSegments()->exists()) {
                continue;
            }

            for ($index = 0; $index < $count; $index++) {
                $this->passenger($departure, $line, $index);
            }
        }

        $this->command?->info(sprintf(
            'KoraOpsInternationalToursDemoSeeder : %d départs internationaux affectés à %s ; %s.',
            count($schedule),
            $this->conductor->email,
            $this->created === 0 ? 'aucun passager créé (déjà en base)' : "{$this->created} passagers créés (état : " . self::PASSENGER_STATE . ')',
        ));
    }

    // ------------------------------------------------------------------
    // Réseau
    // ------------------------------------------------------------------

    private function seedNetwork(): void
    {
        $this->togo = Line::query()->firstOrCreate(
            ['code' => self::LINE_TOGO],
            [
                'name' => 'Cotonou - Lomé (démo internationale)',
                'type' => 'internationale',
                'countries_crossed' => ['BJ', 'TG'],
                'distance_km' => 150,
                'estimated_duration_minutes' => 180,
                'status' => 'active',
            ],
        );

        $this->ivoire = Line::query()->firstOrCreate(
            ['code' => self::LINE_IVOIRE],
            [
                'name' => 'Cotonou - Abidjan (démo internationale)',
                'type' => 'internationale',
                'countries_crossed' => ['BJ', 'TG', 'GH', 'CI'],
                'distance_km' => 1100,
                'estimated_duration_minutes' => 1200,
                'status' => 'active',
            ],
        );

        $this->togoStops = [
            $this->stop($this->togo, 'DEMO-INTL-CTN', 'Cotonou', 'BJ', 1, 0),
            $this->stop($this->togo, 'DEMO-INTL-OUI', 'Ouidah', 'BJ', 2, 42),
            $this->stop($this->togo, 'DEMO-INTL-HIL', 'Hillacondji (frontière)', 'BJ', 3, 120),
            $this->stop($this->togo, 'DEMO-INTL-LFW', 'Lomé', 'TG', 4, 150),
        ];

        $this->ivoireStops = [
            $this->stop($this->ivoire, 'DEMO-INTL-CTN', 'Cotonou', 'BJ', 1, 0),
            $this->stop($this->ivoire, 'DEMO-INTL-LFW', 'Lomé', 'TG', 2, 150),
            $this->stop($this->ivoire, 'DEMO-INTL-ACC', 'Accra', 'GH', 3, 350),
            $this->stop($this->ivoire, 'DEMO-INTL-ABJ', 'Abidjan', 'CI', 4, 1100),
        ];

        $layout = [];
        foreach (range(1, self::SEAT_COUNT / 4) as $row) {
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                $layout[] = $row . $letter;
            }
        }

        $this->composition = BusComposition::query()->firstOrCreate(
            ['code' => self::COMPOSITION],
            [
                'name' => 'Coach démo internationale (56 places)',
                'seat_count' => self::SEAT_COUNT,
                'layout' => [['type' => 'standard', 'codes' => $layout]],
                'status' => 'active',
            ],
        );
    }

    private function stop(Line $line, string $physicalCode, string $city, string $country, int $order, int $km): Stop
    {
        $physical = PhysicalStop::query()->firstOrCreate(
            ['code' => $physicalCode],
            ['name' => $city, 'city' => $city, 'country' => $country, 'status' => 'active'],
        );

        return Stop::query()->firstOrCreate(
            ['line_id' => $line->id, 'physical_stop_id' => $physical->id],
            ['name' => $city, 'city' => $city, 'country' => $country, 'stop_order' => $order, 'km_from_origin' => $km, 'status' => 'active'],
        );
    }

    // ------------------------------------------------------------------
    // Départs
    // ------------------------------------------------------------------

    private function departure(string $registration, Line $line, string $when, string $time): Departure
    {
        $bus = Bus::query()->firstOrCreate(
            ['registration_number' => $registration],
            ['composition_id' => $this->composition->id, 'seat_count' => self::SEAT_COUNT, 'status' => 'active'],
        );

        // Immuable quoi qu'en dise la config de l'app : addMinutes() ne modifie jamais $scheduled.
        $scheduled = CarbonImmutable::parse(now()->modify($when)->toDateString() . ' ' . $time);
        $arrival = $scheduled->addMinutes((int) $line->estimated_duration_minutes);

        $departure = Departure::query()->where('bus_id', $bus->id)->where('line_id', $line->id)->first()
            ?? Departure::query()->create([
                'line_id' => $line->id,
                'bus_id' => $bus->id,
                'departure_date' => $scheduled->toDateString(),
                'departure_time' => $time,
                'estimated_arrival_at' => $arrival,
                'status' => 'scheduled',
            ]);

        // Recalage à chaque exécution, sans déclencher de notification « départ modifié ».
        Departure::withoutEvents(fn() => $departure->forceFill([
            'departure_date' => $scheduled->toDateString(),
            'departure_time' => $time,
            'estimated_arrival_at' => $arrival,
            'status' => 'scheduled',
            'conductor_id' => $this->conductor->id,
        ])->save());

        return $departure;
    }

    // ------------------------------------------------------------------
    // Passagers
    // ------------------------------------------------------------------

    /**
     * Prénoms et noms par nationalité (mode d'emploi : [prénoms, noms]).
     *
     * @var array<string, array{0: list<string>, 1: list<string>}>
     */
    private const array NAMES = [
        'BJ' => [
            ['Adjoa', 'Bernard', 'Chantal', 'Didier', 'Estelle', 'Fidèle', 'Grâce', 'Hervé', 'Léonie', 'Maxime'],
            ['Agbodjan', 'Behanzin', 'Chabi', 'Fassinou', 'Gbaguidi', 'Hounsou', 'Lokossou', 'Padonou', 'Sagbo', 'Zinsou'],
        ],
        'TG' => [
            ['Kodjo', 'Akossiwa', 'Yao', 'Afi', 'Komla', 'Ama', 'Koffi', 'Sena'],
            ['Amégah', 'Lawson', 'Adjovi', 'Agbéko', 'Dzogbédé', 'Kpodar', 'Ayivi', 'Tsogbé'],
        ],
        'CI' => [
            ['Kouassi', 'Aya', 'Adjoua', 'Awa', 'Serge', 'Fatou', 'Ismaël', 'Nathalie'],
            ['Koné', 'Traoré', 'Ouattara', 'Kouamé', "N'Guessan", 'Bamba', 'Coulibaly', 'Diomandé'],
        ],
        'GH' => [
            ['Kwame', 'Ama', 'Kofi', 'Abena', 'Yaw', 'Akua', 'Kwesi', 'Esi'],
            ['Mensah', 'Owusu', 'Boateng', 'Asante', 'Osei', 'Appiah', 'Addo', 'Quaye'],
        ],
        'NG' => [
            ['Chinedu', 'Ngozi', 'Emeka', 'Amaka', 'Tunde', 'Funke', 'Ibrahim', 'Aisha'],
            ['Okafor', 'Adeyemi', 'Balogun', 'Eze', 'Bello', 'Ogunleye', 'Nwosu', 'Abubakar'],
        ],
        'BF' => [
            ['Ousmane', 'Salamata', 'Issa', 'Fatimata', 'Souleymane', 'Aminata'],
            ['Ouédraogo', 'Sawadogo', 'Compaoré', 'Zongo', 'Kaboré', 'Sankara'],
        ],
    ];

    /** Répartition des nationalités par ligne (pondérée par répétition). */
    private const array NATIONALITY_MIX = [
        self::LINE_TOGO => ['BJ', 'BJ', 'BJ', 'BJ', 'TG', 'TG', 'TG', 'TG', 'GH', 'NG', 'BF'],
        self::LINE_IVOIRE => ['BJ', 'BJ', 'CI', 'CI', 'CI', 'CI', 'TG', 'GH', 'GH', 'BF', 'NG'],
    ];

    private const array DOCUMENT_TYPES = ['passport', 'biometric_card', 'national_id', 'passport'];

    /**
     * Un passager complet : réservation, paiement, segment, billet — dans l'état PASSENGER_STATE.
     */
    private function passenger(Departure $departure, Line $line, int $index): void
    {
        $stops = $line->is($this->togo) ? $this->togoStops : $this->ivoireStops;
        [$origin, $destination] = $this->journey($stops, $index);

        $distance = (float) $destination->km_from_origin - (float) $origin->km_from_origin;
        $fare = max(2000, (int) (round((1500 + $distance * 30) / 500) * 500));

        $number = ++$this->counter;
        $mix = self::NATIONALITY_MIX[$line->code];
        $nationality = $mix[$number % count($mix)];
        [$firstNames, $lastNames] = self::NAMES[$nationality];
        $documentType = self::DOCUMENT_TYPES[$number % count(self::DOCUMENT_TYPES)];

        $pending = self::PASSENGER_STATE === 'unpaid';

        $reservation = Reservation::query()->create([
            'uuid' => (string) Str::uuid(),
            'status' => $pending ? 'confirmed' : 'ticketed',
            'total_amount' => $fare,
            'currency' => 'XOF',
            'channel' => 'counter',
        ]);

        Payment::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'provider' => 'cash',
            'idempotency_key' => (string) Str::uuid(),
            'amount' => $fare,
            'currency' => 'XOF',
            'status' => $pending ? 'pending' : 'confirmed',
            'confirmed_at' => $pending ? null : now(),
        ]);

        $passenger = ReservationPassenger::query()->create([
            'uuid' => (string) Str::uuid(),
            'reservation_id' => $reservation->id,
            'status' => 'active',
            'passenger_index' => 0,
            'first_name' => $firstNames[$number % count($firstNames)],
            'last_name' => $lastNames[intdiv($number, 2) % count($lastNames)],
            'phone' => '+22997' . str_pad((string) $number, 6, '0', STR_PAD_LEFT),
            'nationality' => $nationality,
            'document_type' => $documentType,
            'document_number' => $nationality . strtoupper($documentType[0]) . str_pad((string) (9000000 + $number), 7, '0', STR_PAD_LEFT),
            'birth_date' => now()->subYears(21 + ($number % 38))->toDateString(),
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
            'status' => $pending ? 'confirmed' : 'ticketed',
        ]);

        if (! $pending) {
            Ticket::query()->create([
                'uuid' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'reservation_passenger_id' => $passenger->id,
                'qr_code' => 'QR-' . Str::random(24),
                'short_code' => 'INT' . Str::upper(Str::random(5)),
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        }

        $this->created++;
    }

    /**
     * Trajet du passager : surtout de bout en bout (Cotonou → terminus), avec
     * quelques trajets intermédiaires.
     *
     * @param  list<Stop>  $stops  Les 4 arrêts de la ligne, dans l'ordre.
     * @return array{0: Stop, 1: Stop}
     */
    private function journey(array $stops, int $index): array
    {
        $patterns = [[0, 3], [0, 3], [0, 3], [0, 2], [1, 3], [0, 1], [2, 3], [0, 3]];
        [$from, $to] = $patterns[$index % count($patterns)];

        return [$stops[$from], $stops[$to]];
    }

    private function seat(Departure $departure, int $index): Seat
    {
        $row = intdiv($index, 4) + 1;
        $letter = ['A', 'B', 'C', 'D'][$index % 4];

        return Seat::query()->firstOrCreate(
            ['bus_id' => $departure->bus_id, 'code' => $row . $letter],
            ['type' => 'standard', 'sellable' => true, 'status' => 'active'],
        );
    }
}
