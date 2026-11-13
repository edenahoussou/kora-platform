import 'package:equatable/equatable.dart';

import '../../../booking/domain/entities/price.dart';
import '../../../payment/domain/entities/payment_attempt.dart';

/// Où en est une recharge, côté serveur.
enum WalletTopUpStatus {
  pending,
  confirmed,
  failed;

  static WalletTopUpStatus of(String raw) => switch (raw) {
    'confirmed' => WalletTopUpStatus.confirmed,
    'failed' => WalletTopUpStatus.failed,
    _ => WalletTopUpStatus.pending,
  };

  bool get isSettled => this != WalletTopUpStatus.pending;
}

/// Couche DOMAIN : une recharge du Wallet et le paiement qui la porte.
///
/// Une recharge n'est pas un paiement de billet : elle n'a pas de
/// réservation, et le solde ne bouge **qu'après** confirmation du
/// provider. L'app n'avance rien : afficher l'argent dès l'envoi
/// montrerait une somme que le serveur n'a pas encore encaissée.
class WalletTopUp extends Equatable {
  const WalletTopUp({
    required this.id,
    required this.amount,
    required this.status,
    this.attempt,
    this.createdAt,
  });

  final String id;
  final Price amount;
  final WalletTopUpStatus status;

  /// Le paiement provider créé pour cette recharge, avec de quoi le
  /// poursuivre.
  ///
  /// `POST /wallet/topups` ne rendait que son identifiant : il fallait
  /// relire le paiement ailleurs pour obtenir la configuration du
  /// widget et la conduite à tenir. Le serveur les rend désormais dans
  /// la même réponse, et ce second appel a disparu.
  final PaymentAttempt? attempt;

  final DateTime? createdAt;

  /// Vrai quand la recharge attend encore le provider.
  bool get isPending => status == WalletTopUpStatus.pending;

  /// L'identifiant du paiement, quand il y en a un.
  String? get paymentId => attempt?.id;

  /// Vrai quand il y a un paiement à poursuivre.
  bool get canBeResumed => isPending && attempt != null;

  @override
  List<Object?> get props => [id, amount, status, attempt, createdAt];
}
