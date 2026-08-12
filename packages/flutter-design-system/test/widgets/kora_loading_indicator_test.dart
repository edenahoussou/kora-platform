import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraLoadingIndicator', () {
    testWidgets(
      'variante spinner affiche un CircularProgressIndicator Material 3',
      (tester) async {
        await pumpKoraWidget(tester, const KoraLoadingIndicator());

        expect(find.byType(CircularProgressIndicator), findsOneWidget);
      },
    );

    testWidgets('la taille du spinner respecte size', (tester) async {
      await pumpKoraWidget(tester, const KoraLoadingIndicator(size: 44));

      final box = tester.widget<SizedBox>(
        find
            .ancestor(
              of: find.byType(CircularProgressIndicator),
              matching: find.byType(SizedBox),
            )
            .first,
      );
      expect(box.width, 44);
      expect(box.height, 44);
    });

    testWidgets('variante skeleton affiche skeletonLines barres', (
      tester,
    ) async {
      await pumpKoraWidget(
        tester,
        const SizedBox(
          width: 260,
          child: KoraLoadingIndicator(
            variant: KoraLoadingIndicatorVariant.skeleton,
            skeletonLines: 3,
          ),
        ),
      );
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.byType(CircularProgressIndicator), findsNothing);
      expect(find.byType(ShaderMask), findsNWidgets(3));
    });
  });
}
