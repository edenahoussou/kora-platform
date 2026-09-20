import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/border_manifest/domain/services/border_manifest_builder.dart';
import 'package:kora_ops_mobile/features/ops/domain/entities/passenger.dart';

import '../support/border_manifest_fixtures.dart';

void main() {
  final DateTime now = DateTime(2026, 8, 14, 7, 5);

  group('BorderManifestBuilder.build', () {
    test('manifeste complet d\'un départ international : payload prêt', () {
      final BorderManifestBuild build =
          BorderManifestBuilder.build(borderInput(), now: now);

      expect(build.isReady, isTrue);
      expect(build.issues, isEmpty);

      final payload = build.payload!;
      expect(payload.departureNumber, '9B1DEB4D');
      expect(payload.departureDate, '14/08/2026');
      expect(payload.departureTime, '07:30');
      expect(payload.lineCode, 'COT-LFW');
      expect(payload.route, 'Cotonou → Lomé');
      expect(payload.passengerCount, 2);
      expect(payload.passengers.first.nomComplet, 'Awa Traoré');
      expect(payload.passengers.first.nationalite, 'BJ');
      expect(payload.passengers.first.documentNumero, 'PP123456');
      expect(payload.passengers.first.destination, 'Lomé');
      expect(payload.passengers.first.siege, '1A');
      expect(payload.printedAt, now);
      expect(payload.manifestVersion, 'abcdef1234567890');
    });

    test('départ national : refusé', () {
      final BorderManifestBuild build =
          BorderManifestBuilder.build(borderInput(isInternational: false));

      expect(build.isReady, isFalse);
      expect(build.issues.single, contains('pas international'));
    });

    test(
        'départ dont le type est inconnu : refusé, demande un rafraîchissement',
        () {
      final BorderManifestBuild build =
          BorderManifestBuilder.build(borderInput(isInternational: null));

      expect(build.isReady, isFalse);
      expect(build.issues.single, contains('Rafraîchissez'));
    });

    test('aucun passager : refusé', () {
      final BorderManifestBuild build =
          BorderManifestBuilder.build(borderInput(passengers: <Passenger>[]));

      expect(build.isReady, isFalse);
      expect(build.issues.single, contains('Aucun passager'));
    });

    test('champ obligatoire manquant : refusé, le passager est nommé', () {
      final BorderManifestBuild build = BorderManifestBuilder.build(
        borderInput(passengers: <Passenger>[
          borderPassenger(),
          borderPassenger(
              id: 'ticket-2',
              name: 'Koffi Mensah',
              seat: '1B',
              nationality: null,
              document: '  '),
        ]),
      );

      expect(build.isReady, isFalse);
      expect(build.payload, isNull);
      expect(build.issues, hasLength(1));
      expect(build.issues.single, contains('Koffi Mensah'));
      expect(build.issues.single, contains('1B'));
      expect(build.issues.single, contains('nationalité'));
      expect(build.issues.single, contains('n° de document'));
    });

    test('siège, destination et nom absents (valeurs de repli du DTO)', () {
      final BorderManifestBuild build = BorderManifestBuilder.build(
        borderInput(passengers: <Passenger>[
          borderPassenger(name: 'Passager', seat: '—', destination: '—'),
        ]),
      );

      expect(build.isReady, isFalse);
      expect(build.issues.single, contains('Passager sans nom'));
      expect(build.issues.single, contains('nom'));
      expect(build.issues.single, contains('siège'));
      expect(build.issues.single, contains('destination'));
    });

    test('plusieurs passagers incomplets : une ligne par passager', () {
      final BorderManifestBuild build = BorderManifestBuilder.build(
        borderInput(passengers: <Passenger>[
          borderPassenger(nationality: null),
          borderPassenger(id: 't2', name: 'Autre', seat: '2A', document: null),
        ]),
      );

      expect(build.issues, hasLength(2));
    });

    test('les passagers refusés / descendus avant la frontière sont exclus',
        () {
      final BorderManifestBuild build = BorderManifestBuilder.build(
        borderInput(passengers: <Passenger>[
          borderPassenger(),
          // Incomplet mais no-show : ne bloque pas et n'est pas imprimé.
          borderPassenger(
              id: 't2',
              name: 'No Show',
              nationality: null,
              status: BoardingStatus.refuse),
          borderPassenger(
              id: 't3',
              name: 'Descendu',
              document: null,
              status: BoardingStatus.descenduAnticipe),
        ]),
      );

      expect(build.isReady, isTrue);
      expect(build.payload!.passengerCount, 1);
      expect(build.payload!.passengers.single.nomComplet, 'Awa Traoré');
    });

    test('les passagers embarqués sont inclus', () {
      final BorderManifestBuild build = BorderManifestBuilder.build(
        borderInput(passengers: <Passenger>[
          borderPassenger(status: BoardingStatus.embarque),
        ]),
      );

      expect(build.payload!.passengerCount, 1);
    });

    test('un manifeste refusé n\'a jamais de payload partiel', () {
      final BorderManifestBuild build = BorderManifestBuilder.build(
        borderInput(passengers: <Passenger>[
          borderPassenger(),
          borderPassenger(id: 't2', document: null),
        ]),
      );

      expect(build.payload, isNull);
    });
  });

  group('BorderManifestBuilder.departureNumber', () {
    test('8 premiers caractères de l\'UUID, en majuscules', () {
      expect(BorderManifestBuilder.departureNumber(kDepartureUuid), '9B1DEB4D');
    });

    test('identifiant court : conservé tel quel', () {
      expect(BorderManifestBuilder.departureNumber('ab-12'), 'AB12');
    });
  });
}
