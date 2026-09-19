import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/features/printing/data/formatters/border_manifest_formatter.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/border_manifest_payload.dart';

BorderManifestPayload payload({
  List<BorderManifestPassengerLine>? passengers,
  String? version = 'abcdef1234567890',
  DateTime? fetchedAt,
}) =>
    BorderManifestPayload(
      departureNumber: '9B1DEB4D',
      departureDate: '14/08/2026',
      departureTime: '07:30',
      lineCode: 'COT-LFW',
      route: 'Cotonou → Lomé',
      passengers: passengers ??
          const <BorderManifestPassengerLine>[
            BorderManifestPassengerLine(
              nomComplet: 'Fatou Diop',
              nationalite: 'SN',
              documentNumero: 'A1234567',
              destination: 'Porto-Novo',
              siege: '12A',
            ),
          ],
      printedAt: DateTime(2026, 8, 14, 7, 5),
      manifestVersion: version,
      manifestFetchedAt: fetchedAt ?? DateTime(2026, 8, 14, 6, 45),
    );

List<String> format(BorderManifestPayload p,
        {int copy = 1, int total = 3}) =>
    BorderManifestFormatter.format(p, copyNumber: copy, totalCopies: total);

void main() {
  group('BorderManifestFormatter', () {
    test('aucune ligne ne dépasse 32 caractères (58 mm)', () {
      final List<String> lines = format(payload());

      expect(BorderManifestFormatter.isValid(lines), isTrue);
      expect(lines.every((String l) => l.length <= 32), isTrue);
    });

    test('en-tête : marque, titre, départ, ligne, passagers et exemplaire',
        () {
      final String text = format(payload(), copy: 2).join('\n');

      expect(text, contains('KORA EXPRESS'));
      expect(text, contains('MANIFESTE FRONTIERE'));
      expect(text, contains('Depart: 9B1DEB4D'));
      expect(text, contains('Date: 14/08/2026 07:30'));
      expect(text, contains('Ligne: COT-LFW'));
      expect(text, contains('Passagers: 1'));
      expect(text, contains('Exemplaire: 2/3'));
    });

    test('un passager tient sur un bloc de 2 lignes (nom, puis détails)', () {
      final List<String> lines = format(payload());

      final int nameIndex = lines.indexOf('01 FATOU DIOP');
      expect(nameIndex, greaterThan(0));
      expect(lines[nameIndex + 1], 'SN A1234567 > Porto-Novo 12A');
    });

    test('les passagers sont numérotés dans l\'ordre', () {
      final List<String> lines = format(payload(
        passengers: const <BorderManifestPassengerLine>[
          BorderManifestPassengerLine(
              nomComplet: 'Un',
              nationalite: 'BJ',
              documentNumero: 'D1',
              destination: 'Lome',
              siege: '1A'),
          BorderManifestPassengerLine(
              nomComplet: 'Deux',
              nationalite: 'BJ',
              documentNumero: 'D2',
              destination: 'Lome',
              siege: '1B'),
        ],
      ));

      expect(lines, contains('01 UN'));
      expect(lines, contains('02 DEUX'));
    });

    test('les accents sont retirés (police P501 sans accents)', () {
      final String text = format(payload(
        passengers: const <BorderManifestPassengerLine>[
          BorderManifestPassengerLine(
              nomComplet: 'Aïcha Hounkpé',
              nationalite: 'BJ',
              documentNumero: 'P1',
              destination: 'Lomé',
              siege: '2C'),
        ],
      )).join('\n');

      expect(text, contains('01 AICHA HOUNKPE'));
      expect(text, contains('BJ P1 > Lome 2C'));
      expect(text.runes.every((int rune) => rune < 128), isTrue);
    });

    test('un nom long est replié, jamais tronqué', () {
      const String name =
          'Abdoul-Karim Mahamadou Ousmane Bakary Salifou Ouedraogo';
      final List<String> lines = format(payload(
        passengers: const <BorderManifestPassengerLine>[
          BorderManifestPassengerLine(
              nomComplet: name,
              nationalite: 'BF',
              documentNumero: 'B0012345',
              destination: 'Cotonou',
              siege: '5D'),
        ],
      ));

      expect(BorderManifestFormatter.isValid(lines), isTrue);
      final String flattened = lines.join('').replaceAll(' ', '');
      expect(flattened, contains(name.toUpperCase().replaceAll(' ', '')));
    });

    test('un mot plus long qu\'une ligne est coupé au caractère, sans perte',
        () {
      final String longDocument = 'X' * 45;
      final List<String> lines = format(payload(
        passengers: <BorderManifestPassengerLine>[
          BorderManifestPassengerLine(
              nomComplet: 'Test',
              nationalite: 'BJ',
              documentNumero: longDocument,
              destination: 'Lome',
              siege: '1A'),
        ],
      ));

      expect(BorderManifestFormatter.isValid(lines), isTrue);
      expect(lines.join('').replaceAll(' ', ''), contains(longDocument));
    });

    test('le n° de document n\'est ni modifié ni mis en majuscules', () {
      final String text = format(payload(
        passengers: const <BorderManifestPassengerLine>[
          BorderManifestPassengerLine(
              nomComplet: 'Test',
              nationalite: 'bj',
              documentNumero: 'aB-12/cd',
              destination: 'Lome',
              siege: '1A'),
        ],
      )).join('\n');

      expect(text, contains('BJ aB-12/cd > Lome 1A'));
    });

    test('détails trop longs : la 2e ligne est coupée en deux, pas rognée', () {
      final List<String> lines = format(payload(
        passengers: const <BorderManifestPassengerLine>[
          BorderManifestPassengerLine(
              nomComplet: 'Test',
              nationalite: 'BJ',
              documentNumero: 'ABCDEFGH1234567',
              destination: 'Saint-Louis du Senegal',
              siege: '14B'),
        ],
      ));

      expect(BorderManifestFormatter.isValid(lines), isTrue);
      final String text = lines.join('\n');
      expect(text, contains('BJ ABCDEFGH1234567'));
      expect(text, contains('> Saint-Louis du Senegal 14B'));
    });

    test('pied de page : version tronquée à 12 caractères, dates', () {
      final String text = format(payload()).join('\n');

      expect(text, contains('Version: abcdef123456'));
      expect(text, isNot(contains('abcdef1234567')));
      expect(text, contains('Maj: 14/08/2026 06:45'));
      expect(text, contains('Imprime: 14/08/2026 07:05'));
      expect(text, contains('FIN DU MANIFESTE'));
    });

    test('sans version serveur, la ligne « Version » est absente', () {
      final String text = format(payload(version: null)).join('\n');

      expect(text, isNot(contains('Version:')));
    });

    test('jamais de montant ni de téléphone dans le document', () {
      final String text = format(payload()).join('\n').toLowerCase();

      expect(text, isNot(contains('xof')));
      expect(text, isNot(contains('fcfa')));
      expect(text, isNot(contains('prix')));
      expect(text, isNot(contains('montant')));
      expect(text, isNot(contains('+229')));
    });

    test('un manifeste de 60 passagers reste valide sur 58 mm', () {
      final List<BorderManifestPassengerLine> passengers =
          List<BorderManifestPassengerLine>.generate(
        60,
        (int i) => BorderManifestPassengerLine(
          nomComplet: 'Passager Numero $i Nom Composé Long',
          nationalite: 'BJ',
          documentNumero: 'PP${100000 + i}',
          destination: 'Porto-Novo',
          siege: '${i + 1}A',
        ),
      );

      final List<String> lines = format(payload(passengers: passengers));

      expect(BorderManifestFormatter.isValid(lines), isTrue);
      expect(lines.where((String l) => l.startsWith('60 ')), hasLength(1));
    });

    group('ascii', () {
      test('retire les accents et remplace les caractères sans équivalent', () {
        expect(BorderManifestFormatter.ascii('Éléonore Ñandú'),
            'Eleonore Nandu');
        expect(BorderManifestFormatter.ascii('Cotonou → Lomé'),
            'Cotonou > Lome');
        expect(BorderManifestFormatter.ascii('Ɛwɛ Ɔdɖ'), 'Ewe Odd');
        expect(BorderManifestFormatter.ascii('日本'), '??');
      });
    });
  });
}
