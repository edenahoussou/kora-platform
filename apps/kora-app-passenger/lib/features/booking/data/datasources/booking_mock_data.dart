/// Jeu de données de test du parcours Bus (Sprint 1).
///
/// **Ce fichier n'a pas vocation à survivre au Sprint 2** : il sera
/// remplacé par les réponses de l'API KORA. Il est isolé ici, séparé de
/// [BookingMockDataSource], pour qu'on puisse le supprimer d'un bloc le
/// jour venu sans toucher à la moindre ligne de logique.
///
/// Les valeurs (villes, horaires, prix) reprennent celles de la maquette
/// `docs/maquettes/kora-app-passenger` quand elle en propose.
library;

import 'dart:math';

import '../../domain/entities/amenity.dart';
import '../../domain/entities/city.dart';
import '../../domain/entities/departure.dart';
import '../../domain/entities/line.dart';
import '../../domain/entities/price.dart';
import '../../domain/entities/search_criteria.dart';
import '../../domain/entities/seat.dart';
import '../../domain/entities/ticket.dart';
import '../../domain/entities/trip_segment.dart';
import '../../domain/entities/upcoming_trip.dart';

/// Villes desservies, dans l'ordre d'affichage du sélecteur.
const List<City> kMockCities = [
  City(id: 'ctn', name: 'Cotonou', code: 'CTN', station: 'St Michel'),
  City(id: 'clv', name: 'Abomey-Calavi', code: 'CLV', station: 'Tokan'),
  City(id: 'pnv', name: 'Porto-Novo', code: 'PNV', station: 'Ouando'),
  City(id: 'bhc', name: 'Bohicon', code: 'BHC', station: 'Gare centrale'),
  City(id: 'sav', name: 'Savè', code: 'SAV'),
  City(id: 'pku', name: 'Parakou', code: 'PKU', station: 'Zongo'),
  City(id: 'djg', name: 'Djougou', code: 'DJG'),
  City(id: 'nat', name: 'Natitingou', code: 'NAT'),
  City(id: 'knd', name: 'Kandi', code: 'KND'),
  City(id: 'mlv', name: 'Malanville', code: 'MLV'),
];

City _city(String id) => kMockCities.firstWhere((city) => city.id == id);

/// Villes proposées en « Recherches récentes » sur l'écran de sélection.
///
/// Figées ici faute d'historique : au Sprint 2 elles viendront des
/// dernières recherches réellement effectuées par l'utilisateur.
List<City> mockRecentCities() => [_city('pnv'), _city('pku')];

/// Une liaison du réseau, décrite par ses arrêts et leur distance cumulée
/// depuis l'origine.
///
/// Tout le reste — tronçons, heures de passage, prix par portion — se
/// calcule à partir de ces deux informations (voir [_lineFrom]) : c'est la
/// seule façon de garantir que les étapes affichées restent cohérentes
/// avec la durée et le prix total de la ligne.
class _LineSpec {
  const _LineSpec({
    required this.id,
    required this.duration,
    required this.waypoints,
  });

  final String id;
  final Duration duration;

  /// De l'origine au terminus : `(identifiant de ville, km depuis
  /// l'origine)`.
  final List<(String, int)> waypoints;
}

const List<_LineSpec> _lineSpecs = [
  // L'axe nord-sud principal, celui que met en avant la maquette.
  _LineSpec(
    id: 'ctn-mlv',
    duration: Duration(hours: 9, minutes: 45),
    waypoints: [
      ('ctn', 0),
      ('bhc', 128),
      ('pku', 415),
      ('knd', 620),
      ('mlv', 733),
    ],
  ),
  _LineSpec(
    id: 'ctn-pku',
    duration: Duration(hours: 6, minutes: 30),
    waypoints: [('ctn', 0), ('bhc', 128), ('sav', 255), ('pku', 415)],
  ),
  _LineSpec(
    id: 'ctn-pnv',
    duration: Duration(minutes: 45),
    waypoints: [('ctn', 0), ('pnv', 35)],
  ),
  _LineSpec(
    id: 'ctn-nat',
    duration: Duration(hours: 8, minutes: 20),
    waypoints: [('ctn', 0), ('bhc', 128), ('djg', 445), ('nat', 620)],
  ),
  _LineSpec(
    id: 'bhc-pku',
    duration: Duration(hours: 3, minutes: 15),
    waypoints: [('bhc', 0), ('sav', 127), ('pku', 285)],
  ),
  _LineSpec(
    id: 'clv-pku',
    duration: Duration(hours: 6, minutes: 45),
    waypoints: [('clv', 0), ('bhc', 138), ('pku', 425)],
  ),
];

