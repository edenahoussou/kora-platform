import 'package:equatable/equatable.dart';

import '../../../booking/domain/entities/price.dart';

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
    this.paymentId,
    this.paymentStatus,
    this.provider,
    this.createdAt,
  });

  final String id;
  final Price amount;
  final WalletTopUpStatus status;

  /// Le paiement provider créé pour cette recharge.
  ///
  /// C'est par lui qu'on ouvre la feuille de paiement : la réponse de
  /// `POST /wallet/topups` ne porte aujourd'hui que son identifiant,
  /// pas sa charge utile — voir [WalletTopUp] dans les notes de
  /// MEZ-160 et `WalletTopUpResource` côté serveur.
  final String? paymentId;

  final String? paymentStatus;
  final String? provider;

  final DateTime? createdAt;

  /// Vrai quand la recharge attend encore le provider.
  bool get isPending => status == WalletTopUpStatus.pending;

  /// Vrai quand il y a un paiement à poursuivre.
  bool get canBeResumed => isPending && paymentId != null;

  @override
  List<Object?> get props => [
    id,
    amount,
    status,
    paymentId,
    paymentStatus,
    provider,
    createdAt,
  ];
}
