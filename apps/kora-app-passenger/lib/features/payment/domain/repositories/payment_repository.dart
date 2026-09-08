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

  /// Lance un paiement Mobile Money pour une réservation retenue.
  ///
  /// Le serveur pousse alors la demande de code sur le téléphone du
  /// payeur ; il n'y a aucune page à ouvrir côté app.
  Future<Either<Failure, PaymentAttempt>> initiate({
    required String reservationId,
    required String provider,
    required String network,
    required String phone,
    required String idempotencyKey,
  });

  /// Relit l'état d'un paiement.
  Future<Either<Failure, PaymentAttempt>> status(String paymentId);

  /// Demande au serveur d'interroger le provider maintenant.
  Future<Either<Failure, PaymentAttempt>> verify(String paymentId);
}
