part of 'manifeste_cubit.dart';

enum ManifesteStatus { noDepart, loading, success, error }

class ManifesteState extends Equatable {
  const ManifesteState({
    this.status = ManifesteStatus.noDepart,
    this.passengers = const <Passenger>[],
    this.errorMessage,
    this.manifestVersion,
    this.fetchedAt,
    this.isStale = false,
    this.isFromCache = false,
    this.pendingReleaseTicketUuids = const <String>{},
    this.isInternational,
  });

  final ManifesteStatus status;
  final List<Passenger> passengers;
  final String? errorMessage;

  final String? manifestVersion;

  /// Dernier téléchargement complet réussi — `null` si jamais téléchargé
  /// (état `error` avant le tout premier chargement).
  final DateTime? fetchedAt;

  /// `true` si ce manifeste n'a pas pu être vérifié auprès du serveur
  /// (hors ligne, ou vérification en échec) — voir `ManifestSnapshot.
  /// isStale`. Toujours `false` après un chargement réussi en ligne
  /// (retéléchargé si périmé, vérifié sinon — voir `ManifesteRepository.
  /// load`).
  final bool isStale;

  /// `true` si servi depuis le cache local pour ce chargement (vérifié à
  /// jour sans retéléchargement, ou hors ligne) — distinct de [isStale] :
  /// un cache tout juste vérifié à jour est `isFromCache: true` mais
  /// `isStale: false`.
  final bool isFromCache;

  /// UUID de billet ayant une libération de siège (descente anticipée ou
  /// no-show) **en attente de synchronisation** — MEZ-87. N'affecte
  /// jamais [Passenger.statut] : un badge "en attente de sync" purement
  /// informatif sur la ligne du manifeste, jamais un statut "libéré"
  /// avant acceptation serveur (critère d'acceptation MEZ-87 — empêcher
  /// une nouvelle vente locale fondée sur une libération non acceptée).
  /// Vidé à chaque [load] réussi (un rafraîchissement fait foi).
  final Set<String> pendingReleaseTicketUuids;

  /// Le départ est-il sur une ligne internationale ? `null` = inconnu
  /// (voir `ManifestSnapshot.isInternational`). Conditionne le manifeste
  /// frontière (MEZ-94).
  final bool? isInternational;

  int get embarques => passengers
      .where((Passenger p) => p.statut == BoardingStatus.embarque)
      .length;

  ManifesteState copyWith({
    ManifesteStatus? status,
    List<Passenger>? passengers,
    String? errorMessage,
    String? manifestVersion,
    DateTime? fetchedAt,
    bool? isStale,
    bool? isFromCache,
    Set<String>? pendingReleaseTicketUuids,
    Object? isInternational = _unset,
  }) {
    return ManifesteState(
      status: status ?? this.status,
      passengers: passengers ?? this.passengers,
      errorMessage: errorMessage,
      manifestVersion: manifestVersion ?? this.manifestVersion,
      fetchedAt: fetchedAt ?? this.fetchedAt,
      isStale: isStale ?? this.isStale,
      isFromCache: isFromCache ?? this.isFromCache,
      pendingReleaseTicketUuids:
          pendingReleaseTicketUuids ?? this.pendingReleaseTicketUuids,
      // Sentinelle plutôt que `??` : « inconnu » (`null`) est une valeur
      // légitime qui doit pouvoir REMPLACER une valeur précédente (départ
      // différent, cache ancien) au lieu de la conserver.
      isInternational: identical(isInternational, _unset)
          ? this.isInternational
          : isInternational as bool?,
    );
  }

  static const Object _unset = Object();

  @override
  List<Object?> get props => <Object?>[
        status,
        passengers,
        errorMessage,
        manifestVersion,
        fetchedAt,
        isStale,
        isFromCache,
        pendingReleaseTicketUuids,
        isInternational,
      ];
}