/// Les liaisons desservies par le réseau.
///
/// **Toutes les paires de villes ne sont pas reliées en direct** : c'est
/// vrai d'un vrai réseau d'autocars, et c'est ce qui rend l'état « aucun
/// départ » atteignable en démonstration (essayer Savè → Djougou).
final List<Line> kMockLines = [for (final spec in _lineSpecs) _lineFrom(spec)];

/// Ligne Cotonou → Malanville, utilisée par le prochain voyage de
/// l'accueil.
final Line kMockCotonouMalanville = kMockLines.first;

/// Construit une ligne complète (tronçons compris) à partir de sa
/// description.
///
/// Le temps de parcours de chaque tronçon est proportionnel à sa distance,
/// et son prix suit le même barème au kilomètre que le trajet entier (voir
/// [_mockFareFor]). Les heures de passage aux étapes en découlent.
Line _lineFrom(_LineSpec spec, {bool reversed = false}) {
  final total = spec.waypoints.last.$2;

  // Parcourue à l'envers, la ligne garde ses arrêts mais leurs distances se
  // comptent depuis l'autre bout.
  final waypoints = reversed
      ? [for (final (id, km) in spec.waypoints.reversed) (id, total - km)]
      : spec.waypoints;

  Duration offsetAt(int km) => Duration(
    milliseconds: (spec.duration.inMilliseconds * km / total).round(),
  );

  final segments = [
    for (var i = 0; i < waypoints.length - 1; i++)
      TripSegment(
        origin: _city(waypoints[i].$1),
        destination: _city(waypoints[i + 1].$1),
        departureOffset: offsetAt(waypoints[i].$2),
        arrivalOffset: offsetAt(waypoints[i + 1].$2),
        distanceKm: waypoints[i + 1].$2 - waypoints[i].$2,
        price: _mockFareFor(waypoints[i + 1].$2 - waypoints[i].$2),
      ),
  ];

  return Line(
    id: reversed ? '${spec.id}-inv' : spec.id,
    origin: _city(waypoints.first.$1),
    destination: _city(waypoints.last.$1),
    duration: spec.duration,
    distanceKm: total,
    segments: segments,
  );
}

/// La ligne reliant [origin] à [destination], dans un sens ou dans
/// l'autre, ou `null` si la liaison n'est pas desservie.
Line? mockLineFor(City origin, City destination) {
  for (final spec in _lineSpecs) {
    final first = _city(spec.waypoints.first.$1);
    final last = _city(spec.waypoints.last.$1);

    if (first == origin && last == destination) return _lineFrom(spec);
    if (first == destination && last == origin) {
      return _lineFrom(spec, reversed: true);
    }
  }
  return null;
}

/// Départs proposés pour une recherche.
///
/// Reproduit la grille de la maquette (`js/app.js`, écran `results`) :
/// trois départs vendables et un complet. Les heures d'arrivée ne sont pas
/// écrites en dur — elles découlent de la durée de la ligne (voir
/// [Departure.arrivesAt]), ce qui redonne exactement les valeurs de la
/// maquette sur Cotonou → Malanville (07:30 → 17:15, 20:00 → 05:45...).
List<Departure> mockDeparturesFor(SearchCriteria criteria) {
  final line = mockLineFor(criteria.origin, criteria.destination);
  if (line == null) return const [];

  final base = _mockFareFor(line.distanceKm);
  final day = criteria.date;

  Departure departure({
    required String id,
    required int hour,
    required int minute,
    required BusComfort comfort,
    required int extra,
    required int seatsAvailable,
  }) {
    return Departure(
      id: '${line.id}-$id',
      line: line,
      departsAt: DateTime(day.year, day.month, day.day, hour, minute),
      comfort: comfort,
      price: Price(base.amount + extra),
      seatsTotal: 55,
      seatsAvailable: seatsAvailable,
      amenities: const [
        Amenity(code: 'wifi'),
        Amenity(code: 'air_conditioning'),
        Amenity(code: 'usb_charging'),
      ],
    );
  }

  return [
    departure(
      id: '0730',
      hour: 7,
      minute: 30,
      comfort: BusComfort.confort,
      extra: 0,
      seatsAvailable: 12,
    ),
    departure(
      id: '0900',
      hour: 9,
      minute: 0,
      comfort: BusComfort.classique,
      extra: 0,
      seatsAvailable: 5,
    ),
    departure(
      id: '2000',
      hour: 20,
      minute: 0,
      comfort: BusComfort.vip,
      // Le départ de nuit est plus cher : 12 500 → 14 500 dans la maquette.
      extra: 2000,
      seatsAvailable: 22,
    ),
    departure(
      id: '2230',
      hour: 22,
      minute: 30,
      comfort: BusComfort.confort,
      extra: 2000,
      seatsAvailable: 0,
    ),
  ];
}

