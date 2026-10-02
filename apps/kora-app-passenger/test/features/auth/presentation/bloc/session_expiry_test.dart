// Une session refusée par le serveur ramène à la connexion.
//
// Le token vaut sept jours et rien ne le renouvelle. L'app vérifiait sa
// péremption au seul lancement : restée ouverte, elle traversait
// l'échéance sans le voir et affichait « Impossible de charger
// l'accueil · Unauthenticated », avec un bouton « Réessayer » qui ne
// pouvait que réessayer en vain.

import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/network/session_expiry.dart';
import 'package:kora_app_passenger/features/auth/presentation/bloc/auth_bloc.dart';

import '../../../../support/stub_auth_repository.dart';

/// Un serveur qui refuse tout : c'est la réponse d'un token périmé.
class _RefusingAdapter implements HttpClientAdapter {
  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async => ResponseBody.fromString(
    '{"message":"Unauthenticated."}',
    401,
    headers: {
      Headers.contentTypeHeader: [Headers.jsonContentType],
    },
  );

  @override
  void close({bool force = false}) {}
}

/// Joue un appel refusé, avec ou sans token, et rend la main.
Future<void> _call(SessionExpiry expiry, {required bool withToken}) async {
  final dio = Dio(BaseOptions(baseUrl: 'https://exemple.test'))
    ..httpClientAdapter = _RefusingAdapter()
    ..interceptors.add(SessionExpiredInterceptor(expiry));

  try {
    await dio.get<dynamic>(
      '/bus/cities',
      options: Options(
        headers: withToken ? {'Authorization': 'Bearer jeton'} : null,
      ),
    );
  } on DioException {
    // Attendu : c'est le refus lui-même.
  }
}

void main() {
  late SessionExpiry expiry;

  setUp(() => expiry = SessionExpiry());
  tearDown(() => expiry.close());

  test(
    'un 401 sur une requête authentifiée signale la fin de session',
    () async {
      final signalled = expiry.expirations.first;

      await _call(expiry, withToken: true);

      await expectLater(signalled, completes);
    },
  );

  test('un 401 sans token ne signale rien', () async {
    var signalled = false;
    final subscription = expiry.expirations.listen((_) => signalled = true);
    addTearDown(subscription.cancel);

    // C'est le cas d'un PIN erroné : `auth/login` part sans
    // `Authorization`, et renvoyer l'utilisateur à la connexion qu'il a
    // sous les yeux n'aurait aucun sens.
    await _call(expiry, withToken: false);

    expect(signalled, isFalse);
  });

  test('le bloc se déconnecte et purge le stockage', () async {
    final repository = StubAuthRepository();
    final bloc = AuthBloc(repository)..watchExpiry(expiry);
    addTearDown(bloc.close);

    bloc.add(AuthSessionOpened(StubAuthRepository.session));
    await bloc.stream.firstWhere((state) => state.isAuthenticated);

    expiry.notify();
    final state = await bloc.stream.firstWhere(
      (state) => !state.isAuthenticated,
    );

    expect(state.status, AuthStatus.unauthenticated);
    // Le token mort part du téléphone : le garder ferait rejouer les 401
    // au prochain lancement.
    expect(repository.calls, contains('logout'));
  });
}
