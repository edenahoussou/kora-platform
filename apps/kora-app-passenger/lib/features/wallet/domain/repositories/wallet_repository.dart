import 'package:dartz/dartz.dart';

import '../../../../core/error/failures.dart';
import '../entities/wallet_account.dart';
import '../entities/wallet_movement.dart';
import '../entities/wallet_top_up.dart';

/// Le Wallet KORA BUS d'un voyageur : deux soldes et leur relevé.
///
/// **L'app ne tient aucun compte.** Elle lit ce que le serveur dit, et
/// ne crédite ni ne débite rien de son côté : un solde avancé en local
/// après une recharge afficherait de l'argent qui n'existe pas encore —
/// voir `docs/mez-49-compte-voyageur-wallet-fidelite.md`.
abstract class WalletRepository {
  /// Les soldes, l'échéance des points, le code de parrainage et les
  /// règles en vigueur.
  Future<Either<Failure, WalletAccount>> fetchAccount();

  /// Le relevé, du plus récent au plus ancien.
  ///
  /// [kind] restreint à l'un des deux soldes ; `null` les mêle, ce qui
  /// est la vue par défaut. [cursor] enchaîne les pages — le serveur
  /// pagine par curseur, pas par numéro de page.
  Future<Either<Failure, WalletLedgerPage>> fetchMovements({
    WalletBalanceKind? kind,
    String? cursor,
  });

  /// Ouvre une recharge et le paiement qui la porte.
  ///
  /// [idempotencyKey] est ce qui empêche un double débit quand le
  /// réseau coupe entre l'envoi et la réponse : rejouée, la requête
  /// rend la recharge déjà créée au lieu d'en ouvrir une seconde.
  Future<Either<Failure, WalletTopUp>> initiateTopUp({
    required int amount,
    required String method,
    required String provider,
    required String idempotencyKey,
    String? phone,
    String? network,
  });

  /// Relit une recharge, pour suivre son issue.
  Future<Either<Failure, WalletTopUp>> fetchTopUp(String uuid);
}

/// Une page du relevé, et de quoi demander la suivante.
class WalletLedgerPage {
  const WalletLedgerPage({required this.movements, this.nextCursor});

  final List<WalletMovement> movements;

  /// `null` quand il n'y a plus rien à charger.
  final String? nextCursor;

  bool get hasMore => nextCursor != null;
}