/// Tarif d'une distance, arrondi aux 500 FCFA — un prix fixe donnerait le
/// même montant pour Cotonou → Porto-Novo (35 km) et Cotonou → Malanville
/// (733 km).
///
/// Le coefficient est calé sur la maquette : 733 km × 17 ≈ 12 461, soit
/// 12 500 FCFA après arrondi, le prix qu'elle affiche. Le même barème sert
/// aux tronçons, ce qui explique que la somme des portions ne retombe pas
/// exactement sur le prix du trajet complet — comme dans la vraie vie, un
/// billet direct n'est pas la somme des billets intermédiaires.
Price _mockFareFor(int distanceKm) {
  final raw = distanceKm * 17;
  return Price((raw / 500).round() * 500);
}

/// Plan de sièges d'un départ.
///
/// Le bus fait 14 rangées de 4 places (A, B, allée, C, D) moins la
/// dernière — soit les 55 places annoncées par la maquette.
///
/// **Le nombre de sièges libres correspond exactement à
/// `departure.seatsAvailable`** : un plan généré indépendamment
/// afficherait 42 places libres là où la carte de résultat en annonce 12.
/// Les places prises sont réparties de façon pseudo-aléatoire mais
/// **déterministe** (graine dérivée de l'identifiant du départ), pour que
/// le même départ redonne toujours le même plan.
List<Seat> mockSeatsFor(Departure departure) {
  const rows = 14;
  const columns = ['A', 'B', 'C', 'D'];

  final seats = <Seat>[];
  for (var row = 1; row <= rows; row++) {
    for (var column = 0; column < columns.length; column++) {
      // La maquette retire la dernière place du fond (`if (id === "14D")`).
      if (row == rows && column == columns.length - 1) continue;

      seats.add(
        Seat(
          id: '${departure.id}-$row${columns[column]}',
          label: '$row${columns[column]}',
          row: row,
          column: column,
          // Première rangée condamnée, comme dans la maquette.
          status: row == 1 ? SeatStatus.blocked : SeatStatus.free,
        ),
      );
    }
  }

  final assignable = [
    for (var i = 0; i < seats.length; i++)
      if (seats[i].status == SeatStatus.free) i,
  ]..shuffle(Random(_seedOf(departure.id)));

  // Ce qui n'est pas laissé libre est vendu : quelques places retenues le
  // temps d'un paiement, quelques voyageurs déjà à bord, le reste payé.
  final toFill = (assignable.length - departure.seatsAvailable).clamp(
    0,
    assignable.length,
  );

  for (var i = 0; i < toFill; i++) {
    final status = switch (i % 8) {
      0 => SeatStatus.held,
      1 || 2 => SeatStatus.embarked,
      _ => SeatStatus.occupied,
    };
    final index = assignable[i];
    seats[index] = Seat(
      id: seats[index].id,
      label: seats[index].label,
      row: seats[index].row,
      column: seats[index].column,
      status: status,
    );
  }

  return seats;
}

/// Graine stable pour un identifiant donné — `String.hashCode` ne garantit
/// pas la même valeur d'une exécution à l'autre.
int _seedOf(String id) =>
    id.codeUnits.fold(0, (sum, unit) => (sum * 31 + unit) % 0x7fffffff);

