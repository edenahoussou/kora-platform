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
  });

  final PaymentFlowStatus status;

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
  }) {
    return PaymentFlowState(
      status: status ?? this.status,
      attempt: attempt ?? this.attempt,
      // `copyWith` ne sait pas remettre un champ à `null` : un nouvel
      // essai doit pouvoir effacer l'échec précédent, sinon l'écran garde
      // un message qui ne vaut plus rien.
      failure: clearFailure ? null : failure ?? this.failure,
      isVerifying: isVerifying ?? this.isVerifying,
    );
  }

  @override
  List<Object?> get props => [status, attempt, failure, isVerifying];
}
