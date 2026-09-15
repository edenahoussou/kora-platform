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

void main() {
  const started = PaymentFlowStarted(
    reservationId: 'res-1',
    provider: 'fedapay',
    network: 'mtn_momo',
    phone: '+2290152235689',
    idempotencyKey: 'kora-res-1-mtn_momo',
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
    // L'essai en cours garde son numéro : un double appui reste protégé.
    expect(bloc.state.round, 0);

    bloc.add(const PaymentFlowReset());
    await bloc.stream.firstWhere((state) => state.attempt == null);

    expect(bloc.state.round, 1);
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
