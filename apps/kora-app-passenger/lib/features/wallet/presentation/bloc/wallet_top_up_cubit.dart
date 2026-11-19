import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../../payment/domain/entities/payment_catalog.dart';
import '../../../payment/domain/repositories/payment_repository.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_top_up.dart';
import '../../domain/repositories/wallet_repository.dart';

part 'wallet_top_up_state.dart';

/// Pourquoi un montant ne passe pas.
///
/// Un code plutôt qu'une phrase : les bornes viennent du serveur, mais
/// la manière de les dire appartient à l'écran, qui connaît la langue
/// du voyageur.
enum TopUpAmountIssue { empty, tooSmall, tooLarge, overCap }

/// Couche PRESENTATION : la recharge du Wallet — point 2 de MEZ-160.
///
/// **Toutes les bornes viennent du serveur** : montant minimum, maximum
/// et plafond du solde. Les recopier dans l'app ferait refuser ici des
/// montants que le serveur accepte, ou l'inverse — et dans le second cas
/// le voyageur ne l'apprendrait qu'après avoir tapé son code.
///
/// La validation locale ne remplace pas celle du serveur, elle lui
/// évite un aller-retour : c'est `InitiateWalletTopUpAction` qui tranche.
class WalletTopUpCubit extends Cubit<WalletTopUpState> {
  WalletTopUpCubit(this._wallet, this._payments)
    : super(const WalletTopUpState());

  final WalletRepository _wallet;
  final PaymentRepository _payments;

  /// Lit en parallèle le Wallet (pour ses bornes et son solde) et le
  /// catalogue (pour les moyens que le serveur accepte vraiment).
  Future<void> load() async {
    if (state.isLoading) return;
    emit(state.copyWith(isLoading: true, clearFailure: true));

    final pendingAccount = _wallet.fetchAccount();
    final pendingCatalog = _payments.catalog();

    final account = await pendingAccount;
    final catalog = await pendingCatalog;

    if (isClosed) return;

    account.fold(
      (failure) => emit(state.copyWith(isLoading: false, failure: failure)),
      (value) {
        final methods = catalog.fold(
          (_) => const <PaymentMethod>[],
          // Le Wallet ne se recharge pas avec le Wallet : proposer
          // « payer par Wallet » pour alimenter le Wallet tournerait en
          // rond.
          (value) => value.enabledMethods
              .where((method) => method.code != 'wallet')
              .toList(),
        );

        emit(
          WalletTopUpState(
            account: value,
            methods: methods,
            method: methods.isEmpty ? null : methods.first.code,
            catalogFailed: catalog.isLeft(),
          ),
        );
      },
    );
  }

  /// Choisit un montant proposé, ou saisi.
  void chooseAmount(int? amount) =>
      emit(state.copyWith(amount: amount, clearAmount: amount == null));

  void chooseMethod(String code) => emit(state.copyWith(method: code));

  /// Ouvre la recharge.
  ///
  /// La clé d'idempotence tient au montant, au moyen et au tour : sans
  /// elle, un réseau qui coupe entre l'envoi et la réponse laisserait le
  /// voyageur relancer et payer deux fois.
  Future<void> submit() async {
    final amount = state.amount;
    final method = state.method;
    final account = state.account;
    if (amount == null || method == null || account == null) return;
    if (state.amountIssue != null || state.isSubmitting) return;

    final provider = state.providerFor(method);
    if (provider == null) return;

    emit(state.copyWith(isSubmitting: true, clearFailure: true));

    final result = await _wallet.initiateTopUp(
      amount: amount,
      method: method,
      provider: provider,
      idempotencyKey: 'kora-topup-${account.id}-$amount-$method-${state.round}',
    );

    if (isClosed) return;

    result.fold(
      // Un tour de plus : la prochaine tentative porte une clé neuve,
      // sans quoi le serveur rejouerait la recharge refusée.
      (failure) => emit(
        state.copyWith(
          isSubmitting: false,
          failure: failure,
          round: state.round + 1,
        ),
      ),
      (topUp) => emit(state.copyWith(isSubmitting: false, topUp: topUp)),
    );
  }
}
