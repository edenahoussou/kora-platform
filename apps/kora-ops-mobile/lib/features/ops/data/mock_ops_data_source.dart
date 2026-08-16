import 'dart:math';
import '../domain/entities/depart.dart';
import '../domain/entities/passenger.dart';
import '../domain/entities/seat.dart';
import '../domain/entities/ticket.dart';

/// Jeu de données de test pour Manifeste / Scan boarding / Descente
/// anticipée / Synchro — voir document.md § Écrans OPS mockés. Remplacé par
/// de vrais appels API au Sprint 5 (voir ticket MEZ-11).
class MockOpsDataSource {
  static final List<Depart> departs = <Depart>[
    const Depart(
      id: 'dep-001',
      route: 'Cotonou → Porto-Novo',
      date: '16/08/2026',
      heure: '07:30',
      vehiculeImmat: 'AB-1234-RB',
      totalPlaces: 45,
      placesOccupees: 38,
    ),
    const Depart(
      id: 'dep-002',
      route: 'Cotonou → Bohicon',
      date: '16/08/2026',
      heure: '09:00',
      vehiculeImmat: 'AB-5678-RB',
      totalPlaces: 45,
      placesOccupees: 21,
    ),
    const Depart(
      id: 'dep-003',
      route: 'Cotonou → Parakou',
      date: '16/08/2026',
      heure: '10:15',
      vehiculeImmat: 'AB-9012-RB',
      totalPlaces: 50,
      placesOccupees: 12,
    ),
  ];

  static final List<String> _prenoms = <String>[
    'Aïcha',
    'Kokou',
    'Fatouma',
    'Bienvenu',
    'Adjoa',
    'Séraphin',
    'Rafiatou',
    'Comlan',
    'Chimène',
    'Prudencio',
    'Nadège',
    'Basile',
    'Sandrine',
    'Wilfried',
    'Mariam',
    'Élysée',
  ];
  static final List<String> _noms = <String>[
    'Hounkpé',
    'Adjovi',
    'Dossou',
    'Amoussou',
    'Zinsou',
    'Agossou',
    'Kpossou',
    'Houénou',
    'Sègbédji',
    'Vodounou',
    'Gbaguidi',
    'Assogba',
  ];

  static List<Passenger> passengersFor(String departId) {
    final Random random = Random(departId.hashCode);
    final Depart depart = departs.firstWhere((Depart d) => d.id == departId);
    final List<String> segments = <String>[
      depart.route.split('→').first.trim(),
      ...depart.route.split('→').last.trim().split(' via '),
    ];

    return List<Passenger>.generate(depart.placesOccupees, (int index) {
      final String prenom = _prenoms[random.nextInt(_prenoms.length)];
      final String nom = _noms[random.nextInt(_noms.length)];
      final int rangee = (index ~/ 3) + 1;
      final String colonne = String.fromCharCode(65 + (index % 3));
      final BoardingStatus statut = switch (index % 7) {
        0 => BoardingStatus.embarque,
        1 => BoardingStatus.embarque,
        2 => BoardingStatus.refuse,
        3 => BoardingStatus.descenduAnticipe,
        _ => BoardingStatus.enAttente,
      };

      return Passenger(
        id: '$departId-pax-$index',
        nomComplet: '$prenom $nom',
        siege: '$rangee$colonne',
        statut: statut,
        segmentOrigine: segments.first,
        segmentDestination: segments.length > 1 ? segments.last : depart.route,
        ticketId: '$departId-ticket-$index',
        telephone:
            '+229 01 ${60 + index} ${10 + index} ${20 + index} ${30 + index}'
                .replaceAll(' ', ''),
      );
    });
  }

  static Ticket ticketFor(Passenger passenger, String departId) {
    return Ticket(
      id: passenger.ticketId,
      numero: 'KORA-${passenger.ticketId.toUpperCase()}',
      passengerId: passenger.id,
      departId: departId,
      siege: passenger.siege,
      segmentOrigine: passenger.segmentOrigine,
      segmentDestination: passenger.segmentDestination,
      qrData: passenger.ticketId,
    );
  }

  /// Sièges du véhicule pour la Descente anticipée — un siège sur trois est
  /// marqué comme libéré à un arrêt intermédiaire, pour peupler l'écran.
  static List<Seat> seatsFor(String departId) {
    final List<Passenger> passengers = passengersFor(departId);
    return passengers.asMap().entries.map((MapEntry<int, Passenger> entry) {
      final int index = entry.key;
      final Passenger passenger = entry.value;
      final bool libereBientot =
          index % 3 == 0 && passenger.statut != BoardingStatus.refuse;

      return Seat(
        numero: passenger.siege,
        statut: passenger.statut == BoardingStatus.refuse
            ? SeatStatus.libere
            : SeatStatus.occupe,
        passengerName: passenger.statut == BoardingStatus.refuse
            ? null
            : passenger.nomComplet,
        libereAPartirDe: libereBientot ? 'Bohicon' : null,
      );
    }).toList();
  }
}
