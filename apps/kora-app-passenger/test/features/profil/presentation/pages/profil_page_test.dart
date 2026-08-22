// Tests de la déconnexion, seule action branchée de l'onglet « Profil ».
//
// Le reste de l'écran attend ses features et ouvre un toast : il n'y a
// rien à en vérifier tant que les données sont figées.

import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_design_system/flutter_design_system.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/core/l10n/app_localization.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/profil/presentation/pages/profil_page.dart';

import '../../../../support/stub_auth_repository.dart';

/// Dépôt dont la déconnexion ne répond qu'à la demande, pour observer
/// l'écran pendant l'appel réseau.
class _SlowLogoutRepository extends StubAuthRepository {
  final Completer<void> completer = Completer<void>();

  @override
  Future<Either<Failure, void>> logout() async {
    calls.add('logout');
    await completer.future;
    return const Right(null);
  }
}

void main() {
  late StubAuthRepository repository;
  late AuthBloc auth;

  /// Monte l'onglet Profil avec une session ouverte, comme dans l'app.
  Future<void> pumpProfil(WidgetTester tester) async {
    tester.view.physicalSize = const Size(390 * 3, 1400 * 3);
    tester.view.devicePixelRatio = 3;
    addTearDown(tester.view.reset);

    auth = AuthBloc(repository)
      ..add(AuthSessionOpened(StubAuthRepository.session));
    addTearDown(auth.close);

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
        home: BlocProvider<AuthBloc>.value(
          value: auth,
          child: const ProfilPage(),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('la déconnexion révoque la session', (tester) async {
    repository = StubAuthRepository();
    await pumpProfil(tester);

    await tester.tap(find.text('Se déconnecter'));
    await tester.pumpAndSettle();

    // Le token est révoqué côté serveur, et l'app n'a plus de session :
    // c'est ce que lit le garde de navigation pour revenir à l'accueil.
    expect(repository.calls, ['logout']);
    expect(auth.state.status, AuthStatus.unauthenticated);
    expect(auth.state.session, isNull);
  });

  testWidgets('déconnecte même si le serveur refuse', (tester) async {
    // Le dépôt purge le stockage quoi qu'il arrive : rester « connecté »
    // afficherait un compte auquel l'app n'a plus accès.
    repository = StubAuthRepository(
      failure: const ServerFailure('Serveur injoignable'),
    );
    await pumpProfil(tester);

    await tester.tap(find.text('Se déconnecter'));
    await tester.pumpAndSettle();

    expect(auth.state.status, AuthStatus.unauthenticated);
    expect(auth.state.errorMessage, 'Serveur injoignable');
  });

  testWidgets('un second appui ne relance pas la déconnexion', (tester) async {
    final slow = _SlowLogoutRepository();
    repository = slow;
    await pumpProfil(tester);

    await tester.tap(find.text('Se déconnecter'));
    await tester.pump();

    // Pendant l'appel, le lien annonce ce qu'il fait et ne répond plus.
    expect(find.text('Déconnexion…'), findsOneWidget);
    expect(find.text('Se déconnecter'), findsNothing);

    await tester.tap(find.text('Déconnexion…'));
    await tester.pump();

    slow.completer.complete();
    await tester.pumpAndSettle();

    expect(slow.calls, ['logout']);
  });
}
