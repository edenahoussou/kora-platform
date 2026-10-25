import 'package:dio/dio.dart';

import '../../features/app_update/data/models/app_update_dtos.dart';
import '../../features/app_update/domain/entities/app_update_info.dart';

/// Lit le bloc `data.update` (MEZ-150) dans les réponses OPS Mobile qui le
/// portent — `ops-mobile/terminal/handshake|sync|activity` et
/// `ops-mobile/session` — et le transmet au cubit de mise à jour : la
/// politique de version est ainsi appliquée en temps réel, sans écran ni
/// appel dédié.
///
/// N'altère jamais la réponse et n'échoue jamais : un bloc absent ou
/// illisible est simplement ignoré.
class AppUpdateInterceptor extends Interceptor {
  AppUpdateInterceptor({required this.onUpdateBlock});

  /// Appelé uniquement quand la réponse contient la clé `update`
  /// (`null` = aucune release publiée côté serveur).
  final void Function(AppUpdateInfo? info) onUpdateBlock;

  static const List<String> _paths = <String>[
    '/ops-mobile/terminal/',
    '/ops-mobile/session',
  ];

  @override
  void onResponse(
    Response<dynamic> response,
    ResponseInterceptorHandler handler,
  ) {
    final String path = response.requestOptions.uri.path;

    if (_paths.any(path.contains)) {
      try {
        final ({bool present, AppUpdateInfo? info}) block =
            AppUpdateDtos.fromOpsResponse(response.data);
        if (block.present) onUpdateBlock(block.info);
      } on Object {
        // Champ additionnel : ne jamais faire échouer la requête métier.
      }
    }

    handler.next(response);
  }
}
