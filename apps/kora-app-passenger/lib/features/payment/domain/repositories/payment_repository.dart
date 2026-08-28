import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/payment_catalog.dart';

/// Couche DOMAIN : contrat du paiement.
///
/// Ne porte pour l'instant que le catalogue — l'initiation, la
/// vérification et l'annulation viendront ici au fur et à mesure qu'elles
/// seront branchées. On n'écrit pas de contrat que personne n'appelle
/// encore.
abstract class PaymentRepository {
  /// Les moyens de paiement que le serveur accepte en ce moment.
  ///
  /// Un catalogue vide est un **succès** : c'est un serveur qui n'a rien
  /// d'activé, pas une panne. L'écran a de quoi le dire, distinct de son
  /// état d'erreur.
  Future<Either<Failure, PaymentCatalog>> catalog();
}
