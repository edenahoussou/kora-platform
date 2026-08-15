// Tests de la première étape de création de compte : champ vide, format
// invalide, et transmission de l'adresse validée.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/signup_email_page.dart';

void main() {
  late String? submitted;

  Future<void> pumpPage(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 844 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    submitted = null;

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: SignupEmailPage(onSubmit: (email) => submitted = email),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('affiche la consigne et le champ vide', (tester) async {
    await pumpPage(tester);

    expect(find.text('Votre adresse email'), findsOneWidget);
    expect(find.text('CRÉATION DE COMPTE'), findsOneWidget);
    expect(
      find.text(
        'Nous vous enverrons un code de vérification sécurisé par email.',
      ),
      findsOneWidget,
    );

    // Le champ ne propose pas d'adresse toute faite, juste un exemple.
    expect(find.text('vous@exemple.com'), findsOneWidget);
    final champ = tester.widget<TextField>(find.byType(TextField));
    expect(champ.controller!.text, isEmpty);
  });

  testWidgets('refuse un champ vide', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail requise'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('refuse une adresse mal formée', (tester) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField), 'aicha.example.com');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(find.text('Adresse e-mail invalide'), findsOneWidget);
    expect(submitted, isNull);
  });

  testWidgets('transmet l’adresse validée, sans espaces superflus', (
    tester,
  ) async {
    await pumpPage(tester);

    await tester.enterText(find.byType(TextField), '  aicha@example.com  ');
    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();

    expect(submitted, 'aicha@example.com');
    expect(find.textContaining('invalide'), findsNothing);
  });

  testWidgets('efface l’erreur dès que la saisie reprend', (tester) async {
    await pumpPage(tester);

    await tester.tap(find.text('Recevoir mon code'));
    await tester.pumpAndSettle();
    expect(find.text('Adresse e-mail requise'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'a');
    await tester.pumpAndSettle();
    expect(find.text('Adresse e-mail requise'), findsNothing);
  });
}
