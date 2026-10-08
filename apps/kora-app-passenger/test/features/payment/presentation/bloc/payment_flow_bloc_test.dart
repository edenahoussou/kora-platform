// Le paiement lancé, puis attendu.
//
// L'app ne conclut jamais un paiement : elle relit l'état que le serveur
// tient à jour. Ces tests vérifient précisément cela — qui appelle quoi,
// et ce que l'écran a le droit d'en déduire.

import 'package:flutter_test/flutter_test.dart';
import 'package:kora_app_passenger/core/error/failures.dart';
import 'package:kora_app_passenger/features/payment/domain/entities/payment_attempt.dart';
import 'package:kora_app_passenger/features/payment/presentation/bloc/payment_flow_bloc.dart';

import '../../../../support/seat_hold_harness.dart';

/// Un paiement que l'opérateur refuse.
const kRefusedAttempt = PaymentAttempt(
  id: 'pay-refuse',
  status: PaymentAttemptStatus.failed,
  failureMessage: 'Paiement refusé par l’opérateur.',
);

void main() {
  _retryAfterFailure();

  const started = PaymentFlowStarted(
    reservationId: 'res-1',
    provider: 'fedapay',
    network: 'mtn_momo',
    phone: '+2290152235689',
  );

  const confirmed = PaymentAttempt(
    id: 'pay-1',
    status: PaymentAttemptStatus.confirmed,
    nextAction: 'show_tickets',
  );

  test('lance le paiement chez l’encaisseur du réseau choisi', () async {
    final repository = StubPaymentRepository();
    final bloc = PaymentFlowBloc(repository);
    addTearDown(bloc.close);

    bloc.add(started);
    await bloc.stream.firstWhere((state) => state.isPending);

    // Le réseau est celui du voyageur, le provider celui qui l'achemine.
    expect(repository.calls, contains('initiate:fedapay/mtn_momo'));
    expect(bloc.state.attempt!.reference, 'FEDA-AB12CD34');
  });

  test(
    'un second appui pendant l’appel n’ouvre pas un deuxième paiement',
    () async {
      // Le double débit est la faute qu'on ne rattrape pas.
      final repository = StubPaymentRepository();
      final bloc = PaymentFlowBloc(repository);
      addTearDown(bloc.close);

      bloc
        ..add(started)
        ..add(started);
      await bloc.stream.firstWhere((state) => state.isPending);

      expect(
        repository.calls.where((call) => call.startsWith('initiate')).length,
        1,
      );
    },
  );

  test('un lancement refusé remonte le message du serveur', () async {
    final bloc = PaymentFlowBloc(
      StubPaymentRepository(
        initiateFailure: const ServerFailure('Fournisseur non configuré.'),
      ),
    );
    addTearDown(bloc.close);

    bloc.add(started);
    await bloc.stream.firstWhere((state) => state.hasFailed);

    expect(bloc.state.errorMessage, 'Fournisseur non configuré.');
    // Rien à attendre : l'écran ne doit pas partir en salle d'attente.
    expect(bloc.state.isPending, isFalse);
  });

  test('la relecture confirme le paiement sans que l’app en décide', () async {
    // Elle passe par `verify` et non par `status` : le premier interroge le
    // fournisseur, le second se contente de relire ce que la base dit déjà.
    // Sans webhook, cette base reste à `pending`, et la relecture tournait
    // sur une valeur figée jusqu'à l'expiration du blocage.
    final repository = StubPaymentRepository(
      attempt: const PaymentAttempt(
        id: 'pay-1',
        status: PaymentAttemptStatus.pending,
        nextAction: 'verify',
        // Court, pour ne pas faire attendre la suite de tests.
        pollingInterval: Duration(milliseconds: 20),
      ),
      statuses: const [confirmed],
    );
    final bloc = PaymentFlowBloc(repository);
    addTearDown(bloc.close);

    bloc.add(started);
    await bloc.stream.firstWhere((state) => state.isConfirmed);

    expect(repository.calls, contains('verify'));
    expect(repository.calls, isNot(contains('status')));
    expect(bloc.state.attempt!.hasTickets, isTrue);
  });

  test('recommencer après un refus ouvre un nouvel essai', () async {
    // Le serveur rend le paiement existant pour toute clé déjà vue, même
    // refusé. Sans numéro d'essai dans la clé, « Changer de moyen » puis
    // « Payer » ressortait l'ancien refus sans rien envoyer à l'opérateur.
    final bloc = PaymentFlowBloc(
      StubPaymentRepository(
        attempt: const PaymentAttempt(
          id: 'pay-1',
          status: PaymentAttemptStatus.failed,
        ),
      ),
    );
    addTearDown(bloc.close);

    expect(bloc.state.round, 0);

    bloc.add(started);
    await bloc.stream.firstWhere((state) => state.hasFailed);
    // Le refus consomme l'essai : la demande suivante ne doit pas
    // ressortir celle-ci, quel que soit le chemin emprunté pour y
    // revenir. Seul un paiement **vivant** garde son numéro, et c'est lui
    // que protège le garde du double appel.
    expect(bloc.state.round, 1);

    bloc.add(const PaymentFlowReset());
    await bloc.stream.firstWhere((state) => state.attempt == null);

    expect(bloc.state.round, 2);
    expect(bloc.state.hasFailed, isFalse);
  });

  test('« J’ai payé » demande une vérification, il ne conclut rien', () async {
    final repository = StubPaymentRepository(statuses: const [confirmed]);
    final bloc = PaymentFlowBloc(repository);
    addTearDown(bloc.close);

    bloc.add(started);
    await bloc.stream.firstWhere((state) => state.isPending);

    bloc.add(const PaymentFlowVerifyRequested());
    await bloc.stream.firstWhere((state) => state.isConfirmed);

    // C'est le serveur qui interroge le provider, pas l'app.
    expect(repository.calls, contains('verify'));
  });
}

