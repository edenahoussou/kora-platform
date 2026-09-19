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
use App\Modules\IdentityAccess\Data\TerminalP501Status;
use App\Modules\IdentityAccess\Data\TerminalType;
use App\Modules\IdentityAccess\Models\TerminalP501;
use App\Modules\Payments\Models\Payment;
use App\Modules\Ticketing\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Jeu de données de TEST pour le manifeste frontière (MEZ-94) — impression
 * P501 (58 mm, 3 exemplaires) et PDF A4 (mode téléphone) de l'app KORA OPS
 * Mobile.
 *
 * Lancer (autonome, ré-exécutable sans doublon) :
 *
 *   php artisan db:seed --class=KoraBorderManifestDemoSeeder
 *
 * Prérequis : KoraBusRolePermissionSeeder (rôles) et KoraDemoUserSeeder
 * (convoyeur `conductor.marc@kora.demo`, mot de passe « password », PIN
 * « 1234 »). Change de convoyeur avec BORDER_DEMO_CONDUCTOR_EMAIL.
 *
 * Terminaux créés pour ce convoyeur :
 * - `P501-FRONT-01` (P501, actif) — pour l'impression thermique ;
 * - un terminal « téléphone » actif SI BORDER_DEMO_PHONE_SERIAL est défini
 *   (numéro de série que l'app affiche sur l'écran de liaison du terminal,
 *   en mode téléphone) — sinon le handshake du téléphone est refusé.
 *
 * Six départs, tous affectés au convoyeur (chacun sur son propre bus) :
 *
 *  A  Départ international COMPLET (à venir)       → impression OK
 *  B  Départ international INCOMPLET (à venir)     → impression REFUSÉE
 *  C  Départ NATIONAL                              → pas de bouton « frontière »
 *  D  Départ international VIDE                    → refusé (aucun passager)
 *  E  Départ international EN ROUTE (embarqués)    → cas réel à la frontière
 *  F  Départ international GROS MANIFESTE (52 pax) → 58 mm sur plusieurs mètres
 *
 * Les dates sont recalées sur aujourd'hui à chaque exécution : relancer le
 * seeder « rafraîchit » les départs sans recréer les passagers déjà en place.
 */
class KoraBorderManifestDemoSeeder extends Seeder
{
    private const string LINE_INTERNATIONAL = 'DEMO-FRT-INT';

    private const string LINE_NATIONAL = 'DEMO-FRT-NAT';

    private const string COMPOSITION = 'DEMO-FRT-COMP';

    private const int SEAT_COUNT = 56;

    private User $conductor;

    private Line $international;

    private Line $national;

    /** @var array{cotonou: Stop, aneho: Stop, lome: Stop, porto: Stop} */
    private array $stops;

    private ?Stop $nationalOrigin = null;

    private BusComposition $composition;

    /** Compteur servant à fabriquer des numéros de téléphone distincts. */
    private int $ticketCounter = 0;

    public function run(): void
    {
        $email = (string) env('BORDER_DEMO_CONDUCTOR_EMAIL', 'conductor.marc@kora.demo');
        $conductor = User::query()->where('email', $email)->first();

        if ($conductor === null) {
            throw new RuntimeException(
                "KoraBorderManifestDemoSeeder requiert KoraDemoUserSeeder ({$email} introuvable).",
            );
        }

        $this->conductor = $conductor;

        $this->seedNetwork();
        $this->seedTerminals();

        $this->seedDepartureA();
        $this->seedDepartureB();
        $this->seedDepartureC();
        $this->seedDepartureD();
        $this->seedDepartureE();
        $this->seedDepartureF();

        $this->command->info(
            "KoraBorderManifestDemoSeeder : 6 départs de test affectés à {$this->conductor->email} "
            .'(connexion : mot de passe « password », PIN « 1234 »).',
        );
    }

