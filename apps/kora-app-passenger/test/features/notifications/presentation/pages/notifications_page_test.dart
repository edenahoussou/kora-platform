// Tests du centre de notifications : la liste, les pastilles de non-lu,
// « Tout lire », et la façon dont chaque date est formulée.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/notifications/presentation/pages/notifications_page.dart';

void main() {
  /// Horloge figée : les libellés « Il y a 2 min » et « Hier » dépendent
  /// du moment où l'écran s'ouvre.
  final today = DateTime(2026, 8, 22, 10, 30);

  setUpAll(() async {
    await initializeDateFormatting();
  });

  Future<void> pumpPage(
    WidgetTester tester, {
    Locale locale = const Locale('fr'),
  }) async {
    tester.view.physicalSize = const Size(390 * 3, 1600 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        locale: locale,
        supportedLocales: const [Locale('fr'), Locale('en')],
        localizationsDelegates: const [
          AppLocalization.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        home: NotificationsPage(today: today),
      ),
    );
    await tester.pumpAndSettle();
  }

  /// Le toast garde un minuteur : sans le laisser finir, le test
  /// s'achèverait avec un timer en attente.
  Future<void> settleToast(WidgetTester tester) async {
    await tester.pump(const Duration(seconds: 3));
    await tester.pumpAndSettle();
  }

  testWidgets('liste les notifications de la maquette', (tester) async {
    await pumpPage(tester);

    expect(find.text('VOTRE ACTIVITÉ'), findsOneWidget);
    expect(find.text('Notifications'), findsOneWidget);

    expect(find.text('Billet confirmé'), findsOneWidget);
    expect(find.text('Départ dans 2 heures'), findsOneWidget);
    expect(find.text('Retard estimé : 35 min'), findsOneWidget);
    expect(find.text('+ 12 500 FCFA'), findsOneWidget);
    expect(find.text('Votre colis est arrivé'), findsOneWidget);
    expect(find.text('Départ annulé'), findsOneWidget);
    expect(find.text('Rappel de voyage'), findsOneWidget);
  });

  testWidgets('formule chaque date selon son ancienneté', (tester) async {
    await pumpPage(tester);

    // Reçue il y a deux minutes, dans la même heure.
    expect(find.text('Il y a 2 min'), findsOneWidget);
    // Deux notifications datent du jour, une de la veille.
    expect(find.text('Aujourd’hui'), findsNWidgets(2));
    expect(find.text('Hier'), findsOneWidget);
  });

  testWidgets('« Tout lire » retire les pastilles et le lien', (tester) async {
    await pumpPage(tester);

    // Deux notifications non lues dans le jeu de la maquette.
    expect(_goldDots(tester), 2);

    await tester.tap(find.text('Tout lire'));
    await tester.pumpAndSettle();

    expect(_goldDots(tester), 0);
    // Le lien disparaît : il n'a plus rien à faire.
    expect(find.text('Tout lire'), findsNothing);
    expect(
      find.text('Toutes les notifications sont marquées comme lues.'),
      findsOneWidget,
    );

    await settleToast(tester);
  });

  testWidgets('annonce le détail, qui n’existe pas encore', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Billet confirmé'));
    await tester.pumpAndSettle();

    expect(
      find.text('Le détail de cette notification arrive bientôt.'),
      findsOneWidget,
    );

    await settleToast(tester);
  });

  testWidgets('s’affiche en anglais quand la langue est l’anglais', (
    tester,
  ) async {
    await pumpPage(tester, locale: const Locale('en'));

    expect(find.text('YOUR ACTIVITY'), findsOneWidget);
    expect(find.text('Mark all read'), findsOneWidget);
    expect(find.text('2 min ago'), findsOneWidget);
    expect(find.text('Yesterday'), findsOneWidget);
  });
}

/// Compte les pastilles dorées de non-lu.
int _goldDots(WidgetTester tester) =>
    tester.widgetList<Container>(find.byType(Container)).where((container) {
      final decoration = container.decoration;
      return decoration is BoxDecoration &&
          decoration.shape == BoxShape.circle &&
          decoration.color == AppColors.gold;
    }).length;
