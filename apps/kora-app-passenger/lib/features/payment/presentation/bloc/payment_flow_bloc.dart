import 'dart:async';

import 'package:dartz/dartz.dart';
import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../../wallet/domain/repositories/wallet_repository.dart';
import '../../data/datasources/pending_payment_store.dart';
import '../../domain/entities/payment_attempt.dart';
import '../../domain/entities/payment_catalog.dart';
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
  PaymentFlowBloc(this._repository, [this._pending, this._wallet])
    : super(const PaymentFlowState()) {
    on<PaymentFlowStarted>(_onStarted);
    on<PaymentFlowPolled>(_onPolled);
    on<PaymentFlowVerifyRequested>(_onVerifyRequested);
    on<PaymentFlowResumed>(_onResumed);
    on<PaymentFlowAbandoned>(_onAbandoned);
    on<PaymentFlowReset>(_onReset);
  }

  final PaymentRepository _repository;

  /// La trace du paiement en cours, d'un lancement à l'autre.
  ///
  /// Facultative : les tests de bloc n'ont pas de stockage, et un
  /// parcours sans trace se déroule normalement — il perd seulement la
  /// reprise après fermeture de l'app.
  final PendingPaymentStore? _pending;

  /// Le dépôt du Wallet, pour ouvrir une recharge.
  ///
  /// Facultatif : un parcours qui ne recharge pas n'en a pas besoin, et
  /// les tests du paiement d'un billet s'en passent.
  final WalletRepository? _wallet;

  Timer? _poller;

  /// Ce qui distingue les demandes de **ce lancement-ci** de l'app.
  ///
  /// La clé d'idempotence ne décrivait que la demande : même montant,
  /// même passerelle, même répartition donnaient la même clé d'un
  /// lancement à l'autre. Recharger 5 000 F par KKiaPay une seconde
  /// fois rejouait donc la recharge de la veille : le serveur rendait
  /// le paiement **déjà réglé**, l'app affichait « Recharge confirmée »
  /// sans que la feuille de paiement s'ouvre, et le solde ne bougeait
  /// pas d'un franc.
  ///
  /// Le filet qui reste contre le double débit est celui qui compte :
  /// une demande en vol n'est jamais relancée (voir [_onStarted]), et
  /// la clé reste stable tant que le voyageur réessaie la même.
  final String _launch = DateTime.now().microsecondsSinceEpoch.toRadixString(
    36,
  );

  @override
  Future<void> close() {
    _poller?.cancel();
    return super.close();
  }

  Future<void> _onStarted(
    PaymentFlowStarted event,
    Emitter<PaymentFlowState> emit,
  ) async {
    // Un paiement **en vie** ne se relance pas : ni pendant que l'appel
    // vole, ni tant que la demande suit son cours chez l'opérateur. Le
    // double débit commence toujours par un double appel, et la clé
    // d'idempotence n'est qu'un filet.
    //
    // Un paiement **refusé**, lui, ne retient rien : le voyageur corrige
    // son numéro et réessaie. Le lui interdire ici le laissait appuyer
    // sur « Payer » sans que rien ne parte.
    //
    // Et ce verrou ne vaut que pour **la réservation que ce paiement
    // règle**. Le bloc vit au niveau du parcours et reprend, à l'entrée,
    // le paiement laissé en attente au lancement précédent : sans cette
    // condition, celui d'une réservation abandonnée interdisait d'en
    // payer une nouvelle, et « Payer » n'envoyait plus rien du tout.
    final current = state.attempt;
    final paying = state.reservationId;
    // Une recharge n'a pas de réservation : son verrou tient à la
    // demande elle-même, pas à ce qu'elle règle.
    final source = event.reservationId ?? topUpTag(event.topUpAmount);
    final same = paying == null || paying == source;
    // Une recharge **confirmée** ne ferme pas la porte à la suivante :
    // remettre deux fois le même montant est banal, alors qu'un billet
    // ne se paie qu'une fois. Sans cette exception, « Payer » restait
    // sans effet après une recharge réussie du même montant.
    final again = event.topUpAmount != null && (current?.isConfirmed ?? false);
    if (state.isStarting ||
        (same && current != null && !current.isRefused && !again)) {
      return;
    }

    emit(
      state.copyWith(
        status: PaymentFlowStatus.starting,
        clearFailure: true,
        // Retenue dès le lancement : c'est elle que la trace de reprise
        // rattachera au paiement si l'app se ferme avant le verdict.
        reservationId: source,
        // Nouvelle recharge, clé neuve : sinon le serveur rejouerait
        // celle qu'il vient de confirmer.
        round: again ? state.round + 1 : state.round,
        clearAttempt: again,
      ),
    );

    final result = event.topUpAmount == null
        ? await _repository.initiate(
            reservationId: event.reservationId!,
            method: event.method,
            provider: event.provider,
            idempotencyKey: _keyFor(event),
            network: event.network,
            phone: event.phone,
            walletAmount: event.walletAmount,
            pointsAmount: event.pointsAmount,
          )
        : await _initiateTopUp(event);

    result.fold(
      // Le numéro d'essai avance : le prochain « Payer » sera une demande
      // neuve, pas la relecture de ce refus.
      (failure) => emit(
        state.copyWith(
          status: PaymentFlowStatus.failure,
          failure: failure,
          round: state.round + 1,
        ),
      ),
      (attempt) {
        emit(_settled(attempt));
        if (attempt.isPending) _startPolling(attempt.pollingInterval);
      },
    );
  }

  /// Ouvre une recharge.
  ///
  /// Un seul appel : `POST /wallet/topups` rend maintenant le paiement
  /// avec de quoi le poursuivre — la conduite à tenir, la configuration
  /// du widget, l'intervalle de relecture. L'app relisait le paiement
  /// ailleurs faute de ces champs.
  Future<Either<Failure, PaymentAttempt>> _initiateTopUp(
    PaymentFlowStarted event,
  ) async {
    final wallet = _wallet;
    if (wallet == null) {
      return const Left(
        UnexpectedFailure('La recharge n’est pas disponible ici.'),
      );
    }

    final opened = await wallet.initiateTopUp(
      amount: event.topUpAmount!,
      method: event.method,
      provider: event.provider,
      idempotencyKey: _keyFor(event),
      network: event.network,
      phone: event.phone,
    );

    return opened.fold((failure) async => Left(failure), (topUp) async {
      final paymentId = topUp.paymentId;
      if (paymentId == null) {
        // Une recharge sans paiement n'a rien à faire avancer : mieux
        // vaut le dire que de laisser l'écran attendre indéfiniment.
        return const Left(
          ServerFailure('La recharge n’a pas ouvert de paiement.'),
        );
      }

      return _repository.status(paymentId);
    });
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
    final result = await _repository.verify(
      attempt.id,
      transactionId: state.transactionId,
    );

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

    // L'identifiant du widget, quand il vient d'arriver, est retenu dans
    // l'état : la boucle pourra le renvoyer si cette vérification-ci
    // échoue sur un réseau coupé.
    emit(
      state.copyWith(
        isVerifying: true,
        clearFailure: true,
        transactionId: event.transactionId,
      ),
    );

    final result = await _repository.verify(
      attempt.id,
      transactionId: state.transactionId,
    );

    result.fold(
      (failure) => emit(state.copyWith(isVerifying: false, failure: failure)),
      (refreshed) {
        emit(_settled(refreshed).copyWith(isVerifying: false));
        if (!refreshed.isPending) _poller?.cancel();
      },
    );
  }

  /// Clôt le paiement en cours côté serveur, puis oublie tout.
  ///
  /// Le round avance : la prochaine demande portera une clé neuve, que
  /// le serveur ne confondra pas avec celle qu'on vient d'abandonner.
  Future<void> _onAbandoned(
    PaymentFlowAbandoned event,
    Emitter<PaymentFlowState> emit,
  ) async {
    final paymentId = state.attempt?.id;
    _poller?.cancel();

    if (paymentId != null) {
      // Un abandon qui échoue ne doit pas retenir le voyageur : le
      // serveur finira par expirer la demande, et l'app repart avec une
      // clé neuve de toute façon.
      await _repository.cancel(paymentId);
      if (isClosed) return;
    }

    unawaited(_pending?.clear());

    emit(PaymentFlowState(round: state.round + 1));
  }

  void _onReset(PaymentFlowReset event, Emitter<PaymentFlowState> emit) {
    _poller?.cancel();
    // Tout repart de zéro, sauf le numéro d'essai : c'est lui qui fera du
    // prochain « Payer » une demande neuve plutôt qu'une relecture du refus.
    emit(PaymentFlowState(round: state.round + 1));
  }

  /// La clé d'idempotence de cette demande-ci.
  ///
  /// **Elle décrit ce qu'on demande**, pas seulement à qui : réservation,
  /// réseau, numéro à débiter, et le numéro d'essai. Le serveur refuse en
  /// 422 une clé déjà vue avec un contenu différent — c'est ce qui
  /// arrivait après un refus, quand le voyageur revenait taper un autre
  /// numéro : la clé n'avait pas bougé, le contenu si, et l'essai suivant
  /// échouait sans jamais atteindre l'opérateur.
  ///
  /// Le numéro d'essai, lui, avance à chaque échec : il faut bien qu'un
  /// second essai sur le **même** numéro parte aussi, au lieu de
  /// ressortir le refus précédent.
  String _keyFor(PaymentFlowStarted event) {
    // Le moyen entre dans la clé : payer par carte après un refus en
    // Mobile Money est une demande neuve, pas la relecture de l'ancienne.
    //
    // **L'encaisseur aussi.** Deux passerelles servent le Mobile Money :
    // sans lui, choisir FedaPay après KKiaPay produisait la même clé, le
    // serveur rejouait la demande KKiaPay, et sa feuille s'ouvrait alors
    // que le voyageur venait d'en choisir une autre.
    final phone = (event.phone ?? '').replaceAll(RegExp(r'\D'), '');
    final network = event.network ?? '';
    final source = event.reservationId ?? topUpTag(event.topUpAmount);
    // La répartition entre dans la clé : payer 2 000 F de solde puis
    // 3 000 F sont deux demandes, pas la relecture de la même.
    final split = '${event.walletAmount ?? 0}-${event.pointsAmount ?? 0}';

    return 'kora-$_launch-$source-${event.method}-${event.provider}'
        '-$network-$phone-$split-${state.round}';
  }

  /// De quoi nommer une recharge là où le code attend une réservation.
  ///
  /// Public : les écrans s'en servent pour reconnaître *leur* paiement
  /// parmi ceux que le parcours peut reprendre.
  static String topUpTag(int? amount) => 'topup-$amount';

  /// L'état correspondant à un paiement fraîchement lu.
  /// Garde — ou efface — la trace du paiement en cours.
  ///
  /// Tant qu'il est en attente, elle permet de le reprendre si l'app est
  /// fermée pendant que le voyageur compose son code. Dès qu'il est
  /// réglé, elle part : la garder ferait proposer au prochain lancement
  /// de reprendre un paiement déjà abouti, et le voyageur croirait
  /// devoir payer deux fois.
  void _remember(PaymentAttempt attempt) {
    final store = _pending;
    if (store == null) return;

    if (!attempt.isPending) {
      unawaited(store.clear());
      return;
    }

    unawaited(
      store.save(
        PendingPayment(
          paymentId: attempt.id,
          reservationId: state.reservationId ?? '',
          transactionId: state.transactionId,
        ),
      ),
    );
  }

  /// Reprend un paiement laissé en cours au lancement précédent.
  ///
  /// On ne devine rien : on redemande son état au serveur, qui a pu le
  /// confirmer pendant que l'app était fermée — c'est même le cas le plus
  /// probable, le webhook de l'opérateur n'attendant pas l'app.
  ///
  /// Un échec de lecture ne fait rien : le réseau peut manquer au
  /// lancement, et effacer la trace priverait le voyageur de sa reprise.
  Future<void> _onResumed(
    PaymentFlowResumed event,
    Emitter<PaymentFlowState> emit,
  ) async {
    final store = _pending;
    if (store == null || state.attempt != null) return;

    final pending = store.read();
    if (pending == null) return;

    final result = await _repository.verify(
      pending.paymentId,
      transactionId: pending.transactionId,
    );

    result.fold((_) {}, (attempt) {
      emit(
        _settled(attempt).copyWith(
          reservationId: pending.reservationId,
          transactionId: pending.transactionId,
        ),
      );
      if (attempt.isPending) _startPolling(attempt.pollingInterval);
    });
  }

  PaymentFlowState _settled(PaymentAttempt attempt) {
    final failed = switch (attempt.status) {
      PaymentAttemptStatus.confirmed || PaymentAttemptStatus.pending => false,
      _ => true,
    };

    _remember(attempt);

    return state.copyWith(
      // Un paiement refusé par l'opérateur compte comme un essai consommé.
      round: failed ? state.round + 1 : state.round,
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