/// Prochain voyage réservé affiché sur l'accueil.
///
/// Construit relativement à [now] (et non figé au 4 août 2026 comme la
/// maquette) pour que la carte reste crédible quelle que soit la date à
/// laquelle on lance l'app : départ dans 7 jours, à 07:30.
UpcomingTrip mockUpcomingTrip(DateTime now) {
  final departureDay = DateTime(now.year, now.month, now.day + 7, 7, 30);

  return UpcomingTrip(
    departure: Departure(
      id: 'dep-ctn-mlv-0730',
      line: kMockCotonouMalanville,
      departsAt: departureDay,
      comfort: BusComfort.confort,
      price: const Price(12500),
      seatsTotal: 55,
      seatsAvailable: 12,
      amenities: const [
        Amenity(code: 'wifi'),
        Amenity(code: 'air_conditioning'),
        Amenity(code: 'usb_charging'),
      ],
    ),
    // Siège déjà vendu — c'est justement celui du voyageur.
    seat: const Seat(
      id: 'seat-4a',
      label: '4A',
      row: 4,
      column: 0,
      status: SeatStatus.occupied,
    ),
  );
}

/// Billets du voyageur, tels que les liste l'onglet « Mes voyages ».
///
/// Construits relativement à [now], comme [mockUpcomingTrip] : un billet
/// « à venir » figé au 4 août 2026 deviendrait un billet passé au bout de
/// quelques jours, et l'écran perdrait son onglet le plus important.
///
/// Le jeu couvre les trois statuts, parce que c'est ce que l'écran doit
/// savoir afficher : un billet confirmé (le même départ que la carte de
/// l'accueil, pour que les deux écrans racontent la même histoire), trois
/// voyages terminés et une annulation.
List<Ticket> mockTickets(DateTime now) {
  final today = DateTime(now.year, now.month, now.day);

  Departure departure({
    required String id,
    required Line line,
    required DateTime departsAt,
    int price = 12500,
    int seatsAvailable = 12,
  }) {
    return Departure(
      id: id,
      line: line,
      departsAt: departsAt,
      comfort: BusComfort.confort,
      price: Price(price),
      seatsTotal: 55,
      seatsAvailable: seatsAvailable,
      amenities: const [
        Amenity(code: 'wifi'),
        Amenity(code: 'air_conditioning'),
        Amenity(code: 'usb_charging'),
      ],
    );
  }

  // Le siège du voyageur : vendu, forcément — c'est le sien.
  Seat seat(String label, int row) => Seat(
    id: 'seat-${label.toLowerCase()}',
    label: label,
    row: row,
    column: 0,
    status: SeatStatus.occupied,
  );

  return [
    Ticket(
      reference: 'KE-20260804-78342',
      departure: departure(
        id: 'dep-ctn-mlv-0730',
        line: kMockCotonouMalanville,
        departsAt: today
            .add(const Duration(days: 7))
            .add(const Duration(hours: 7, minutes: 30)),
      ),
      seat: seat('4A', 4),
      status: TicketStatus.confirmed,
    ),
    Ticket(
      reference: 'KE-20260712-51108',
      departure: departure(
        id: 'dep-ctn-par-0900',
        line: kMockLines[1], // Cotonou → Parakou
        departsAt: today
            .subtract(const Duration(days: 12))
            .add(const Duration(hours: 9)),
        price: 8000,
      ),
      seat: seat('7B', 7),
      status: TicketStatus.completed,
    ),
    Ticket(
      reference: 'KE-20260628-33920',
      departure: departure(
        id: 'dep-bhc-pku-1400',
        line: kMockLines[4], // Bohicon → Parakou
        departsAt: today
            .subtract(const Duration(days: 26))
            .add(const Duration(hours: 14)),
        price: 4000,
      ),
      seat: seat('2C', 2),
      status: TicketStatus.completed,
    ),
    Ticket(
      reference: 'KE-20260605-19477',
      departure: departure(
        id: 'dep-ctn-pnv-1730',
        line: kMockLines[2], // Cotonou → Porto-Novo
        departsAt: today
            .subtract(const Duration(days: 49))
            .add(const Duration(hours: 17, minutes: 30)),
        price: 2000,
      ),
      seat: seat('5D', 5),
      status: TicketStatus.completed,
    ),
    Ticket(
      reference: 'KE-20260715-64201',
      departure: departure(
        id: 'dep-ctn-nat-2000',
        line: kMockLines[3], // Cotonou → Natitingou
        departsAt: today
            .subtract(const Duration(days: 9))
            .add(const Duration(hours: 20)),
        price: 13500,
      ),
      seat: seat('9A', 9),
      status: TicketStatus.cancelled,
    ),
  ];
}
