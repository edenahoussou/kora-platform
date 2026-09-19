import 'dart:typed_data';

import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/exceptions/app_exceptions.dart';
import 'package:kora_ops_mobile/features/printing/data/services/p501_thermal_printer.dart';
import 'package:kora_ops_mobile/features/printing/domain/entities/border_manifest_payload.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  const MethodChannel channel = MethodChannel(P501ThermalPrinter.channelName);
  final Uint8List logo = Uint8List.fromList(<int>[1, 2, 3]);

  final BorderManifestPayload manifest = BorderManifestPayload(
    departureNumber: '9B1DEB4D',
    departureDate: '14/08/2026',
    departureTime: '07:30',
    lineCode: 'COT-LFW',
    route: 'Cotonou → Lomé',
    passengers: const <BorderManifestPassengerLine>[
      BorderManifestPassengerLine(
        nomComplet: 'Fatou Diop',
        nationalite: 'SN',
        documentNumero: 'A1234567',
        destination: 'Porto-Novo',
        siege: '12A',
      ),
    ],
    printedAt: DateTime(2026, 8, 14, 7, 5),
  );

  P501ThermalPrinter buildPrinter({Uint8List? logoBytes}) => P501ThermalPrinter(
        channel: channel,
        logoLoader: () async => logoBytes,
      );

  void mockNative(Future<Object?> Function(MethodCall call)? handler) {
    TestDefaultBinaryMessengerBinding.instance.defaultBinaryMessenger
        .setMockMethodCallHandler(channel, handler);
  }

  tearDown(() => mockNative(null));

  group('P501ThermalPrinter.printBorderManifest', () {
    test('envoie au natif les lignes 58 mm, le logo et le n° d\'exemplaire',
        () async {
      MethodCall? received;
      mockNative((MethodCall call) async {
        received = call;
        return true;
      });

      final bool result = await buildPrinter(logoBytes: logo)
          .printBorderManifest(manifest, copyNumber: 2, totalCopies: 3);

      expect(result, isTrue);
      expect(received?.method, 'printBorderManifest');

      final Map<Object?, Object?> args =
          received!.arguments as Map<Object?, Object?>;
      expect(args['copyNumber'], 2);
      expect(args['totalCopies'], 3);
      expect(args['logoPng'], logo);

      final List<Object?> lines = args['lines']! as List<Object?>;
      expect(lines, isNotEmpty);
      expect(lines.every((Object? l) => (l! as String).length <= 32), isTrue);
      expect(lines.join('\n'), contains('Exemplaire: 2/3'));
    });

    test('imprime quand même sans logo (chargement impossible)', () async {
      MethodCall? received;
      mockNative((MethodCall call) async {
        received = call;
        return true;
      });

      final bool result = await buildPrinter()
          .printBorderManifest(manifest, copyNumber: 1, totalCopies: 3);

      expect(result, isTrue);
      expect(
          (received!.arguments as Map<Object?, Object?>)['logoPng'], isNull);
    });

    test('retourne false si le natif ne confirme pas l\'impression', () async {
      mockNative((MethodCall call) async => null);

      expect(
        await buildPrinter()
            .printBorderManifest(manifest, copyNumber: 1, totalCopies: 3),
        isFalse,
      );

      mockNative((MethodCall call) async => false);
      expect(
        await buildPrinter()
            .printBorderManifest(manifest, copyNumber: 1, totalCopies: 3),
        isFalse,
      );
    });

    Future<PrinterException> failWith(String code) async {
      mockNative((MethodCall call) async =>
          throw PlatformException(code: code, message: 'SDK: $code'));

      try {
        await buildPrinter()
            .printBorderManifest(manifest, copyNumber: 1, totalCopies: 3);
      } on PrinterException catch (error) {
        return error;
      }
      fail('PrinterException attendue');
    }

    test('absence de papier (OUT_OF_PAPER / NO_PAPER)', () async {
      expect((await failWith('OUT_OF_PAPER')).kind,
          PrinterFailureKind.outOfPaper);
      expect(
          (await failWith('NO_PAPER')).kind, PrinterFailureKind.outOfPaper);
    });

    test('imprimante absente ou déconnectée', () async {
      expect((await failWith('NO_PRINTER')).kind,
          PrinterFailureKind.notConnected);
      expect((await failWith('PRINTER_DISCONNECTED')).kind,
          PrinterFailureKind.notConnected);
    });

    test('surchauffe', () async {
      expect(
          (await failWith('OVERHEAT')).kind, PrinterFailureKind.overheated);
    });

    test('code inconnu : erreur générique avec le message du SDK', () async {
      final PrinterException error = await failWith('WHATEVER');

      expect(error.kind, PrinterFailureKind.unknown);
      expect(error.userMessage, 'SDK: WHATEVER');
    });

    test('canal natif non implémenté : imprimante considérée absente',
        () async {
      // Aucun handler enregistré → MissingPluginException.
      mockNative(null);

      await expectLater(
        buildPrinter()
            .printBorderManifest(manifest, copyNumber: 1, totalCopies: 3),
        throwsA(isA<PrinterException>().having(
            (PrinterException e) => e.kind,
            'kind',
            PrinterFailureKind.notConnected)),
      );
    });
  });
}
