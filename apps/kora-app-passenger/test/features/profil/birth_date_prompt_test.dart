// L'invitation à renseigner sa date de naissance.
//
// Elle s'ouvre une fois, après la connexion, pour un profil qui n'a pas
// de date de naissance. Trois choses la retiennent : un profil déjà
// complet, une question déjà posée sur cet appareil, et l'absence de
// session — sans compte, il n'y a pas de profil à compléter.
//
// Une invitation qui revient à chaque lancement se fait fermer sans
// être lue : ces tests tiennent surtout le « une fois ».

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/di/injector.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/profil/data/birth_date_prompt_store.dart';
import 'package:kora_app_passenger/features/profil/presentation/widgets/birth_date_prompt.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  setUpAll(() async {
    await AppLocalization.preloadAll();
  });

  /// Installe le magasin de la question, vide ou déjà vu.
  Future<BirthDatePromptStore> store({bool seen = false}) async {
    SharedPreferences.setMockInitialValues(
      seen ? {'kora_birth_date_prompt_seen': true} : {},
    );

    final prefs = await SharedPreferences.getInstance();
    final value = BirthDatePromptStore(prefs);

    if (getIt.isRegistered<BirthDatePromptStore>()) {
      await getIt.unregister<BirthDatePromptStore>();
    }
    getIt.registerSingleton<BirthDatePromptStore>(value);
    addTearDown(() => getIt.unregister<BirthDatePromptStore>());

    return value;
  }

  Future<void> pump(
    WidgetTester tester, {
    required bool isSignedIn,
    required bool hasBirthDate,
  }) async {
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
        home: BirthDatePromptGate(
          isSignedIn: isSignedIn,
          hasBirthDate: hasBirthDate,
          child: const Scaffold(body: Text('accueil')),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('s’ouvre quand la date de naissance manque', (tester) async {
    await store();
    await pump(tester, isSignedIn: true, hasBirthDate: false);

    expect(
      find.text('Gagnez des points le jour de votre anniversaire'),
      findsOneWidget,
    );
    expect(find.text('Compléter mon profil'), findsOneWidget);
  });

  testWidgets('ne s’ouvre pas quand la date est déjà renseignée', (
    tester,
  ) async {
    final saved = await store();
    await pump(tester, isSignedIn: true, hasBirthDate: true);

    expect(find.text('Compléter mon profil'), findsNothing);
    // La question n'a pas été posée : elle reste disponible pour le
    // jour où ce profil-là en aurait besoin.
    expect(saved.hasSeen, isFalse);
  });

  testWidgets('ne s’ouvre pas sans session', (tester) async {
    await store();
    await pump(tester, isSignedIn: false, hasBirthDate: false);

    expect(find.text('Compléter mon profil'), findsNothing);
  });

  testWidgets('ne revient pas une fois posée', (tester) async {
    // Fermée d'un glissement, elle ne repasse par aucun bouton : c'est
    // l'ouverture elle-même qui doit être retenue.
    final saved = await store(seen: true);
    await pump(tester, isSignedIn: true, hasBirthDate: false);

    expect(find.text('Compléter mon profil'), findsNothing);
    expect(saved.hasSeen, isTrue);
  });

  testWidgets('retient qu’elle a été posée', (tester) async {
    final saved = await store();
    await pump(tester, isSignedIn: true, hasBirthDate: false);

    expect(saved.hasSeen, isTrue);

    await tester.tap(find.text('Plus tard'));
    await tester.pumpAndSettle();

    expect(find.text('Compléter mon profil'), findsNothing);
  });

  testWidgets('attend la session plutôt que de laisser passer', (tester) async {
    // Le profil se relit après le premier rendu : l'accueil s'affiche
    // d'abord sans session. Sans second passage, la question ne serait
    // jamais posée au lancement.
    final saved = await store();
    await pump(tester, isSignedIn: false, hasBirthDate: false);
    expect(saved.hasSeen, isFalse);

    await pump(tester, isSignedIn: true, hasBirthDate: false);

    expect(
      find.text('Gagnez des points le jour de votre anniversaire'),
      findsOneWidget,
    );
  });
}
