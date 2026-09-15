import 'package:dio/dio.dart';

/// Ajoute l'en-tête `X-Terminal-Serial` à chaque requête sortante, dès
/// qu'un terminal est lié (voir `EnsureTerminalIsUsable` côté backend, qui
/// lit cet en-tête en priorité sur `serial_number` dans le corps).
///
/// Ne bloque jamais la requête si aucun terminal n'est encore lié —
/// `handshake` lui-même n'a pas besoin de cet en-tête, et les endpoints qui
/// l'exigent renverront simplement `terminal_unknown` si l'en-tête est
/// absent, ce que la couche Data traduit normalement en `Failure`.
class TerminalHeaderInterceptor extends Interceptor {
  TerminalHeaderInterceptor({required this.serialNumberProvider});

  /// Lit le numéro de série actuellement lié (stockage sécurisé). Passé en
  /// callback plutôt qu'une dépendance directe à `TerminalLocalDataSource`
  /// pour éviter tout cycle d'injection entre le client réseau et la
  /// couche Data qui l'utilise.
  final Future<String?> Function() serialNumberProvider;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    final String? serial = await serialNumberProvider();
    if (serial != null && serial.isNotEmpty) {
      options.headers['X-Terminal-Serial'] = serial;
    }
    handler.next(options);
  }
}
