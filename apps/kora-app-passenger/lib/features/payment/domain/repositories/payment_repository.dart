import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/payment_attempt.dart';
import '../entities/payment_catalog.dart';

/// Couche DOMAIN : contrat du paiement.
///
/// L'annulation viendra ici quand elle sera branchée : on n'écrit pas de
/// contrat que personne n'appelle encore.
abstract class PaymentRepository {
  /// Les moyens de paiement que le serveur accepte en ce moment.
  ///
  /// Un catalogue vide est un **succès** : c'est un serveur qui n'a rien
  /// d'activé, pas une panne. L'écran a de quoi le dire, distinct de son
  /// état d'erreur.
  Future<Either<Failure, PaymentCatalog>> catalog();

  /// Lance un paiement pour une réservation retenue.
  ///
  /// [walletAmount] et [pointsAmount] règlent tout ou partie du billet
  /// sur le Wallet. Le serveur en déduit le reste à encaisser et refuse
  /// l'ensemble si les comptes ne tombent pas juste : c'est lui qui
  /// arrête la répartition, l'app ne fait que proposer la sienne.
  ///
  /// [method] vaut `mobile_money` ou `card`. [network] et [phone] ne
  /// concernent que les encaisseurs qui poussent eux-mêmes la demande
  /// sur le téléphone du payeur : KKiaPay les recueille dans son widget,
  /// et une carte n'en a pas.
  Future<Either<Failure, PaymentAttempt>> initiate({
    required String reservationId,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? network,
    String? phone,
    int? walletAmount,
    int? pointsAmount,
  });

  /// Relit l'état d'un paiement.
  Future<Either<Failure, PaymentAttempt>> status(String paymentId);

  /// Demande au serveur d'interroger le provider maintenant.
  ///
  /// [transactionId] est celui que le widget KKiaPay a rendu, quand
  /// c'est lui qui a encaissé. Le serveur reste seul juge : il
  /// l'interroge chez KKiaPay avant de conclure.
  Future<Either<Failure, PaymentAttempt>> verify(
    String paymentId, {
    String? transactionId,
  });
}
