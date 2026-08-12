import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraInput — golden', () {
    testWidgets('par défaut', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Adresse e-mail', hint: 'toi@exemple.com'),
        surfaceSize: const Size(320, 100),
      );
      await expectLater(find.byType(KoraInput), matchesGoldenFile('kora_input_default.png'));
    });

    testWidgets('erreur', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Mot de passe', obscureText: true, errorText: 'Minimum 8 caractères'),
        surfaceSize: const Size(320, 110),
      );
      await expectLater(find.byType(KoraInput), matchesGoldenFile('kora_input_error.png'));
    });

    testWidgets('dark theme', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraInput(label: 'Adresse e-mail', hint: 'toi@exemple.com'),
        surfaceSize: const Size(320, 100),
        dark: true,
      );
      await expectLater(find.byType(KoraInput), matchesGoldenFile('kora_input_dark.png'));
    });
  });
}
