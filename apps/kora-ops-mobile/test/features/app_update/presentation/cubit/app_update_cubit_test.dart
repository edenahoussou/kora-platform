import 'package:bloc_test/bloc_test.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_ops_mobile/core/errors/failures.dart';
import 'package:kora_ops_mobile/core/result/result.dart';
import 'package:kora_ops_mobile/features/app_update/data/services/apk_installer.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_update_info.dart';
import 'package:kora_ops_mobile/features/app_update/domain/entities/app_version.dart';
import 'package:kora_ops_mobile/features/app_update/domain/repositories/app_update_repository.dart';
import 'package:kora_ops_mobile/features/app_update/presentation/cubit/app_update_cubit.dart';
import 'package:mocktail/mocktail.dart';

class MockAppUpdateRepository extends Mock implements AppUpdateRepository {}

const AppVersion installed = AppVersion(1, 2, 0, build: 30);

AppUpdateInfo updateInfo({bool mandatory = false, String uuid = 'r-1'}) =>
    AppUpdateInfo(
      latestVersion: '1.4.0',
      latestBuild: 42,
      serverMandatory: mandatory,
      minimumVersion: mandatory ? '1.3.0' : null,
      minimumBuild: mandatory ? 35 : null,
      releaseUuid: uuid,
      downloadUrl:
          'https://ops.koraexpress.app/downloads/apps/ops-mobile/releases/$uuid',
      sha256: 'abc',
      sizeBytes: 1000,
    );

