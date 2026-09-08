// Le Bloc du catalogue : quand il appelle le serveur, et quand il s'en
// abstient.

import 'package:bloc_test/bloc_test.dart';
import 'package:dartz/dartz.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_catalog.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_catalog_bloc.dart';

import '../../../../support/seat_hold_harness.dart';

/// Dépôt qui compte ses appels — c'est justement ce qu'on vérifie ici.
class CountingPaymentRepository with PaymentCatalogOnly {
  CountingPaymentRepository({this.failure});

  final Failure? failure;
  int calls = 0;

  @override
  Future<Either<Failure, PaymentCatalog>> catalog() async {
    calls += 1;
    final failure = this.failure;
    return failure != null ? Left(failure) : const Right(kStubPaymentCatalog);
  }
}

/// Dépôt hors ligne au premier appel, en ligne dès qu'on le rebranche.
class FlakyPaymentRepository with PaymentCatalogOnly {
  bool online = false;

  @override
  Future<Either<Failure, PaymentCatalog>> catalog() async {
    return online
        ? const Right(kStubPaymentCatalog)
        : const Left(NetworkFailure());
  }
}

void main() {
  blocTest<PaymentCatalogBloc, PaymentCatalogState>(
    'charge le catalogue et le rend disponible aux écrans',
    build: () => PaymentCatalogBloc(StubPaymentRepository()),
    act: (bloc) => bloc.add(const PaymentCatalogRequested()),
    verify: (bloc) {
      expect(bloc.state.status, PaymentCatalogStatus.success);
      expect(bloc.state.hasMobileMoney, isTrue);
      expect(bloc.state.mobileMoneyNetworks, [
        'mtn_momo',
        'moov_flooz',
        'celtiis_cash',
      ]);
    },
  );

  blocTest<PaymentCatalogBloc, PaymentCatalogState>(
    'remonte le message d’échec pour que l’écran puisse le dire',
    build: () => PaymentCatalogBloc(
      StubPaymentRepository(failure: AuthFailure('Unauthenticated.')),
    ),
    act: (bloc) => bloc.add(const PaymentCatalogRequested()),
    verify: (bloc) {
      expect(bloc.state.hasFailed, isTrue);
      expect(bloc.state.errorMessage, 'Unauthenticated.');
      expect(bloc.state.hasMobileMoney, isFalse);
    },
  );

  test('ne rappelle pas le serveur pour un catalogue déjà chargé', () async {
    final repository = CountingPaymentRepository();
    final bloc = PaymentCatalogBloc(repository);
    addTearDown(bloc.close);

    bloc.add(const PaymentCatalogRequested());
    await bloc.stream.firstWhere(
      (state) => state.status == PaymentCatalogStatus.success,
    );

    // Chaque écran du tunnel en demande un à l'ouverture : sans ce
    // garde-fou, trois écrans d'affilée referaient le même appel.
    bloc.add(const PaymentCatalogRequested());
    await Future<void>.delayed(Duration.zero);

    expect(repository.calls, 1);
  });

  test('« Réessayer » force bien un nouvel appel', () async {
    final repository = CountingPaymentRepository();
    final bloc = PaymentCatalogBloc(repository);
    addTearDown(bloc.close);

    bloc.add(const PaymentCatalogRequested());
    await bloc.stream.firstWhere(
      (state) => state.status == PaymentCatalogStatus.success,
    );

    bloc.add(const PaymentCatalogRequested(force: true));
    await Future<void>.delayed(Duration.zero);

    expect(repository.calls, 2);
  });

  test('un rechargement réussi efface le message d’erreur précédent', () async {
    // Coupure réseau, puis retour de la connexion : le scénario exact du
    // bouton « Réessayer ».
    final repository = FlakyPaymentRepository();
    final bloc = PaymentCatalogBloc(repository);
    addTearDown(bloc.close);

    bloc.add(const PaymentCatalogRequested());
    await bloc.stream.firstWhere(
      (state) => state.status == PaymentCatalogStatus.failure,
    );
    expect(bloc.state.errorMessage, isNotNull);

    repository.online = true;
    bloc.add(const PaymentCatalogRequested(force: true));
    await bloc.stream.firstWhere(
      (state) => state.status == PaymentCatalogStatus.success,
    );

    // Sans effacement, l'écran garderait sous les yeux un message qui ne
    // vaut plus rien.
    expect(bloc.state.errorMessage, isNull);
  });
}
