import 'dart:async';

/// Un signal « le Wallet a bougé », que les écrans écoutent.
///
/// L'onglet Wallet naît une fois et vit tant que l'app vit : son cubit
/// lit le solde à la création, puis plus jamais. Après une recharge
/// confirmée, le voyageur y revenait donc sur l'ancien solde et devait
/// tirer pour rafraîchir — l'écran annonçait « 20 000 FCFA ajoutés »
/// pendant que la carte montrait le montant d'avant.
///
/// Même mécanisme que `BookingRefresh` pour l'onglet Voyages : ce qui
/// change le Wallet le signale, et l'onglet relit.
class WalletRefresh {
  WalletRefresh();

  final _changes = StreamController<void>.broadcast();

  Stream<void> get changes => _changes.stream;

  void notify() => _changes.add(null);

  Future<void> close() => _changes.close();
}