    // ------------------------------------------------------------------
    // Réseau, terminaux
    // ------------------------------------------------------------------

    private function seedNetwork(): void
    {
        $this->international = Line::query()->firstOrCreate(
            ['code' => self::LINE_INTERNATIONAL],
            [
                'name' => 'Cotonou - Lomé (démo frontière)',
                'type' => 'internationale',
                'countries_crossed' => ['BJ', 'TG'],
                'distance_km' => 150,
                'estimated_duration_minutes' => 180,
                'status' => 'active',
            ],
        );

        $this->national = Line::query()->firstOrCreate(
            ['code' => self::LINE_NATIONAL],
            [
                'name' => 'Cotonou - Porto-Novo (démo frontière, national)',
                'type' => 'nationale',
                'countries_crossed' => ['BJ'],
                'distance_km' => 35,
                'estimated_duration_minutes' => 60,
                'status' => 'active',
            ],
        );

        $cotonouIntl = $this->stop($this->international, 'DEMO-FRT-CTN', 'Cotonou', 'BJ', 1, 0);
        $aneho = $this->stop($this->international, 'DEMO-FRT-ANE', 'Aného', 'TG', 2, 110);
        $lome = $this->stop($this->international, 'DEMO-FRT-LFW', 'Lomé', 'TG', 3, 150);
        $cotonouNat = $this->stop($this->national, 'DEMO-FRT-CTN', 'Cotonou', 'BJ', 1, 0);
        $porto = $this->stop($this->national, 'DEMO-FRT-PNV', 'Porto-Novo', 'BJ', 2, 35);

        $this->stops = [
            'cotonou' => $cotonouIntl,
            'aneho' => $aneho,
            'lome' => $lome,
            'porto' => $porto,
        ];
        // L'origine de la ligne nationale est un autre enregistrement Stop
        // (même arrêt physique, autre ligne) : gardée à part.
        $this->nationalOrigin = $cotonouNat;

        $layout = [];
        foreach (range(1, self::SEAT_COUNT / 4) as $row) {
            foreach (['A', 'B', 'C', 'D'] as $letter) {
                $layout[] = $row.$letter;
            }
        }

        $this->composition = BusComposition::query()->firstOrCreate(
            ['code' => self::COMPOSITION],
            [
                'name' => 'Coach démo frontière (56 places)',
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

    private function seedTerminals(): void
    {
        $this->terminal('P501-FRONT-01', TerminalType::P501);

        $phoneSerial = trim((string) env('BORDER_DEMO_PHONE_SERIAL', ''));
        if ($phoneSerial !== '') {
            $this->terminal(strtoupper($phoneSerial), TerminalType::Phone);
        }
    }

    private function terminal(string $serial, TerminalType $type): void
    {
        $terminal = TerminalP501::query()->firstOrCreate(
            ['serial_number' => $serial],
            [
                'uuid' => (string) Str::uuid(),
                'type' => $type,
                'assigned_user_id' => $this->conductor->id,
                'status' => TerminalP501Status::Active,
                'last_handshake_at' => now(),
            ],
        );

        // Ré-exécution : remet le terminal de test dans un état utilisable.
        if ($terminal->status !== TerminalP501Status::Active || $terminal->assigned_user_id !== $this->conductor->id) {
            $terminal->forceFill([
                'status' => TerminalP501Status::Active,
                'assigned_user_id' => $this->conductor->id,
            ])->save();
        }
    }

    // ------------------------------------------------------------------
    // Les six départs
    // ------------------------------------------------------------------

    /** A — complet : accents, noms très longs, n° de document sans type de pièce… */
    private function seedDepartureA(): void
    {
        $departure = $this->departure('BJ-FRT-01', $this->international, '+1 day', '07:30', 'scheduled');
        if ($this->alreadyPopulated($departure)) {
            return;
        }

        [$origin, $aneho, $lome] = [$this->stops['cotonou'], $this->stops['aneho'], $this->stops['lome']];

        $people = [
            ['Awa', 'Traoré', 'BJ', 'passport', 'PP123456', $lome],
            ['Koffi', 'Mensah', 'TG', 'national_id', 'TG-0098765', $lome],
            ['Aïcha', 'Hounkpé', 'BJ', 'biometric_card', 'BIO-2026-778899', $aneho],
            ['Chukwuemeka', 'Okonkwo-Adeyemi', 'NG', 'passport', 'A12345678', $lome],
            ['Abdoul-Karim Mahamadou Ousmane', 'Bakary Salifou Ouedraogo', 'BF', 'passport', 'B0012345', $lome], // nom à replier sur 58 mm
            ['Ama', 'Boateng', 'GH', 'passport', 'G0987654', $lome],
            ['Fatou', 'Diop', 'SN', 'consular_card', 'CI-SN-2026-0000123456789', $lome], // n° de document très long
            ['Yao', "N'Guessan", 'CI', null, 'NO-TYPE-42', $lome], // n° de document sans type de pièce
        ];

        foreach ($people as $i => [$first, $last, $nat, $docType, $docNumber, $destination]) {
            $this->passenger($departure, $origin, $destination, $this->seat($departure, $i), [
                'first_name' => $first, 'last_name' => $last, 'nationality' => $nat,
                'document_type' => $docType, 'document_number' => $docNumber,
            ]);
        }
    }

    /** B — incomplet : l'impression doit être REFUSÉE et nommer les passagers à corriger. */
    private function seedDepartureB(): void
    {
        $departure = $this->departure('BJ-FRT-02', $this->international, '+1 day', '09:00', 'scheduled');
        if ($this->alreadyPopulated($departure)) {
            return;
        }

        [$origin, $lome] = [$this->stops['cotonou'], $this->stops['lome']];

        // 3 passagers complets.
        foreach ([['Adjoa', 'Koudjo', 'BJ', 'PP200001'], ['Bertin', 'Houngbo', 'BJ', 'PP200002'], ['Chantal', 'Dossou', 'TG', 'PP200003']] as $i => [$f, $l, $n, $d]) {
            $this->passenger($departure, $origin, $lome, $this->seat($departure, $i), [
                'first_name' => $f, 'last_name' => $l, 'nationality' => $n, 'document_number' => $d,
            ]);
        }

        // 3 passagers incomplets — ce sont eux qui bloquent l'impression.
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 3), [
            'first_name' => 'Didier', 'last_name' => 'Sans-Nationalité', 'nationality' => null, 'document_number' => 'PP200004',
        ]);
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 4), [
            'first_name' => 'Eudoxie', 'last_name' => 'Sans-Document', 'nationality' => 'BJ', 'document_type' => null, 'document_number' => null,
        ]);
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 5), [
            'first_name' => 'Firmin', 'last_name' => 'Sans-Rien', 'nationality' => null, 'document_type' => null, 'document_number' => null,
        ]);

        // Incomplet MAIS annulé : ne doit PAS bloquer (exclu du manifeste).
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 6), [
            'first_name' => 'Gilbert', 'last_name' => 'Annulé-Incomplet', 'nationality' => null, 'document_number' => null,
        ], segmentStatus: 'cancelled');

        // Incomplet MAIS paiement non confirmé : absent du manifeste.
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 7), [
            'first_name' => 'Honorine', 'last_name' => 'Impayée-Incomplète', 'nationality' => null, 'document_number' => null,
        ], segmentStatus: 'confirmed', paid: false, issueTicket: false);
    }

    /** C — national : le manifeste frontière ne doit pas être proposé. */
    private function seedDepartureC(): void
    {
        $departure = $this->departure('BJ-FRT-03', $this->national, '+1 day', '11:00', 'scheduled');
        if ($this->alreadyPopulated($departure)) {
            return;
        }

        foreach (['Ida', 'Jules', 'Karim', 'Léa'] as $i => $first) {
            // Départ national : ni nationalité ni document exigés.
            $this->passenger($departure, $this->nationalOrigin, $this->stops['porto'], $this->seat($departure, $i), [
                'first_name' => $first, 'last_name' => 'National', 'nationality' => null, 'document_type' => null, 'document_number' => null,
            ]);
        }
    }

    /** D — international mais sans aucun passager. */
    private function seedDepartureD(): void
    {
        $this->departure('BJ-FRT-04', $this->international, '+2 days', '07:30', 'scheduled');
    }

    /** E — déjà en route : le cas réel à la frontière (embarqués, un absent, un annulé). */
    private function seedDepartureE(): void
    {
        $departure = $this->departure('BJ-FRT-05', $this->international, 'today', '06:00', 'departed');
        if ($this->alreadyPopulated($departure)) {
            return;
        }

        [$origin, $lome] = [$this->stops['cotonou'], $this->stops['lome']];

        $boarded = [
            ['Mariam', 'Sow', 'SN', 'PP300001'], ['Nestor', 'Agbessi', 'BJ', 'PP300002'], ['Olivia', 'Mensah', 'GH', 'PP300003'],
            ['Pascal', 'Zannou', 'BJ', 'PP300004'], ['Rita', 'Kpakpo', 'TG', 'PP300005'], ['Serge', 'Akpavi', 'BJ', 'PP300006'],
            ['Tania', 'Dossou', 'NE', 'PP300007'], ['Ulrich', 'Sossou', 'BJ', 'PP300008'], ['Viviane', 'Bello', 'NG', 'PP300009'],
            ['Wilfried', 'Hounkpatin', 'BJ', 'PP300010'],
        ];

        foreach ($boarded as $i => [$f, $l, $n, $d]) {
            $this->passenger($departure, $origin, $lome, $this->seat($departure, $i), [
                'first_name' => $f, 'last_name' => $l, 'nationality' => $n, 'document_number' => $d,
            ], segmentStatus: 'boarded');
        }

        // Billet valide mais jamais monté : "absent" → exclu du manifeste.
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 10), [
            'first_name' => 'Xavier', 'last_name' => 'Absent', 'nationality' => 'BJ', 'document_number' => 'PP300011',
        ], segmentStatus: 'ticketed');

        // Annulé : exclu.
        $this->passenger($departure, $origin, $lome, $this->seat($departure, 11), [
            'first_name' => 'Yvonne', 'last_name' => 'Annulée', 'nationality' => 'BJ', 'document_number' => 'PP300012',
        ], segmentStatus: 'cancelled');
    }

    /** F — gros manifeste (52 passagers complets) : longueur du ticket 58 mm. */
    private function seedDepartureF(): void
    {
        $departure = $this->departure('BJ-FRT-06', $this->international, '+2 days', '14:00', 'scheduled');
        if ($this->alreadyPopulated($departure)) {
            return;
        }

        $firstNames = ['Adama', 'Béatrice', 'Cyrille', 'Delphine', 'Emmanuel', 'Flora', 'Gaston', 'Hélène', 'Isidore', 'Josiane', 'Kévin', 'Laurence', 'Modeste'];
        $lastNames = ['Agossou', 'Bakayoko', 'Coulibaly', 'Dansou', 'Eklou', 'Fagbohoun', 'Gnonlonfoun', 'Houessou', 'Idrissou', 'Johnson'];
        $nationalities = ['BJ', 'TG', 'NG', 'GH', 'CI', 'BF', 'NE', 'SN', 'ML'];

        [$origin, $aneho, $lome] = [$this->stops['cotonou'], $this->stops['aneho'], $this->stops['lome']];

        for ($i = 0; $i < 52; $i++) {
            $this->passenger($departure, $origin, $i % 5 === 0 ? $aneho : $lome, $this->seat($departure, $i), [
                'first_name' => $firstNames[$i % count($firstNames)],
                'last_name' => $lastNames[$i % count($lastNames)].' '.($i + 1),
                'nationality' => $nationalities[$i % count($nationalities)],
                'document_number' => sprintf('PP%06d', 400000 + $i),
            ]);
        }
    }

    // ------------------------------------------------------------------
    // Briques
    // ------------------------------------------------------------------

    /** Un bus dédié par départ (évite tout conflit d'affectation du bus). */
    private function departure(string $registration, Line $line, string $when, string $time, string $status): Departure
    {
        $bus = Bus::query()->firstOrCreate(
            ['registration_number' => $registration],
            ['composition_id' => $this->composition->id, 'seat_count' => self::SEAT_COUNT, 'status' => 'active'],
        );

        $date = now()->modify($when)->toDateString();

        $departure = Departure::query()->where('bus_id', $bus->id)->where('line_id', $line->id)->first()
            ?? Departure::query()->create([
                'line_id' => $line->id,
                'bus_id' => $bus->id,
                'departure_date' => $date,
                'departure_time' => $time,
                'estimated_arrival_at' => now()->modify($when)->addHours(3),
                'status' => $status,
            ]);

        // Recale à chaque exécution : date du jour, statut voulu, convoyeur.
        $departure->forceFill([
            'departure_date' => $date,
            'departure_time' => $time,
            'status' => $status,
            'conductor_id' => $this->conductor->id,
        ])->save();

        return $departure;
    }

    private function alreadyPopulated(Departure $departure): bool
    {
        return $departure->reservationSegments()->exists();
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

    /**
     * Un passager, sa réservation, son paiement, son segment et son billet.
     * Par défaut : passager complet, payé, billet émis.
     *
     * @param  array<string, mixed>  $person  first_name, last_name, nationality, document_type, document_number
     */
    private function passenger(
        Departure $departure,
        Stop $origin,
        Stop $destination,
        Seat $seat,
        array $person,
        string $segmentStatus = 'ticketed',
        bool $paid = true,
        bool $issueTicket = true,
    ): void {
        $reservation = Reservation::query()->create([
            'status' => $paid ? 'ticketed' : 'confirmed',
            'total_amount' => 10000,
            'currency' => 'XOF',
            'channel' => 'counter',
        ]);

        if ($paid) {
            Payment::query()->create([
                'reservation_id' => $reservation->id,
                'provider' => 'cash',
                'idempotency_key' => (string) Str::uuid(),
                'amount' => 10000,
                'currency' => 'XOF',
                'status' => 'confirmed',
                'confirmed_at' => now(),
            ]);
        }

        $passenger = ReservationPassenger::query()->create(array_merge([
            'reservation_id' => $reservation->id,
            'status' => 'active',
            'passenger_index' => 0,
            'phone' => '+22997'.str_pad((string) (++$this->ticketCounter), 6, '0', STR_PAD_LEFT),
            'document_type' => 'passport',
            'birth_date' => '1990-01-01',
            'yellow_fever_vaccinated' => true,
        ], $person));

        ReservationSegment::query()->create([
            'reservation_id' => $reservation->id,
            'reservation_passenger_id' => $passenger->id,
            'leg_order' => 1,
            'departure_id' => $departure->id,
            'seat_id' => $seat->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'origin_stop_order' => $origin->stop_order,
            'destination_stop_order' => $destination->stop_order,
            'status' => $segmentStatus,
        ]);

        if ($issueTicket) {
            Ticket::query()->create([
                'uuid' => (string) Str::uuid(),
                'reservation_id' => $reservation->id,
                'reservation_passenger_id' => $passenger->id,
                'qr_code' => 'QR-'.Str::random(24),
                'short_code' => 'FRT'.Str::upper(Str::random(5)),
                'status' => 'issued',
                'issued_at' => now(),
            ]);
        }
    }
}
