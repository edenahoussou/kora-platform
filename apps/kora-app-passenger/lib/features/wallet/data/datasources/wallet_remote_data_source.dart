import 'package:dio/dio.dart';

import '../../../../core/constants/api_constants.dart';
import '../../../../core/network/api_json.dart';
import '../../domain/entities/wallet_account.dart';
import '../../domain/entities/wallet_movement.dart';
import '../../domain/repositories/wallet_repository.dart';
import '../models/wallet_models.dart';

/// Couche DATA : les deux lectures du Wallet.
///
/// Comme les autres sources distantes, celle-ci laisse remonter les
/// `DioException` : c'est le dépôt qui les traduit en échecs métier.
class WalletRemoteDataSource {
  const WalletRemoteDataSource(this._dio);

  final Dio _dio;

  Future<WalletAccount> fetchAccount() async {
    final response = await _dio.get<dynamic>(ApiConstants.wallet);

    // La réponse est un objet sous `data`, pas une liste : on ouvre
    // l'enveloppe à la main.
    final body = ApiJson.map(response.data);
    final data = body['data'] is Map ? ApiJson.map(body['data']) : body;

    return WalletAccountModel.fromJson(data);
  }

  Future<WalletLedgerPage> fetchMovements({
    WalletBalanceKind? kind,
    String? cursor,
  }) async {
    final response = await _dio.get<dynamic>(
      ApiConstants.walletTransactions,
      queryParameters: {'balance_type': ?kind?.name, 'cursor': ?cursor},
    );

    final body = ApiJson.map(response.data);

    return WalletLedgerPage(
      movements: [
        for (final json in ApiJson.list(response.data))
          WalletMovementModel.fromJson(json),
      ],
      nextCursor: _nextCursor(body),
    );
  }

  /// Le curseur de la page suivante.
  ///
  /// Le serveur pagine par curseur : `meta.next_cursor` le porte, et
  /// `null` signifie qu'on est au bout. Certaines versions ne rendent
  /// que `links.next`, une URL complète — on en extrait le paramètre
  /// plutôt que de rappeler l'URL telle quelle, qui contournerait
  /// l'intercepteur d'authentification.
  static String? _nextCursor(Map<String, dynamic> body) {
    final meta = ApiJson.map(body['meta']);
    final direct = ApiJson.stringOrNull(meta['next_cursor']);
    if (direct != null) return direct;

    final next = ApiJson.stringOrNull(ApiJson.map(body['links'])['next']);
    if (next == null) return null;

    return Uri.tryParse(next)?.queryParameters['cursor'];
  }
}
