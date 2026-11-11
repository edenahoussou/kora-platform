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
/// **Le filtre crédits / utilisations se fait ici, pas au serveur** :
/// `GET /wallet/transactions` n'accepte que `balance_type`, il n'a pas
/// de paramètre de sens. On lit donc le relevé entier et on le trie à
/// l'affichage — en continuant de charger tant qu'un filtre actif n'a
/// pas de quoi remplir l'écran, sans quoi une page de vingt débits
/// afficherait « aucun crédit » alors que la suite en contient.
class WalletHistoryCubit extends Cubit<WalletHistoryState> {
  WalletHistoryCubit(this._repository) : super(const WalletHistoryState());

  /// En dessous, un filtre actif va chercher la page suivante : sinon
  /// l'écran paraîtrait vide alors que le relevé continue.
  static const int _minimumVisible = 12;

  final WalletRepository _repository;

  /// (Re)lit le relevé depuis le début.
  Future<void> load() async {
    if (state.isLoading) return;
    emit(state.copyWith(isLoading: true));

    final result = await _repository.fetchMovements();
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

    await _fillFilter();
  }

  /// Change la vue. Rien n'est redemandé au serveur : le relevé est déjà
  /// là, seul l'affichage change — et on complète si le filtre vide
  /// l'écran.
  Future<void> filter(WalletDirection? direction) async {
    if (direction == state.direction) return;
    emit(
      state.copyWith(direction: direction, clearDirection: direction == null),
    );

    await _fillFilter();
  }

  /// Charge la page suivante et l'ajoute à la fin.
  ///
  /// Un échec ici n'efface pas ce qui est déjà lu : le voyageur garde
  /// son relevé et voit qu'il manque la suite.
  Future<void> loadMore() async {
    final cursor = state.cursor;
    if (cursor == null || state.isLoading || state.isLoadingMore) return;

    emit(state.copyWith(isLoadingMore: true, clearMoreFailed: true));

    final result = await _repository.fetchMovements(cursor: cursor);
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

  /// Complète tant qu'un filtre actif n'a pas de quoi remplir l'écran.
  Future<void> _fillFilter() async {
    while (!isClosed &&
        state.direction != null &&
        state.hasMore &&
        !state.moreFailed &&
        state.visible.length < _minimumVisible) {
      final before = state.movements.length;
      await loadMore();
      // Garde-fou : un serveur qui rendrait un curseur sans écriture
      // ferait tourner cette boucle indéfiniment.
      if (state.movements.length == before) return;
    }
  }
}
