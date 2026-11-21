// L'accueil de l'onglet Colis — écran 01 de la maquette KORA Colis.
//
// Les données sont figées : ce qui se teste ici, c'est que l'écran dise
// la bonne chose. Un envoi en cours, son trajet de hub à hub, et les
// trois étapes qui ne doivent pas se confondre.

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/colis/data/datasources/colis_mock_data.dart';
import 'package:kora_app_passenger/features/colis/presentation/pages/colis_page.dart';

void main() {
  setUpAll(AppLocalization.preloadAll);

  Future<void> pumpColis(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1800 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr', 'FR'),
        supportedLocales: const [Locale('fr', 'FR')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: const ColisPage(),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('l’accueil ouvre sur l’envoi et le suivi', (tester) async {
    await pumpColis(tester);

    expect(find.text('Colis'), findsOneWidget);
    expect(find.text('Suivi rapide'), findsOneWidget);
    // L'action dorée de l'écran : le parcours d'envoi commence là.
    expect(find.text('Nouvel envoi'), findsOneWidget);
    // Le périmètre du ticket s'arrête au dépôt au hub : promettre une
    // collecte ou une livraison à domicile engagerait une phase qui
    // n'est pas ouverte.
    expect(find.textContaining('Dépôt au hub'), findsOneWidget);
    expect(find.textContaining('livraison'), findsNothing);
  });

  testWidgets('l’envoi en cours montre son trajet et ses trois étapes', (
    tester,
  ) async {
    await pumpColis(tester);
    final shipment = kMockColisShipments.single;

    expect(find.text('Envois en cours (1)'), findsOneWidget);
    expect(find.text(shipment.reference), findsOneWidget);
    expect(find.textContaining('Cotonou (Gare Centrale)'), findsOneWidget);
    expect(find.textContaining('Parakou (Hub Nord)'), findsOneWidget);

    // Déposé, en route, attendu : trois états distincts, jamais fondus
    // en un seul « en cours ».
    expect(find.text('Déposé au hub'), findsOneWidget);
    expect(find.text('En route'), findsOneWidget);
    expect(find.text('Hub Parakou'), findsOneWidget);
    expect(
      [for (final step in shipment.steps) step.state],
      const [
        ColisStepState.done,
        ColisStepState.current,
        ColisStepState.planned,
      ],
    );

    // Le code de retrait ne transite pas par l'app : l'écran le dit.
    expect(
      find.textContaining('code de retrait secret par SMS'),
      findsOneWidget,
    );
  });

  testWidgets('la référence se copie telle qu’elle se dicte', (tester) async {
    final copied = <String>[];
    tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
      SystemChannels.platform,
      (call) async {
        if (call.method == 'Clipboard.setData') {
          copied.add((call.arguments as Map)['text'] as String);
        }
        return null;
      },
    );
    addTearDown(
      () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
        SystemChannels.platform,
        null,
      ),
    );

    await pumpColis(tester);
    await tester.tap(find.byIcon(Icons.copy_rounded));
    await tester.pumpAndSettle();

    expect(copied, [kMockColisShipments.single.reference]);
    expect(find.text('Référence copiée'), findsOneWidget);

    // Le toast s'efface tout seul : sans l'attendre, son minuteur reste
    // en vol et le test échoue sur un timer en suspens.
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  });
}
