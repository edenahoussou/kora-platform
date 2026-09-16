import '../../../../core/result/result.dart';
import '../entities/manifest_snapshot.dart';

/// Couche DOMAIN : charger le manifeste d'un départ, avec vérification de
/// fraîcheur explicite et repli local automatique hors ligne — voir
/// MEZ-92 / document.md § Manifeste.
abstract class ManifesteRepository {
  /// - Hors ligne : sert le cache s'il existe (`Result.success`, jamais
  ///   un échec pour la seule absence de réseau), avec `isStale: true`
  ///   (impossible à vérifier auprès du serveur) — ou `Result.failure`
  ///   si aucun cache n'existe pour ce départ.
  /// - En ligne, jamais chargé pour ce départ : téléchargement complet.
  /// - En ligne, déjà en cache : vérifie d'abord la version
  ///   (`POST /ops/sync/manifest-versions`, un aller-retour léger) —
  ///   retélécharge seulement si elle a changé, sert le cache tel quel
  ///   sinon (`isFromCache: true`, `isStale: false` : vérifié à jour).
  Future<Result<ManifestSnapshot>> load(String departureUuid);
}
