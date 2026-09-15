import '../../../../core/result/result.dart';
import '../../../ops/domain/entities/passenger.dart';

/// Couche DOMAIN : charger le manifeste d'un départ, avec repli local
/// automatique hors ligne — voir MEZ-91 / document.md § Manifeste.
abstract class ManifesteRepository {
  /// Tente le réseau d'abord ; en cas d'échec réseau (pas de connexion),
  /// retombe sur le dernier manifeste mis en cache pour ce départ — un
  /// `Result.success` dans les deux cas, jamais un échec pour la seule
  /// absence de réseau (voir critère d'acceptation MEZ-91 "utiliser le
  /// manifeste local en cas de coupure réseau"). Ne renvoie
  /// `Result.failure` que si le réseau échoue **et** qu'aucun cache
  /// n'existe pour ce départ, ou pour un échec technique non lié au
  /// réseau (authentification, serveur).
  Future<Result<List<Passenger>>> load(String departureUuid);
}
