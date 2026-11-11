part of 'wallet_history_cubit.dart';

/// Couche PRESENTATION : ce que l'historique sait à un instant donné.
class WalletHistoryState extends Equatable {
  const WalletHistoryState({
    this.direction,
    this.movements = const [],
    this.cursor,
    this.isLoading = false,
    this.isLoadingMore = false,
    this.hasLoaded = false,
    this.moreFailed = false,
    this.failure,
  });

  /// La vue en cours : les crédits, les utilisations, ou tout.
  final WalletDirection? direction;

  /// Tout ce qui a été lu, les deux soldes mêlés, du plus récent au
  /// plus ancien.
  final List<WalletMovement> movements;

  /// Le curseur de la page suivante ; `null` quand on est au bout.
  final String? cursor;

  final bool isLoading;
  final bool isLoadingMore;

  /// Vrai dès qu'une lecture a abouti. Sans ça, une liste vide au
  /// premier rendu passerait pour « aucun mouvement » avant même que le
  /// serveur ait répondu.
  final bool hasLoaded;

  /// La page suivante n'a pas répondu : on le dit en pied de liste sans
  /// effacer ce qui est déjà lu.
  final bool moreFailed;

  final Failure? failure;

  /// Ce que l'écran montre, une fois la vue appliquée.
  List<WalletMovement> get visible {
    final sense = direction;
    if (sense == null) return movements;

    return movements.where((m) => m.direction == sense).toList();
  }

  bool get hasMore => cursor != null;

  /// Vrai pendant la toute première lecture, celle qui mérite un
  /// squelette plutôt qu'un écran vide.
  bool get isFirstLoad => isLoading && movements.isEmpty;

  /// Vrai quand le serveur a répondu qu'il n'y a rien — à ne pas
  /// confondre avec « on ne sait pas encore ».
  bool get isEmpty => hasLoaded && visible.isEmpty;

  WalletHistoryState copyWith({
    WalletDirection? direction,
    bool clearDirection = false,
    List<WalletMovement>? movements,
    String? cursor,
    bool clearCursor = false,
    bool? isLoading,
    bool? isLoadingMore,
    bool? hasLoaded,
    bool? moreFailed,
    bool clearMoreFailed = false,
  }) {
    return WalletHistoryState(
      direction: clearDirection ? null : (direction ?? this.direction),
      movements: movements ?? this.movements,
      cursor: clearCursor ? null : (cursor ?? this.cursor),
      isLoading: isLoading ?? this.isLoading,
      isLoadingMore: isLoadingMore ?? this.isLoadingMore,
      hasLoaded: hasLoaded ?? this.hasLoaded,
      moreFailed: clearMoreFailed ? false : (moreFailed ?? this.moreFailed),
      failure: failure,
    );
  }

  @override
  List<Object?> get props => [
    direction,
    movements,
    cursor,
    isLoading,
    isLoadingMore,
    hasLoaded,
    moreFailed,
    failure,
  ];
}
