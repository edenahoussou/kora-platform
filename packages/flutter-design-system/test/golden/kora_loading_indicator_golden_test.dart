import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

import '../test_utils.dart';

void main() {
  group('KoraLoadingIndicator — golden', () {
    testWidgets('spinner', (tester) async {
      await pumpKoraWidget(
        tester,
        const KoraLoadingIndicator(),
        surfaceSize: const Size(60, 60),
      );
      await expectLater(
        find.byType(KoraLoadingIndicator),
        matchesGoldenFile('kora_loading_spinner.png'),
      );
    });

    testWidgets('skeleton — 3 lignes', (tester) async {
      await pumpKoraWidget(
        tester,
        const SizedBox(
          width: 220,
          child: KoraLoadingIndicator(
            variant: KoraLoadingIndicatorVariant.skeleton,
            skeletonLines: 3,
          ),
        ),
        surfaceSize: const Size(260, 120),
      );
      await expectLater(
        find.byType(KoraLoadingIndicator),
        matchesGoldenFile('kora_loading_skeleton.png'),
      );
    });
  });
}
