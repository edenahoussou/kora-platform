import 'package:connectivity_plus/connectivity_plus.dart';

/// Vérifie si l'appareil a une connexion internet.
///
/// Utile pour éviter un appel réseau voué à échouer (et donner tout de
/// suite un échec clair côté app) plutôt que d'attendre un timeout Dio.
///
/// Exemple d'utilisation dans un repository :
/// ```dart
/// if (!await networkInfo.isConnected) {
///   return const Left(NetworkFailure());
/// }
/// ```
abstract class NetworkInfo {
  Future<bool> get isConnected;
}

class NetworkInfoImpl implements NetworkInfo {
  NetworkInfoImpl(this._connectivity);

  final Connectivity _connectivity;

  @override
  Future<bool> get isConnected async {
    final result = await _connectivity.checkConnectivity();
    return !result.contains(ConnectivityResult.none);
  }
}
