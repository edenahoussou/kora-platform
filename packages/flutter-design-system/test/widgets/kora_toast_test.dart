// Le toast vit dans un `Overlay`, à côté de l'arbre du `Scaffold` : rien
// ne lui garantit un `Material` au-dessus de lui, et sans ancêtre
// Material Flutter dessine le texte avec son style de secours — jaune et
// souligné. Ces tests verrouillent ce point, invisible en analyse.

import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  /// Monte un écran dont l'unique bouton demande un toast.
  Future<void> pumpToaster(WidgetTester tester, String message) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: Scaffold(
          body: Builder(
            builder: (context) => TextButton(
              onPressed: () => KoraToaster.show(context, message),
              child: const Text('Déclencher'),
            ),
          ),
        ),
      ),
    );
  }

  testWidgets('affiche le message demandé', (tester) async {
    await pumpToaster(tester, 'Session révoquée.');

    await tester.tap(find.text('Déclencher'));
    await tester.pumpAndSettle();

    expect(find.text('Session révoquée.'), findsOneWidget);

    // On laisse le minuteur finir : sans ça le test s'achèverait avec un
    // timer en attente.
    await tester.pump(KoraToaster.defaultDuration + const Duration(seconds: 1));
    await tester.pumpAndSettle();
    expect(find.text('Session révoquée.'), findsNothing);
  });

  testWidgets('pose un Material au-dessus de son texte', (tester) async {
    await pumpToaster(tester, 'Session révoquée.');

    await tester.tap(find.text('Déclencher'));
    await tester.pumpAndSettle();

    // Sans cet ancêtre, le texte s'afficherait souligné en jaune.
    expect(
      find.ancestor(
        of: find.text('Session révoquée.'),
        matching: find.byType(Material),
      ),
      findsWidgets,
    );

    // Le style appliqué est bien celui du toast, et non celui de secours.
    final text = tester.widget<Text>(find.text('Session révoquée.'));
    expect(text.style?.color, AppColors.white);
    expect(text.style?.decoration, isNot(TextDecoration.underline));

    await tester.pump(KoraToaster.defaultDuration + const Duration(seconds: 1));
    await tester.pumpAndSettle();
  });
}
