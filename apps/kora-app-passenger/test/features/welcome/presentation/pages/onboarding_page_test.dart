// Les trois temps de l'onboarding : ce qu'ils montrent, et les deux
// sorties — dérouler jusqu'au bout, ou passer.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/welcome/presentation/pages/onboarding_page.dart';

void main() {
  setUpAll(() => AppLocalization.preloadAll());

  late int done;

  setUp(() => done = 0);

  Future<void> pumpOnboarding(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: const Locale('fr'),
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: OnboardingPage(onDone: () => done++),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('ouvre sur le premier temps', (tester) async {
    await pumpOnboarding(tester);

    expect(find.textContaining('Villes, date, voyageurs'), findsOneWidget);
    expect(find.text('Suivant'), findsOneWidget);
    expect(find.text('Passer'), findsOneWidget);
    // Le bouton final n'apparaît qu'au dernier temps.
    expect(find.text('Commencer'), findsNothing);
  });

  testWidgets('« Suivant » avance d’un temps', (tester) async {
    await pumpOnboarding(tester);

    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();

    expect(find.textContaining('Le plan du bus'), findsOneWidget);
    expect(find.textContaining('Villes, date, voyageurs'), findsNothing);
  });

  testWidgets('le dernier temps propose de commencer, sans « Passer »', (
    tester,
  ) async {
    await pumpOnboarding(tester);

    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Suivant'));
    await tester.pumpAndSettle();

    expect(find.textContaining('Le billet arrive dans l’app'), findsOneWidget);
    expect(find.text('Commencer'), findsOneWidget);
    // Plus rien à passer une fois au bout.
    expect(find.text('Passer'), findsNothing);

    await tester.tap(find.text('Commencer'));
    await tester.pumpAndSettle();
    expect(done, 1);
  });

  testWidgets('« Passer » termine aussi l’onboarding', (tester) async {
    // Passer n'est pas abandonner : dans les deux cas l'app a été
    // présentée, et la redonner à la prochaine ouverture serait une
    // punition.
    await pumpOnboarding(tester);

    await tester.tap(find.text('Passer'));
    await tester.pumpAndSettle();

    expect(done, 1);
  });

  testWidgets('on peut aussi balayer d’un temps à l’autre', (tester) async {
    await pumpOnboarding(tester);

    await tester.drag(find.byType(PageView), const Offset(-400, 0));
    await tester.pumpAndSettle();

    expect(find.textContaining('Le plan du bus'), findsOneWidget);
  });
}
