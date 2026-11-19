import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';

part 'wallet_state.dart';

/// Couche PRESENTATION : l'onglet Wallet.
///
/// Deux lectures en parallèle — les soldes et le début du relevé —
/// parce qu'elles vont ensemble à l'écran : les afficher l'une après
/// l'autre ferait apparaître la carte du solde, puis sauter la page
/// quand les mouvements arrivent.
///
/// **Rien n'est avancé localement.** Pas de crédit optimiste après une
/// recharge, pas de solde reconstitué à partir des mouvements : l'écran
/// montre ce que le serveur dit, ou dit qu'il ne sait pas.
class WalletCubit extends Cubit<WalletState> {
  WalletCubit(this._repository) : super(const WalletState());

  /// Le relevé n'affiche que les derniers mouvements sur cet onglet ;
  /// l'historique complet a son propre écran.
  static const int previewLength = 3;

  final WalletRepository _repository;

  Future<void> load() async {
    if (state.isLoading) return;
    emit(state.copyWith(isLoading: true, clearFailure: true));

    // Les deux appels partent ensemble et on attend les deux : les
    // enchaîner ferait apparaître la carte du solde, puis sauter la page
    // quand les mouvements arrivent.
    final pending = _repository.fetchAccount();
    final pendingLedger = _repository.fetchMovements();

    final account = await pending;
    final ledger = await pendingLedger;

    if (isClosed) return;

    account.fold(
      // Sans les soldes, l'onglet n'a rien à montrer : c'est un échec
      // d'écran, pas une section vide.
      (failure) => emit(state.copyWith(isLoading: false, failure: failure)),
      (value) => emit(
        WalletState(
          account: value,
          // Le relevé, lui, peut manquer sans emporter l'écran : la
          // carte des soldes reste utile, et la section le dira.
          movements: ledger.fold(
            (_) => const [],
            (page) => page.movements.take(previewLength).toList(),
          ),
          movementsFailed: ledger.isLeft(),
        ),
      ),
    );
  }
}
