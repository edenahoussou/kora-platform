// Le voyage en cours de réservation : ses tronçons, et les sièges
// retenus sur chacun.
//
// Le tunnel se passait quatre champs de main en main — départ, retour,
// sièges aller, sièges retour — qui ne savaient dire que « un trajet, ou
// deux ». Une correspondance en demande autant que de bus, et le serveur
// en accepte six.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/data/models/itinerary_model.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/journey.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';

import '../../../../support/bus_api_fixtures.dart';

void main() {
  final connection = ItineraryModel.fromJson(kConnectedItinerary).toEntity()!;
  final direct = ItineraryModel.fromJson(kItinerary).toEntity()!;

  const seatA = Seat(id: 'st-1', label: '4A', row: 4, column: 1);
  const seatB = Seat(id: 'st-2', label: '1B', row: 1, column: 2);

  test('les tronçons suivent l’ordre du voyage : aller puis retour', () {
    // C'est l'ordre de `leg_order` dans le devis et dans le blocage, et
    // celui dans lequel le voyageur choisit ses places.
    final journey = Journey(outbound: connection, inbound: direct);

    expect(journey.legs, hasLength(3));
    expect(journey.legs.first.line.origin.name, 'Porto-Novo');
    expect(journey.legs[1].line.origin.name, 'Cotonou');
    expect(journey.legs.last, direct.first);
  });

  test('le tronçon à pourvoir est le premier qui n’a pas de sièges', () {
    final journey = Journey(outbound: connection);

    expect(journey.pendingLeg, connection.legs.first);
    expect(journey.pendingLegOrder, 1);
    expect(journey.isComplete, isFalse);

    final afterFirst = journey.withSeats(const [seatA]);
    expect(afterFirst.pendingLeg, connection.legs.last);
    expect(afterFirst.pendingLegOrder, 2);

    final complete = afterFirst.withSeats(const [seatB]);
    expect(complete.pendingLeg, isNull);
    expect(complete.isComplete, isTrue);
  });

  test('chaque voyageur garde ses sièges, un par bus', () {
    // C'est ce que le blocage envoie sous `seat_assignments`, et ce que
    // le récapitulatif affiche à côté de son nom.
    final journey = Journey(
      outbound: connection,
      seatsByLeg: const [
        [seatA, seatB],
        [seatB, seatA],
      ],
    );

    expect(journey.passengerCount, 2);
    expect(journey.seatsOf(0), const [seatA, seatB]);
    expect(journey.seatsOf(1), const [seatB, seatA]);
  });

  test('un seul tronçon international suffit à l’être', () {
    // Le manifeste sera réclamé pour le voyage entier : c'est la même
    // réservation qui franchit la frontière. La règle se juge sur les
    // pays des arrêts retenus, jamais sur la ligne — Porto-Novo →
    // Cotonou reste intérieur sur une ligne qui va jusqu'à Lomé.
    expect(connection.isInternational, isFalse);
    expect(direct.isInternational, isTrue);

    final journey = Journey(outbound: connection, inbound: direct);
    expect(journey.isInternational, isTrue);
  });

  test('le prix couvre l’aller et le retour', () {
    final oneWay = Journey(outbound: connection);
    expect(oneWay.total.amount, 12000);

    final roundTrip = Journey(outbound: connection, inbound: direct);
    expect(roundTrip.total.amount, 12000 + 31600);
  });

  test('revenir sur ses choix rend tous les sièges', () {
    // Le voyageur repart du premier plan : les places d'un bus ne valent
    // pas pour l'autre, et en garder la moitié laisserait une
    // réservation bancale.
    final journey = Journey(
      outbound: connection,
      seatsByLeg: const [
        [seatA],
        [seatB],
      ],
    ).withoutSeats;

    expect(journey.seatsByLeg, isEmpty);
    expect(journey.pendingLeg, connection.legs.first);
  });
}
