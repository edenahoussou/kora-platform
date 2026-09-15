import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/payment_attempt.dart';
import '../../domain/repositories/payment_repository.dart';

part 'payment_flow_event.dart';
part 'payment_flow_state.dart';

/// Le paiement en cours : son lancement, puis l'attente de sa
/// confirmation.
///
/// **L'app ne conclut jamais un paiement.** Elle lance la demande, puis
/// relit l'état que le serveur tient à jour ; c'est lui qui interroge le
/// provider et décide. Un écran qui se déclarerait payé sur la seule foi
/// d'un retour d'opérateur émettrait un billet que personne n'a réglé.
///
/// La relecture suit la cadence que le serveur annonce
/// (`polling_interval_seconds`), jamais une valeur choisie ici : lui seul
/// sait ce que son provider tolère comme fréquence d'appel.
class PaymentFlowBloc extends Bloc<PaymentFlowEvent, PaymentFlowState> {
  PaymentFlowBloc(this._repository) : super(const PaymentFlowState()) {
    on<PaymentFlowStarted>(_onStarted);
    on<PaymentFlowPolled>(_onPolled);
    on<PaymentFlowVerifyRequested>(_onVerifyRequested);
    on<PaymentFlowReset>(_onReset);
  }

  final PaymentRepository _repository;

  Timer? _poller;

  @override
  Future<void> close() {
    _poller?.cancel();
    return super.close();
  }

  Future<void> _onStarted(
    PaymentFlowStarted event,
    Emitter<PaymentFlowState> emit,
  ) async {
    // Un paiement déjà lancé ne se relance pas : ni pendant que l'appel
    // vole, ni une fois la demande partie chez l'opérateur. La clé
    // d'idempotence protège le serveur, mais le double débit commence
    // toujours par un double appel — autant ne pas le passer.
    //
    // Pour recommencer après un échec, il faut passer par
    // [PaymentFlowReset], qui est un geste explicite.
    if (state.isStarting || state.attempt != null) return;

    emit(
      state.copyWith(status: PaymentFlowStatus.starting, clearFailure: true),
    );

    final result = await _repository.initiate(
      reservationId: event.reservationId,
      provider: event.provider,
      network: event.network,
      phone: event.phone,
      idempotencyKey: event.idempotencyKey,
    );

    result.fold(
      (failure) => emit(
        state.copyWith(status: PaymentFlowStatus.failure, failure: failure),
      ),
      (attempt) {
        emit(_settled(attempt));
        if (attempt.isPending) _startPolling(attempt.pollingInterval);
      },
    );
  }

  Future<void> _onPolled(
    PaymentFlowPolled event,
    Emitter<PaymentFlowState> emit,
  ) async {
    final attempt = state.attempt;
    if (attempt == null || !attempt.isPending) return;

    // `verify` et non `status` : le premier interroge le fournisseur et met
    // l'état à jour, le second se contente de relire ce que la base dit
    // déjà. Tant qu'aucun webhook n'arrive, cette base reste à `pending`
    // — et la relecture tournait donc indéfiniment sur une valeur qui ne
    // bougeait pas, jusqu'à l'expiration du blocage.
    final result = await _repository.verify(attempt.id);

    result.fold(
      // Une relecture qui échoue n'est pas un paiement qui échoue : le
      // réseau du voyageur peut tomber pendant qu'il compose son code.
      // On garde l'état précédent et on retentera au prochain tour.
      (_) {},
      (refreshed) {
        emit(_settled(refreshed));
        if (!refreshed.isPending) _poller?.cancel();
      },
    );
  }

  Future<void> _onVerifyRequested(
    PaymentFlowVerifyRequested event,
    Emitter<PaymentFlowState> emit,
  ) async {
    final attempt = state.attempt;
    if (attempt == null) return;

    emit(state.copyWith(isVerifying: true, clearFailure: true));

    final result = await _repository.verify(attempt.id);

    result.fold(
      (failure) => emit(state.copyWith(isVerifying: false, failure: failure)),
      (refreshed) {
        emit(_settled(refreshed).copyWith(isVerifying: false));
        if (!refreshed.isPending) _poller?.cancel();
      },
    );
  }

  void _onReset(PaymentFlowReset event, Emitter<PaymentFlowState> emit) {
    _poller?.cancel();
    // Tout repart de zéro, sauf le numéro d'essai : c'est lui qui fera du
    // prochain « Payer » une demande neuve plutôt qu'une relecture du refus.
    emit(PaymentFlowState(round: state.round + 1));
  }

  /// L'état correspondant à un paiement fraîchement lu.
  PaymentFlowState _settled(PaymentAttempt attempt) {
    return state.copyWith(
      status: switch (attempt.status) {
        PaymentAttemptStatus.confirmed => PaymentFlowStatus.confirmed,
        PaymentAttemptStatus.pending => PaymentFlowStatus.pending,
        _ => PaymentFlowStatus.failure,
      },
      attempt: attempt,
      clearFailure: true,
    );
  }

  void _startPolling(Duration interval) {
    _poller?.cancel();
    _poller = Timer.periodic(interval, (_) => add(const PaymentFlowPolled()));
  }
}
