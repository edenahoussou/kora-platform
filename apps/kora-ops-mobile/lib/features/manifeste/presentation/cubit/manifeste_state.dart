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
  }) {
    return ManifesteState(
      status: status ?? this.status,
      passengers: passengers ?? this.passengers,
      errorMessage: errorMessage,
      manifestVersion: manifestVersion ?? this.manifestVersion,
      fetchedAt: fetchedAt ?? this.fetchedAt,
      isStale: isStale ?? this.isStale,
      isFromCache: isFromCache ?? this.isFromCache,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        status,
        passengers,
        errorMessage,
        manifestVersion,
        fetchedAt,
        isStale,
        isFromCache,
      ];
}
