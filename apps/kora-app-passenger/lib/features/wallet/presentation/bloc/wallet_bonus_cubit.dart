import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';

part 'wallet_bonus_state.dart';

/// Couche PRESENTATION : l'écran `wallet-bonuses` — les bonus fidélité.
///
/// **Les bonus se lisent dans le relevé, pas dans un réglage.** Le
/// serveur connaît le montant de chaque bonus (`bonusWelcomePoints`,
/// `bonusReferralPoints`…) mais ne les expose pas : `WalletResource`
/// n'en rend aucun. Plutôt que d'écrire « +500 pts » dans l'app — un
/// chiffre que l'administration peut changer demain — l'écran compte ce
/// qui a réellement été crédité et le montre tel quel.
///
/// Conséquence assumée : un bonus jamais obtenu s'affiche sans son
/// montant. Mieux vaut une promesse muette qu'une promesse fausse.
class WalletBonusCubit extends Cubit<WalletBonusState> {
  WalletBonusCubit(this._repository) : super(const WalletBonusState());

  /// Le relevé des bonus est court par nature — quatre motifs, quelques
  /// écritures. On le lit en entier, dans la limite de ce garde-fou.
  static const int _maxPages = 10;

  final WalletRepository _repository;

  Future<void> load() async {
    if (state.isLoading) return;
    emit(state.copyWith(isLoading: true, clearFailure: true));

    final pendingAccount = _repository.fetchAccount();
    final bonuses = <WalletMovement>[];

    String? cursor;
    var failed = false;

    for (var page = 0; page < _maxPages; page++) {
      final result = await _repository.fetchMovements(
        // Les bonus créditent toujours des points : filtrer au serveur
        // évite de ramener les mouvements cash pour les jeter ensuite.
        kind: WalletBalanceKind.points,
        cursor: cursor,
      );
      if (isClosed) return;

      final stop = result.fold(
        (_) {
          failed = true;
          return true;
        },
        (value) {
          bonuses.addAll(value.movements.where(_isBonus));
          cursor = value.nextCursor;
          return value.nextCursor == null;
        },
      );

      if (stop) break;
    }

    final account = await pendingAccount;
    if (isClosed) return;

    account.fold(
      (failure) => emit(state.copyWith(isLoading: false, failure: failure)),
      (value) => emit(
        WalletBonusState(
          account: value,
          bonuses: bonuses,
          ledgerFailed: failed,
        ),
      ),
    );
  }

  static bool _isBonus(WalletMovement movement) =>
      movement.isCredit &&
      WalletBonusState.bonusReasons.contains(movement.reason);
}
