import 'package:flutter/material.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_test/flutter_test.dart';

/// Enveloppe un widget dans un `MaterialApp` + [AppTheme], comme le ferait
/// n'importe quelle app consommatrice du design system. Tous les
/// composants `Kora*` lisent leurs couleurs via `context.koraColors` /
/// `Theme.of(context)` — sans ce wrapper, ils lèveraient une exception au
/// premier `build()`.
Future<void> pumpKoraWidget(
  WidgetTester tester,
  Widget child, {
  bool dark = false,
  Size surfaceSize = const Size(420, 800),
}) async {
  tester.view.physicalSize = surfaceSize;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    MaterialApp(
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: dark ? ThemeMode.dark : ThemeMode.light,
      home: Scaffold(body: Center(child: child)),
    ),
  );
}
