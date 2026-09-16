import 'package:equatable/equatable.dart';
import '../../../ops/domain/entities/passenger.dart';

/// Le manifeste d'un départ, avec ses métadonnées de fraîcheur (MEZ-92) —
/// voir `ManifesteRepository.load`.
class ManifestSnapshot extends Equatable {
  const ManifestSnapshot({
    required this.passengers,
    this.version,
    this.fetchedAt,
    required this.isStale,
    required this.isFromCache,
  });

  final List<Passenger> passengers;

  /// Version renvoyée par le serveur (`manifestVersion`) — `null` tant
  /// qu'aucune version n'a encore été obtenue (ne devrait arriver que
  /// pour un tout premier chargement jamais résolu).
  final String? version;

  /// Horodatage du dernier téléchargement complet réussi (pas de la
  /// dernière vérification de version, qui ne retélécharge pas
  /// systématiquement) — `null` si jamais téléchargé.
  final DateTime? fetchedAt;

  /// `true` si affiché hors ligne (impossible à vérifier auprès du
  /// serveur) — voir `ManifesteState.isStale` pour le détail des cas.
  final bool isStale;

  /// `true` si servi depuis le cache local plutôt que fraîchement
  /// téléchargé lors de cet appel — un manifeste à jour peut très bien
  /// être `isFromCache: true` (vérification de version sans
  /// retéléchargement, voir `ManifesteRepositoryImpl.load`).
  final bool isFromCache;

  @override
  List<Object?> get props =>
      <Object?>[passengers, version, fetchedAt, isStale, isFromCache];
}
