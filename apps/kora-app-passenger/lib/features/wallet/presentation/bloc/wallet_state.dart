part of 'wallet_cubit.dart';

/// Couche PRESENTATION : ce que l'onglet Wallet sait à un instant donné.
class WalletState extends Equatable {
  const WalletState({
    this.account,
    this.movements = const [],
    this.isLoading = false,
    this.movementsFailed = false,
    this.failure,
  });

  /// `null` tant que le serveur n'a pas répondu — aucun solde par
  /// défaut, ni zéro ni valeur d'exemple.
  final WalletAccount? account;

  /// Les derniers mouvements, au plus [WalletCubit.previewLength].
  final List<WalletMovement> movements;

  final bool isLoading;

  /// Le relevé n'a pas pu être lu, mais les soldes si : la section le
  /// dit sans faire tomber l'écran.
  final bool movementsFailed;

  final Failure? failure;

  bool get hasAccount => account != null;
  bool get hasFailed => failure != null;

  /// Vrai pendant la toute première lecture, celle qui mérite un
  /// squelette plutôt qu'un écran vide.
  bool get isFirstLoad => isLoading && account == null;

  WalletState copyWith({
    WalletAccount? account,
    List<WalletMovement>? movements,
    bool? isLoading,
    bool? movementsFailed,
    Failure? failure,
    bool clearFailure = false,
  }) {
    return WalletState(
      account: account ?? this.account,
      movements: movements ?? this.movements,
      isLoading: isLoading ?? this.isLoading,
      movementsFailed: movementsFailed ?? this.movementsFailed,
      failure: clearFailure ? null : (failure ?? this.failure),
    );
  }

  @override
  List<Object?> get props => [
    account,
    movements,
    isLoading,
    movementsFailed,
    failure,
  ];
}
