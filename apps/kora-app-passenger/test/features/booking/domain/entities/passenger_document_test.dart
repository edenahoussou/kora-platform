// Les pièces d'identité proposées après la revue Kora du 29 août 2026.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/passenger.dart';
import 'package:kora_app_passenger/features/booking/domain/entities/seat.dart';

void main() {
  const seat = Seat(id: 'uuid-4a', label: '4A', row: 4, column: 1);

  test('le CIP n’est plus proposé', () {
    // Retiré à la demande de la direction. Le serveur l'accepte encore
    // dans son `in:` ; c'est l'app qui cesse de l'offrir.
    expect(
      TravelDocumentType.values.map((type) => type.wire),
      isNot(contains('cip')),
    );
  });

  test('les trois pièces citées par la revue restent offertes', () {
    expect(
      TravelDocumentType.values.map((type) => type.wire),
      containsAll(['biometric_card', 'passport', 'consular_card']),
    );
  });

  test('un voyageur sans pièce n’a pas de document de voyage', () {
    // Le cas rendu possible par la revue : ni type ni numéro.
    const passenger = Passenger(
      firstName: 'Aïcha',
      lastName: 'HOUNKPÈ',
      phone: '+22901974218',
      seat: seat,
    );

    expect(passenger.hasTravelDocument, isFalse);
    // Et la vaccination reste une réponse à part, éteinte par défaut.
    expect(passenger.yellowFeverVaccinated, isFalse);
  });

  test('un type sans numéro ne fait pas un document', () {
    // Les deux champs étant facultatifs, l'un peut arriver sans l'autre.
    const passenger = Passenger(
      firstName: 'Kofi',
      lastName: 'MENSAH',
      phone: '+22890000000',
      seat: seat,
      documentType: TravelDocumentType.passport,
    );

    expect(passenger.hasTravelDocument, isFalse);
  });
}
