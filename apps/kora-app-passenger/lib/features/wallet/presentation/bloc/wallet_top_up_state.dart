part of 'wallet_top_up_cubit.dart';

/// Couche PRESENTATION : ce que l'écran de recharge sait.
class WalletTopUpState extends Equatable {
  const WalletTopUpState({
    this.account,
    this.methods = const [],
    this.method,
    this.amount,
    this.topUp,
    this.isLoading = false,
    this.isSubmitting = false,
    this.catalogFailed = false,
    this.round = 0,
    this.failure,
  });

  /// `null` tant que le serveur n'a pas répondu : sans ses bornes,
  /// l'écran n'a aucun montant à proposer ni à valider.
  final WalletAccount? account;

  /// Les moyens que le serveur accepte, Wallet exclu.
  final List<PaymentMethod> methods;

  final String? method;
  final int? amount;

  /// La recharge ouverte, une fois le serveur d'accord.
  final WalletTopUp? topUp;

  final bool isLoading;
  final bool isSubmitting;

  /// Le catalogue n'a pas répondu alors que le Wallet, si : l'écran le
  /// dit plutôt que de proposer une liste de moyens vide sans raison.
  final bool catalogFailed;

  /// Le nombre de tentatives, pour que chaque envoi porte une clé
  /// d'idempotence neuve.
  final int round;

  final Failure? failure;

  bool get isFirstLoad => isLoading && account == null;

  /// Les montants proposés en un geste, tels que le serveur les règle.
  List<int> get presets => account?.rules.topupPresets ?? const [];

  /// Ce qui manque encore pour pouvoir envoyer.
  bool get canSubmit =>
      account != null &&
      method != null &&
      amount != null &&
      amountIssue == null &&
      !isSubmitting;

  /// Ce qui cloche dans le montant, selon les bornes du serveur et elles
  /// seules. `null` quand il passe.
  TopUpAmountIssue? get amountIssue {
    final rules = account?.rules;
    final value = amount;
    if (rules == null) return null;
    if (value == null || value <= 0) return TopUpAmountIssue.empty;

    final min = rules.topupMin;
    if (min != null && value < min) return TopUpAmountIssue.tooSmall;

    final max = rules.topupMax;
    if (max != null && value > max) return TopUpAmountIssue.tooLarge;

    // Le plafond porte sur le solde **après** recharge, pas sur le
    // montant : c'est la règle du serveur (`assertAmount`), et la
    // recopier autrement ferait accepter ici ce qu'il refusera.
    final cap = rules.cashBalanceCap;
    final balance = account?.cash.amount ?? 0;
    if (cap != null && balance + value > cap) return TopUpAmountIssue.overCap;

    return null;
  }

  /// Le provider à employer pour [code], tel que le catalogue l'annonce.
  ///
  /// Le premier activé : c'est un détail d'acheminement, pas un choix
  /// de voyageur — il choisit un moyen, pas un encaisseur.
  String? providerFor(String code) {
    for (final candidate in methods) {
      if (candidate.code != code) continue;
      for (final provider in candidate.providers) {
        if (provider.enabled) return provider.code;
      }
    }

    return null;
  }

  WalletTopUpState copyWith({
    WalletAccount? account,
    List<PaymentMethod>? methods,
    String? method,
    int? amount,
    bool clearAmount = false,
    WalletTopUp? topUp,
    bool? isLoading,
    bool? isSubmitting,
    bool? catalogFailed,
    int? round,
    Failure? failure,
    bool clearFailure = false,
  }) {
    return WalletTopUpState(
      account: account ?? this.account,
      methods: methods ?? this.methods,
      method: method ?? this.method,
      amount: clearAmount ? null : (amount ?? this.amount),
      topUp: topUp ?? this.topUp,
      isLoading: isLoading ?? this.isLoading,
      isSubmitting: isSubmitting ?? this.isSubmitting,
      catalogFailed: catalogFailed ?? this.catalogFailed,
      round: round ?? this.round,
      failure: clearFailure ? null : (failure ?? this.failure),
    );
  }

  @override
  List<Object?> get props => [
    account,
    methods,
    method,
    amount,
    topUp,
    isLoading,
    isSubmitting,
    catalogFailed,
    round,
    failure,
  ];
}
