import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../../../core/error/failures.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';

part 'wallet_history_state.dart';

/// Couche PRESENTATION : l'historique complet du Wallet — écran
/// `wallet-history` de la maquette.
///
/// Le serveur pagine par **curseur**, pas par numéro de page : on ne
/// peut pas sauter à la page 7, on demande « la suite de celle-ci ». Un
/// relevé bouge entre deux lectures — un remboursement tombe, des points
/// expirent — et une pagination par numéro ferait alors sauter ou
/// répéter des écritures.
///
/// Le tri crédits / utilisations se fait **au serveur** depuis que
/// `GET /wallet/transactions` accepte `direction`. L'app le faisait
/// elle-même, ce qui l'obligeait à lire des pages entières pour en
/// garder trois lignes.
class WalletHistoryCubit extends Cubit<WalletHistoryState> {
  WalletHistoryCubit(this._repository) : super(const WalletHistoryState());

  final WalletRepository _repository;

  /// (Re)lit le relevé depuis le début, pour la vue en cours.
  Future<void> load() async {
    if (state.isLoading) return;
    emit(state.copyWith(isLoading: true));

    final result = await _repository.fetchMovements(direction: state.direction);
    if (isClosed) return;

    result.fold(
      (failure) => emit(
        WalletHistoryState(direction: state.direction, failure: failure),
      ),
      (page) => emit(
        WalletHistoryState(
          direction: state.direction,
          movements: page.movements,
          cursor: page.nextCursor,
          hasLoaded: true,
        ),
      ),
    );
  }

  /// Change la vue et relit depuis le début.
  ///
  /// Un curseur appartient à la requête qui l'a produit : le rejouer sur
  /// une autre vue enchaînerait deux listes qui n'ont rien à voir.
  Future<void> filter(WalletDirection? direction) async {
    if (direction == state.direction) return;

    emit(WalletHistoryState(direction: direction, isLoading: true));

    final result = await _repository.fetchMovements(direction: direction);
    if (isClosed) return;

    result.fold(
      (failure) =>
          emit(WalletHistoryState(direction: direction, failure: failure)),
      (page) => emit(
        WalletHistoryState(
          direction: direction,
          movements: page.movements,
          cursor: page.nextCursor,
          hasLoaded: true,
        ),
      ),
    );
  }

  /// Charge la page suivante et l'ajoute à la fin.
  ///
  /// Un échec ici n'efface pas ce qui est déjà lu : le voyageur garde
  /// son relevé et voit qu'il manque la suite.
  Future<void> loadMore() async {
    final cursor = state.cursor;
    if (cursor == null || state.isLoading || state.isLoadingMore) return;

    emit(state.copyWith(isLoadingMore: true, clearMoreFailed: true));

    final result = await _repository.fetchMovements(
      direction: state.direction,
      cursor: cursor,
    );
    if (isClosed) return;

    result.fold(
      (_) => emit(state.copyWith(isLoadingMore: false, moreFailed: true)),
      (page) => emit(
        state.copyWith(
          movements: [...state.movements, ...page.movements],
          cursor: page.nextCursor,
          clearCursor: page.nextCursor == null,
          isLoadingMore: false,
        ),
      ),
    );
  }
}
