part of 'payment_flow_bloc.dart';

/// Où en est le paiement du point de vue des écrans.
enum PaymentFlowStatus {
  /// Rien de lancé.
  idle,

  /// `POST payments/initiate` est parti, sa réponse se fait attendre.
  starting,

  /// La demande est chez l'opérateur ; le voyageur compose son code.
  pending,

  confirmed,
  failure,
}

/// Couche PRESENTATION : le paiement en cours.
class PaymentFlowState extends Equatable {
  const PaymentFlowState({
    this.status = PaymentFlowStatus.idle,
    this.attempt,
    this.failure,
    this.isVerifying = false,
    this.round = 0,
  });

  final PaymentFlowStatus status;

  /// Le numéro de l'essai de paiement, qui entre dans la clé d'idempotence.
  ///
  /// Le serveur rend le paiement existant pour toute clé déjà vue — même
  /// refusé. Une clé figée faisait donc de chaque nouvel essai une relecture
  /// de l'ancien refus : l'écran d'échec revenait aussitôt, sans que rien ne
  /// parte chez l'opérateur, et un autre numéro était rejeté comme « clé
  /// déjà utilisée ». Le compteur n'avance qu'avec [PaymentFlowReset], le
  /// geste explicite de recommencer : deux appuis sur « Payer » gardent la
  /// même clé, et c'est tout ce que l'idempotence doit protéger.
  final int round;

  /// Le paiement tel que le serveur le décrit. `null` avant le lancement.
  final PaymentAttempt? attempt;

  /// Renseigné quand le lancement ou une vérification explicite a échoué.
  ///
  /// Une relecture automatique ratée ne le remplit pas : le réseau du
  /// voyageur peut tomber pendant qu'il compose son code, et afficher une
  /// erreur à ce moment-là lui ferait croire que son paiement a échoué.
  final Failure? failure;

  /// Une vérification demandée par le voyageur est en cours.
  final bool isVerifying;

  bool get isStarting => status == PaymentFlowStatus.starting;
  bool get isPending => status == PaymentFlowStatus.pending;
  bool get isConfirmed => status == PaymentFlowStatus.confirmed;
  bool get hasFailed => status == PaymentFlowStatus.failure;

  /// Le message à montrer : celui du serveur s'il en donne un, sinon celui
  /// de l'échec technique.
  String? get errorMessage => attempt?.failureMessage ?? failure?.message;

  PaymentFlowState copyWith({
    PaymentFlowStatus? status,
    PaymentAttempt? attempt,
    Failure? failure,
    bool clearFailure = false,
    bool? isVerifying,
    int? round,
  }) {
    return PaymentFlowState(
      status: status ?? this.status,
      attempt: attempt ?? this.attempt,
      // `copyWith` ne sait pas remettre un champ à `null` : un nouvel
      // essai doit pouvoir effacer l'échec précédent, sinon l'écran garde
      // un message qui ne vaut plus rien.
      failure: clearFailure ? null : failure ?? this.failure,
      isVerifying: isVerifying ?? this.isVerifying,
      // Le numéro d'essai ne se remet pas à zéro tout seul : il compte les
      // demandes déjà parties pour cette réservation, et c'est lui qui
      // fait d'un nouvel essai une demande neuve aux yeux du serveur.
      round: round ?? this.round,
    );
  }

  @override
  List<Object?> get props => [status, attempt, failure, isVerifying, round];
}