void main() {
  late MockAppUpdateRepository repository;

  setUpAll(() {
    registerFallbackValue(updateInfo());
    registerFallbackValue(Duration.zero);
  });

  setUp(() {
    repository = MockAppUpdateRepository();
    when(() => repository.installedVersion())
        .thenAnswer((_) async => installed);
    when(() => repository.lastKnown()).thenAnswer((_) async => null);
    when(() => repository.remember(any())).thenAnswer((_) async {});
    when(() => repository.isSnoozed(any())).thenAnswer((_) async => false);
    when(() => repository.snooze(any(), any())).thenAnswer((_) async {});
  });

  group('bootstrap', () {
    blocTest<AppUpdateCubit, AppUpdateState>(
      'bloque l’app quand une version obligatoire est publiée',
      setUp: () => when(() => repository.check(installed)).thenAnswer(
        (_) async =>
            Result<AppUpdateInfo?>.success(updateInfo(mandatory: true)),
      ),
      build: () => AppUpdateCubit(repository),
      act: (cubit) => cubit.bootstrap(),
      verify: (cubit) {
        expect(cubit.state.isBlocking, isTrue);
        expect(cubit.state.shouldPromptOptional, isFalse);
        verify(() => repository.remember(updateInfo(mandatory: true)))
            .called(1);
      },
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'reste bloquée hors ligne grâce au dernier blocage connu',
      setUp: () {
        when(() => repository.lastKnown())
            .thenAnswer((_) async => updateInfo(mandatory: true));
        when(() => repository.check(installed)).thenAnswer(
          (_) async => const Result<AppUpdateInfo?>.failure(NetworkFailure()),
        );
      },
      build: () => AppUpdateCubit(repository),
      act: (cubit) => cubit.bootstrap(),
      verify: (cubit) => expect(cubit.state.isBlocking, isTrue),
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'propose une version non obligatoire sans bloquer',
      setUp: () => when(() => repository.check(installed)).thenAnswer(
        (_) async => Result<AppUpdateInfo?>.success(updateInfo()),
      ),
      build: () => AppUpdateCubit(repository),
      act: (cubit) => cubit.bootstrap(),
      verify: (cubit) {
        expect(cubit.state.isBlocking, isFalse);
        expect(cubit.state.shouldPromptOptional, isTrue);
      },
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'ne repropose pas une version reportée (« Plus tard »)',
      setUp: () {
        when(() => repository.isSnoozed(any())).thenAnswer((_) async => true);
        when(() => repository.check(installed)).thenAnswer(
          (_) async => Result<AppUpdateInfo?>.success(updateInfo()),
        );
      },
      build: () => AppUpdateCubit(repository),
      act: (cubit) => cubit.bootstrap(),
      verify: (cubit) {
        expect(cubit.state.updateAvailable, isTrue);
        expect(cubit.state.shouldPromptOptional, isFalse);
      },
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'reste inactive hors Android (version installée inconnue)',
      setUp: () => when(() => repository.installedVersion())
          .thenAnswer((_) async => null),
      build: () => AppUpdateCubit(repository),
      act: (cubit) => cubit.bootstrap(),
      expect: () => <AppUpdateState>[],
      verify: (_) => verifyNever(() => repository.check(any())),
    );
  });

  group('applyServerInfo (bloc update de handshake/sync/activity)', () {
    blocTest<AppUpdateCubit, AppUpdateState>(
      'passe en blocage dès qu’une réponse annonce une MAJ obligatoire, '
      'puis le lève si la release est archivée',
      setUp: () => when(() => repository.check(installed)).thenAnswer(
        (_) async => const Result<AppUpdateInfo?>.success(null),
      ),
      build: () => AppUpdateCubit(repository),
      act: (cubit) async {
        await cubit.bootstrap();
        await cubit.applyServerInfo(updateInfo(mandatory: true));
        expect(cubit.state.isBlocking, isTrue);
        await cubit.applyServerInfo(null);
      },
      verify: (cubit) {
        expect(cubit.state.isBlocking, isFalse);
        expect(cubit.state.info, isNull);
      },
    );
  });

  group('postpone', () {
    blocTest<AppUpdateCubit, AppUpdateState>(
      'reporte la proposition de 24 h',
      build: () => AppUpdateCubit(repository),
      seed: () => AppUpdateState(installed: installed, info: updateInfo()),
      act: (cubit) => cubit.postpone(),
      expect: () => <AppUpdateState>[
        AppUpdateState(
          installed: installed,
          info: updateInfo(),
          promptDismissed: true,
        ),
      ],
      verify: (_) => verify(
        () => repository.snooze(updateInfo(), AppUpdateCubit.snoozeDuration),
      ).called(1),
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'est impossible quand la mise à jour est obligatoire',
      build: () => AppUpdateCubit(repository),
      seed: () => AppUpdateState(
          installed: installed, info: updateInfo(mandatory: true)),
      act: (cubit) => cubit.postpone(),
      expect: () => <AppUpdateState>[],
    );
  });

  group('startUpdate', () {
    blocTest<AppUpdateCubit, AppUpdateState>(
      'télécharge, vérifie puis ouvre l’installeur Android',
      setUp: () {
        when(
          () => repository.downloadAndVerify(
            any(),
            onProgress: any(named: 'onProgress'),
          ),
        ).thenAnswer((invocation) async {
          final void Function(double?)? onProgress =
              invocation.namedArguments[#onProgress] as void Function(double?)?;
          onProgress?.call(0.5);
          return const Result<String>.success('/cache/apk_updates/k.apk');
        });
        when(() => repository.install('/cache/apk_updates/k.apk')).thenAnswer(
          (_) async => const Result<ApkInstallOutcome>.success(
            ApkInstallOutcome.installerStarted,
          ),
        );
      },
      build: () => AppUpdateCubit(repository),
      seed: () => AppUpdateState(
          installed: installed, info: updateInfo(mandatory: true)),
      act: (cubit) => cubit.startUpdate(),
      expect: () => <Matcher>[
        isA<AppUpdateState>()
            .having((s) => s.phase, 'phase', AppUpdatePhase.downloading)
            .having((s) => s.progress, 'progress', 0),
        isA<AppUpdateState>().having((s) => s.progress, 'progress', 0.5),
        isA<AppUpdateState>()
            .having((s) => s.apkPath, 'apkPath', '/cache/apk_updates/k.apk')
            .having((s) => s.progress, 'progress', 1),
        isA<AppUpdateState>()
            .having((s) => s.phase, 'phase', AppUpdatePhase.installerOpened)
            .having((s) => s.isBlocking, 'isBlocking', isTrue),
      ],
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'n’installe jamais un fichier dont le sha256 ne correspond pas',
      setUp: () => when(
        () => repository.downloadAndVerify(
          any(),
          onProgress: any(named: 'onProgress'),
        ),
      ).thenAnswer(
        (_) async => const Result<String>.failure(
          AppUpdateFailure(AppUpdateFailure.integrity, 'Empreinte invalide'),
        ),
      ),
      build: () => AppUpdateCubit(repository),
      seed: () => AppUpdateState(installed: installed, info: updateInfo()),
      act: (cubit) => cubit.startUpdate(),
      verify: (cubit) {
        expect(cubit.state.phase, AppUpdatePhase.failed);
        expect(cubit.state.errorMessage, 'Empreinte invalide');
        verifyNever(() => repository.install(any()));
      },
    );

    blocTest<AppUpdateCubit, AppUpdateState>(
      'demande l’autorisation d’installer puis reprend au retour dans l’app',
      setUp: () {
        var calls = 0;
        when(() => repository.install('/apk/k.apk')).thenAnswer((_) async {
          calls++;
          return Result<ApkInstallOutcome>.success(
            calls == 1
                ? ApkInstallOutcome.permissionRequired
                : ApkInstallOutcome.installerStarted,
          );
        });
        when(() => repository.check(installed)).thenAnswer(
          (_) async => Result<AppUpdateInfo?>.success(updateInfo()),
        );
      },
      build: () => AppUpdateCubit(repository),
      seed: () => AppUpdateState(
        installed: installed,
        info: updateInfo(),
        apkPath: '/apk/k.apk',
      ),
      act: (cubit) async {
        await cubit.startUpdate();
        expect(cubit.state.phase, AppUpdatePhase.awaitingPermission);
        await cubit.onAppResumed();
      },
      verify: (cubit) {
        expect(cubit.state.phase, AppUpdatePhase.installerOpened);
        verify(() => repository.install('/apk/k.apk')).called(2);
      },
    );
  });
}
