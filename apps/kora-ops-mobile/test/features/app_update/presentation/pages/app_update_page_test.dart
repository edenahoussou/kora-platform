import 'package:bloc_test/bloc_test.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_update_info.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_version.dart';
import 'package:kora_ops_mobile/features/app_update/presentation/cubit/app_update_cubit.dart';
import 'package:kora_ops_mobile/features/app_update/presentation/pages/app_update_page.dart';
import 'package:kora_ops_mobile/features/app_update/presentation/widgets/app_update_gate.dart';
import 'package:mocktail/mocktail.dart';

class MockAppUpdateCubit extends MockCubit<AppUpdateState>
    implements AppUpdateCubit {}

const AppVersion installed = AppVersion(1, 2, 0, build: 30);

const AppUpdateInfo mandatoryInfo = AppUpdateInfo(
  latestVersion: '1.4.0',
  latestBuild: 42,
  serverMandatory: true,
  minimumVersion: '1.3.0',
  minimumBuild: 35,
  releaseUuid: 'r-1',
  releaseNotes: 'Correctifs d’impression du manifeste.',
  downloadUrl:
      'https://ops.koraexpress.app/downloads/apps/ops-mobile/releases/r-1',
  sha256: 'abc',
  sizeBytes: 31457280,
);

const AppUpdateInfo optionalInfo = AppUpdateInfo(
  latestVersion: '1.4.0',
  latestBuild: 42,
  serverMandatory: false,
  releaseUuid: 'r-1',
  downloadUrl:
      'https://ops.koraexpress.app/downloads/apps/ops-mobile/releases/r-1',
  sha256: 'abc',
);

void main() {
  late MockAppUpdateCubit cubit;

  setUp(() {
    cubit = MockAppUpdateCubit();
    when(() => cubit.startUpdate()).thenAnswer((_) async {});
    when(() => cubit.openPermissionSettings()).thenAnswer((_) async {});
  });

  Widget host(Widget child) => BlocProvider<AppUpdateCubit>.value(
        value: cubit,
        child: MaterialApp(home: child),
      );

  group('AppUpdatePage — mise à jour obligatoire', () {
    testWidgets('affiche un écran bloquant sans aucun moyen de le fermer',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(installed: installed, info: mandatoryInfo),
      );

      await tester.pumpWidget(host(const AppUpdatePage(mandatory: true)));

      expect(find.text('Mise à jour obligatoire'), findsOneWidget);
      expect(find.text('1.2.0+30'), findsOneWidget);
      expect(find.text('1.4.0+42'), findsOneWidget);
      expect(
          find.text('Correctifs d’impression du manifeste.'), findsOneWidget);
      expect(find.text('Télécharger et installer'), findsOneWidget);
      expect(find.byKey(const Key('app-update-later')), findsNothing);
      expect(find.byKey(const Key('app-update-close')), findsNothing);
      expect(find.text('Plus tard'), findsNothing);
    });

    testWidgets('lance le téléchargement et l’installation',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(installed: installed, info: mandatoryInfo),
      );

      await tester.pumpWidget(host(const AppUpdatePage(mandatory: true)));
      await tester.tap(find.byKey(const Key('app-update-primary')));

      verify(() => cubit.startUpdate()).called(1);
    });

    testWidgets('affiche la progression puis la vérification d’intégrité',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(
          installed: installed,
          info: mandatoryInfo,
          phase: AppUpdatePhase.downloading,
          progress: 0.42,
        ),
      );

      await tester.pumpWidget(host(const AppUpdatePage(mandatory: true)));

      expect(find.byKey(const Key('app-update-progress')), findsOneWidget);
      expect(find.text('Téléchargement… 42 %'), findsOneWidget);
    });

    testWidgets('guide vers l’autorisation d’installation Android',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(
          installed: installed,
          info: mandatoryInfo,
          phase: AppUpdatePhase.awaitingPermission,
          apkPath: '/apk/k.apk',
        ),
      );

      await tester.pumpWidget(host(const AppUpdatePage(mandatory: true)));
      await tester.tap(find.byKey(const Key('app-update-permission')));

      verify(() => cubit.openPermissionSettings()).called(1);
    });

    testWidgets('affiche l’erreur et propose de réessayer',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(
          installed: installed,
          info: mandatoryInfo,
          phase: AppUpdatePhase.failed,
          errorMessage: 'Empreinte SHA-256 invalide',
        ),
      );

      await tester.pumpWidget(host(const AppUpdatePage(mandatory: true)));

      expect(find.text('Empreinte SHA-256 invalide'), findsOneWidget);
      await tester.tap(find.text('Réessayer'));
      verify(() => cubit.startUpdate()).called(1);
    });
  });

  testWidgets('AppUpdatePage non obligatoire : « Plus tard » est proposé',
      (WidgetTester tester) async {
    var later = 0;
    when(() => cubit.state).thenReturn(
      const AppUpdateState(installed: installed, info: optionalInfo),
    );

    await tester.pumpWidget(
      host(AppUpdatePage(mandatory: false, onLater: () => later++)),
    );

    expect(find.text('Nouvelle version disponible'), findsOneWidget);
    await tester.tap(find.byKey(const Key('app-update-later')));
    expect(later, 1);
  });

  group('AppUpdateGate', () {
    GoRouter router() => GoRouter(
          initialLocation: '/',
          routes: <RouteBase>[
            GoRoute(
              path: '/',
              builder: (_, __) => const Scaffold(body: Text('Application')),
            ),
          ],
        );

    Widget gated(GoRouter goRouter) => BlocProvider<AppUpdateCubit>.value(
          value: cubit,
          child: MaterialApp.router(
            routerConfig: goRouter,
            builder: (BuildContext context, Widget? child) =>
                AppUpdateGate(router: goRouter, child: child),
          ),
        );

    testWidgets('remplace toute l’application quand la MAJ est obligatoire',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(installed: installed, info: mandatoryInfo),
      );

      await tester.pumpWidget(gated(router()));
      await tester.pumpAndSettle();

      expect(find.byKey(const Key('app-update-blocking')), findsOneWidget);
      expect(find.text('Application'), findsNothing);
    });

    testWidgets('laisse l’application utilisable sans MAJ obligatoire',
        (WidgetTester tester) async {
      when(() => cubit.state).thenReturn(
        const AppUpdateState(installed: installed),
      );

      await tester.pumpWidget(gated(router()));
      await tester.pumpAndSettle();

      expect(find.text('Application'), findsOneWidget);
      expect(find.byKey(const Key('app-update-blocking')), findsNothing);
    });

    testWidgets('lève le blocage dès que la version installée est à jour',
        (WidgetTester tester) async {
      whenListen(
        cubit,
        Stream<AppUpdateState>.fromIterable(<AppUpdateState>[
          const AppUpdateState(
            installed: AppVersion(1, 4, 0, build: 42),
            info: mandatoryInfo,
          ),
        ]),
        initialState:
            const AppUpdateState(installed: installed, info: mandatoryInfo),
      );

      await tester.pumpWidget(gated(router()));
      await tester.pumpAndSettle();

      expect(find.text('Application'), findsOneWidget);
    });
  });
}
