part of 'wallet_bonus_cubit.dart';

/// Couche PRESENTATION : ce que l'écran des bonus sait.
class WalletBonusState extends Equatable {
  const WalletBonusState({
    this.account,
    this.bonuses = const [],
    this.isLoading = false,
    this.ledgerFailed = false,
    this.failure,
  });

  /// Les quatre motifs que le serveur traite comme des bonus.
  static const Set<WalletReason> bonusReasons = {
    WalletReason.bonusWelcome,
    WalletReason.bonusAnniversary,
    WalletReason.bonusUsage,
    WalletReason.bonusReferral,
  };

  /// Les motifs dans l'ordre de la maquette.
  static const List<WalletReason> order = [
    WalletReason.bonusWelcome,
    WalletReason.bonusAnniversary,
    WalletReason.bonusUsage,
    WalletReason.bonusReferral,
  ];

  final WalletAccount? account;

  /// Les bonus réellement crédités, lus dans le relevé.
  final List<WalletMovement> bonuses;

  final bool isLoading;

  /// Le relevé n'a pas répondu alors que le Wallet, si : on le dit
  /// plutôt que de laisser croire qu'aucun bonus n'a jamais été gagné.
  final bool ledgerFailed;

  final Failure? failure;

  bool get isFirstLoad => isLoading && account == null;

  /// Le total des points gagnés en bonus.
  int get earned =>
      bonuses.fold(0, (total, movement) => total + movement.amount);

  /// Ce qui a été crédité pour un motif, ou `null` s'il ne l'a jamais
  /// été. Le montant vient du relevé : le serveur n'expose pas le
  /// barème.
  int? earnedFor(WalletReason reason) {
    var total = 0;
    var seen = false;

    for (final movement in bonuses) {
      if (movement.reason != reason) continue;
      total += movement.amount;
      seen = true;
    }

    return seen ? total : null;
  }

  WalletBonusState copyWith({
    WalletAccount? account,
    List<WalletMovement>? bonuses,
    bool? isLoading,
    bool? ledgerFailed,
    Failure? failure,
    bool clearFailure = false,
  }) {
    return WalletBonusState(
      account: account ?? this.account,
      bonuses: bonuses ?? this.bonuses,
      isLoading: isLoading ?? this.isLoading,
      ledgerFailed: ledgerFailed ?? this.ledgerFailed,
      failure: clearFailure ? null : (failure ?? this.failure),
    );
  }

  @override
  List<Object?> get props => [
    account,
    bonuses,
    isLoading,
    ledgerFailed,
    failure,
  ];
}
