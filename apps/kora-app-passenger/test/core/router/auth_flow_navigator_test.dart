// La navigation du parcours d'authentification.
//
// Les écrans n'envoient que des événements ; c'est ce navigateur qui
// décide où ils mènent. Le cas qu'il faut tenir : un code demandé une
// seconde fois depuis le début du parcours.

import 'package:flutter/material.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';
import 'package:kora_app_passenger/core/router/app_router.dart';
import 'package:kora_app_passenger/core/router/auth_flow_navigator.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_flow_bloc.dart';

import '../../support/stub_auth_repository.dart';

/// Le parcours et son routeur, montés ensemble.
typedef _Flow = ({AuthFlowBloc bloc, GoRouter router});

void main() {
  /// Monte le navigateur au-dessus de deux écrans nus : le formulaire et
  /// la saisie du code. Le reste du parcours ne l'intéresse pas.
  ///
  /// Tout est construit **dans le test** : un Bloc né dans un `setUp`
  /// n'appartient pas à la zone asynchrone du test, et ses `await`
  /// n'aboutissent jamais sous `pumpAndSettle`.
  Future<_Flow> pumpFlow(WidgetTester tester) async {
    final bloc = AuthFlowBloc(StubAuthRepository());
    addTearDown(bloc.close);

    final router = GoRouter(
      initialLocation: AppRouter.signupPath,
      routes: [
        ShellRoute(
          builder: (context, state, child) => AuthFlowNavigator(child: child),
          routes: [
            GoRoute(
              path: AppRouter.signupPath,
              builder: (context, state) =>
                  const Scaffold(body: Text('inscription')),
            ),
            GoRoute(
              path: AppRouter.otpPath,
              builder: (context, state) => const Scaffold(body: Text('code')),
            ),
          ],
        ),
      ],
    );
    addTearDown(router.dispose);

    await tester.pumpWidget(
      MultiBlocProvider(
        providers: [
          BlocProvider<AuthFlowBloc>.value(value: bloc),
          // Le navigateur confie la session ouverte à ce bloc-là.
          BlocProvider<AuthBloc>(create: (_) => AuthBloc(StubAuthRepository())),
        ],
        child: MaterialApp.router(routerConfig: router),
      ),
    );
    await tester.pumpAndSettle();

    return (bloc: bloc, router: router);
  }

  /// Demande un code pour [identifier], comme le fait le formulaire.
  Future<void> askCode(
    WidgetTester tester,
    AuthFlowBloc bloc,
    String identifier,
  ) async {
    bloc.add(AuthFlowIdentifierSubmitted(identifier: identifier));
    await tester.pumpAndSettle();
  }

  testWidgets('un code demandé ouvre l’écran de saisie', (tester) async {
    final flow = await pumpFlow(tester);

    await askCode(tester, flow.bloc, '+22997000001');

    expect(find.text('code'), findsOneWidget);
  });

  testWidgets('un second numéro rouvre l’écran de saisie', (tester) async {
    // Le bug : revenu en arrière sans saisir le premier code, le
    // voyageur retapait un autre numéro et restait sur le formulaire —
    // l'étape valait déjà `code`, et la navigation n'écoutait que les
    // changements d'étape. Le code partait sans écran pour le saisir.
    final flow = await pumpFlow(tester);

    await askCode(tester, flow.bloc, '+22997000001');
    expect(find.text('code'), findsOneWidget);

    flow.router.pop();
    await tester.pumpAndSettle();
    expect(find.text('inscription'), findsOneWidget);

    await askCode(tester, flow.bloc, '+22997000002');

    expect(find.text('code'), findsOneWidget);
  });

  testWidgets('redemander le code n’empile pas un second écran', (
    tester,
  ) async {
    // « Je n'ai pas reçu le code » crée un nouveau défi, sur l'écran où
    // l'on se trouve déjà : l'empiler mettrait deux écrans identiques
    // l'un sur l'autre, et le retour en arrière ferait du surplace.
    final flow = await pumpFlow(tester);

    await askCode(tester, flow.bloc, '+22997000001');
    expect(find.text('code'), findsOneWidget);

    flow.bloc.add(const AuthFlowCodeResent());
    await tester.pumpAndSettle();
    expect(find.text('code'), findsOneWidget);

    flow.router.pop();
    await tester.pumpAndSettle();

    expect(find.text('inscription'), findsOneWidget);
  });
}
