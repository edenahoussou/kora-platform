// Tests de l'écran « Nouvel appareil » et de l'enchaînement qui le
// précède : un serveur qui répond 202 à `login`, ou le lien de l'écran
// de connexion.

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/auth_session.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_challenge.dart';
import 'package:kora_app_passenger/features/auth/domain/entities/otp_purpose.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/pages/new_device_page.dart';

import '../../../../support/stub_auth_repository.dart';

/// Dépôt dont la connexion se heurte à un appareil inconnu : c'est le
/// 202 `requires_otp` de l'API.
class _UnknownDeviceRepository extends StubAuthRepository {
  @override
  Future<Either<Failure, LoginOutcome>> login({
    required String email,
    required String pin,
    OtpVerification? verification,
  }) async {
    calls.add('login:$email');

    // Une fois le code relevé, le serveur accepte l'appareil.
    if (verification != null) {
      return Right(LoginSucceeded(StubAuthRepository.session));
    }

    return Right(
      LoginNeedsOtp(
        OtpChallengeRef(
          uuid: 'challenge-nouvel-appareil',
          expiresAt: DateTime.now().add(const Duration(minutes: 5)),
        ),
      ),
    );
  }
}

void main() {
  late StubAuthRepository repository;
  late AuthFlowBloc flow;

  Future<void> pumpPage(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1000 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    addTearDown(flow.close);

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
        home: BlocProvider<AuthFlowBloc>.value(
          value: flow,
          child: const NewDevicePage(),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('explique le refus et nomme l’appareil', (tester) async {
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository)
      ..add(const AuthFlowNewDeviceRequested('aicha@example.com'));
    await pumpPage(tester);

    expect(find.text('PROTECTION DU COMPTE'), findsOneWidget);
    expect(find.text('Nouvel appareil détecté'), findsOneWidget);
    expect(find.text('Est-ce bien vous ?'), findsOneWidget);
    expect(find.text('Connexion à confirmer'), findsOneWidget);

    // Le nom vient de l'appareil réel : c'est ce qui permet de
    // reconnaître — ou non — sa propre tentative.
    expect(find.text('Infinix HOT 12i'), findsOneWidget);
    expect(find.text('À l’instant'), findsOneWidget);
  });

  testWidgets('demande le code quand aucun défi n’existe encore', (
    tester,
  ) async {
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository)
      ..add(const AuthFlowNewDeviceRequested('aicha@example.com'));
    await pumpPage(tester);

    // Venu du lien de l'écran de connexion : rien n'a encore été envoyé.
    expect(repository.calls, isEmpty);

    await tester.tap(find.text('Recevoir le code OTP'));
    await tester.pumpAndSettle();

    expect(repository.calls, ['requestOtp:login_new_device']);
    expect(flow.state.step, AuthFlowStep.code);
  });

  testWidgets('ne redemande pas de code quand le serveur en a déjà envoyé un', (
    tester,
  ) async {
    final unknown = _UnknownDeviceRepository();
    repository = unknown;
    flow = AuthFlowBloc(repository)
      ..add(
        const AuthFlowLoginSubmitted(email: 'aicha@example.com', pin: '1234'),
      );
    await pumpPage(tester);

    // Le 202 mène ici, pas directement à la saisie du code.
    expect(flow.state.step, AuthFlowStep.newDevice);
    expect(flow.state.purpose, OtpPurpose.loginNewDevice);

    await tester.tap(find.text('Recevoir le code OTP'));
    await tester.pumpAndSettle();

    // Un second `otp/request` invaliderait le code déjà reçu : le
    // serveur n'en garde qu'un actif à la fois.
    expect(unknown.calls, ['login:aicha@example.com']);
    expect(flow.state.step, AuthFlowStep.code);
  });

  testWidgets('le code vérifié rejoue la connexion avec le PIN gardé', (
    tester,
  ) async {
    final unknown = _UnknownDeviceRepository();
    repository = unknown;
    flow = AuthFlowBloc(repository)
      ..add(
        const AuthFlowLoginSubmitted(email: 'aicha@example.com', pin: '1234'),
      );
    await pumpPage(tester);

    await tester.tap(find.text('Recevoir le code OTP'));
    await tester.pumpAndSettle();

    flow.add(const AuthFlowCodeSubmitted('123456'));
    await tester.pumpAndSettle();

    // Le PIN n'est pas redemandé, et la session s'ouvre.
    expect(unknown.calls, [
      'login:aicha@example.com',
      'verifyOtp:123456',
      'login:aicha@example.com',
    ]);
    expect(flow.state.step, AuthFlowStep.done);
    expect(flow.state.session, isNotNull);
  });

  testWidgets('sans PIN en mémoire, le code vérifié ramène à la connexion', (
    tester,
  ) async {
    repository = StubAuthRepository();
    flow = AuthFlowBloc(repository)
      ..add(const AuthFlowNewDeviceRequested('aicha@example.com'));
    await pumpPage(tester);

    // Le défi doit exister avant qu'un code puisse être vérifié : on
    // laisse la demande partir plutôt que d'empiler les deux événements.
    await tester.tap(find.text('Recevoir le code OTP'));
    await tester.pumpAndSettle();

    flow.add(const AuthFlowCodeSubmitted('123456'));
    await tester.pumpAndSettle();

    // Personne n'a encore saisi de PIN : l'app le demande, et le jeton
    // de vérification part avec cette connexion-là.
    expect(flow.state.step, AuthFlowStep.email);
    expect(flow.state.verification, isNotNull);
  });
}
