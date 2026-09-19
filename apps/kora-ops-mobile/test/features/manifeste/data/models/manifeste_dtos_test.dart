import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/manifeste/data/models/manifeste_dtos.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';

void main() {
  Map<String, dynamic> row({
    Object? nationality = 'BJ',
    Object? document = const <String, dynamic>{
      'type': 'passport',
      'number': 'PP123456',
    },
    bool withBorderFields = true,
  }) =>
      <String, dynamic>{
        'ticketUuid': 'ticket-1',
        'name': 'Awa Traoré',
        'seatCode': '1A',
        'shortCode': 'AB12CD',
        'segmentStatus': 'paid',
        'originStop': <String, dynamic>{'uuid': 'o', 'name': 'Cotonou'},
        'destinationStop': <String, dynamic>{'uuid': 'd', 'name': 'Lomé'},
        if (withBorderFields) ...<String, dynamic>{
          'document': document,
          'nationality': nationality,
        },
      };

  group('ManifesteDtos.passenger — champs frontière (MEZ-94)', () {
    test('lit la nationalité et le n° de document', () {
      final Passenger? passenger = ManifesteDtos.passenger(row());

      expect(passenger?.nationalite, 'BJ');
      expect(passenger?.documentNumero, 'PP123456');
      expect(passenger?.nomComplet, 'Awa Traoré');
      expect(passenger?.segmentDestination, 'Lomé');
    });

    test('départ national : champs absents → null', () {
      final Passenger? passenger =
          ManifesteDtos.passenger(row(withBorderFields: false));

      expect(passenger?.nationalite, isNull);
      expect(passenger?.documentNumero, isNull);
    });

    test('valeurs vides ou blanches → null, jamais « renseigné »', () {
      final Passenger? passenger = ManifesteDtos.passenger(row(
        nationality: '   ',
        document: const <String, dynamic>{'type': 'passport', 'number': ''},
      ));

      expect(passenger?.nationalite, isNull);
      expect(passenger?.documentNumero, isNull);
    });

    test('document null ou de type inattendu → n° de document null', () {
      expect(ManifesteDtos.passenger(row(document: null))?.documentNumero,
          isNull);
      expect(
          ManifesteDtos.passenger(row(document: 'PP123456'))?.documentNumero,
          isNull);
      expect(
          ManifesteDtos.passenger(row(
                  document: const <String, dynamic>{'type': 'passport'}))
              ?.documentNumero,
          isNull);
    });

    test('n° de document sans type de pièce : conservé', () {
      final Passenger? passenger = ManifesteDtos.passenger(row(
        document: const <String, dynamic>{'type': null, 'number': 'NO-TYPE-42'},
      ));

      expect(passenger?.documentNumero, 'NO-TYPE-42');
    });

    test('nationalité non textuelle → null', () {
      expect(
          ManifesteDtos.passenger(row(nationality: 229))?.nationalite, isNull);
    });

    test('espaces autour des valeurs retirés', () {
      final Passenger? passenger = ManifesteDtos.passenger(row(
        nationality: ' BJ ',
        document: const <String, dynamic>{'type': 'cip', 'number': ' PP1 '},
      ));

      expect(passenger?.nationalite, 'BJ');
      expect(passenger?.documentNumero, 'PP1');
    });
  });

  group('ManifesteDtos.isInternational', () {
    test('true / false renvoyés par le serveur', () {
      expect(
          ManifesteDtos.isInternational(
              <String, dynamic>{'international': true}),
          isTrue);
      expect(
          ManifesteDtos.isInternational(
              <String, dynamic>{'international': false}),
          isFalse);
    });

    test('absent ou invalide → inconnu (null), jamais false', () {
      expect(ManifesteDtos.isInternational(<String, dynamic>{}), isNull);
      expect(
          ManifesteDtos.isInternational(
              <String, dynamic>{'international': 'yes'}),
          isNull);
      expect(ManifesteDtos.isInternational(null), isNull);
      expect(ManifesteDtos.isInternational('texte'), isNull);
    });
  });
}