/// Ce qui se passe après un refus : le voyageur corrige son numéro et
/// réessaie, sur le même écran.
void _retryAfterFailure() {
  group('après un refus', () {
    late StubPaymentRepository repository;

    setUp(() => repository = StubPaymentRepository(attempt: kRefusedAttempt));

    test('un autre numéro part sous une clé neuve', () async {
      final bloc = PaymentFlowBloc(repository);
      addTearDown(bloc.close);

      bloc.add(
        const PaymentFlowStarted(
          reservationId: 'res-1',
          provider: 'fedapay',
          network: 'mtn_momo',
          phone: '+2290191003606',
        ),
      );
      await bloc.stream.firstWhere((state) => state.hasFailed);

      bloc.add(
        const PaymentFlowStarted(
          reservationId: 'res-1',
          provider: 'fedapay',
          network: 'mtn_momo',
          phone: '+2290166000001',
        ),
      );
      await bloc.stream.firstWhere(
        (_) => repository.idempotencyKeys.length > 1,
      );

      // Deux demandes, deux clés : le serveur refuse en 422 une clé déjà
      // vue avec un contenu différent, et c'est ce qui faisait échouer le
      // second essai sans qu'il atteigne jamais l'opérateur.
      expect(repository.idempotencyKeys, hasLength(2));
      expect(
        repository.idempotencyKeys.first,
        isNot(repository.idempotencyKeys.last),
      );
      expect(repository.idempotencyKeys.last, contains('0166000001'));
    });

    test('le même numéro repart aussi, sous une clé neuve', () async {
      // Sans ça, le serveur ressortait le refus précédent — la clé étant
      // identique — et l'écran affichait un échec qui datait.
      final bloc = PaymentFlowBloc(repository);
      addTearDown(bloc.close);

      for (var essai = 0; essai < 2; essai++) {
        bloc.add(
          const PaymentFlowStarted(
            reservationId: 'res-1',
            provider: 'fedapay',
            network: 'mtn_momo',
            phone: '+2290191003606',
          ),
        );
        await bloc.stream.firstWhere(
          (_) => repository.idempotencyKeys.length > essai,
        );
      }

      expect(
        repository.idempotencyKeys.first,
        isNot(repository.idempotencyKeys.last),
      );
    });
  });
}
