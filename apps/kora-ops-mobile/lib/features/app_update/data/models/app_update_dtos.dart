import '../../domain/entities/app_update_info.dart';

/// Couche DATA : lecture du bloc `update` (MEZ-150), où qu'il se trouve :
///  - vérification au lancement : `{ "data": { ...update } }` ;
///  - handshake / sync / activity / session : `{ "data": { ..., "update": { ... } } }`.
///
/// Tolérant par construction : un bloc absent, `null` ou incomplet donne
/// `null` (rien à proposer), jamais une exception — une réponse métier ne
/// doit jamais échouer à cause de ce champ additionnel.
abstract final class AppUpdateDtos {
  /// Bloc `data.update` d'une réponse OPS Mobile. [present] vaut `true`
  /// quand la clé existe (même `null`) : le serveur a alors explicitement
  /// dit « aucune release publiée ».
  static ({bool present, AppUpdateInfo? info}) fromOpsResponse(Object? body) {
    if (body is! Map) return (present: false, info: null);

    final Object? data = body['data'];
    if (data is! Map || !data.containsKey('update')) {
      return (present: false, info: null);
    }

    return (present: true, info: fromBlock(data['update']));
  }

  /// Réponse de `GET /api/v1/apps/{app}/update` : le bloc est `data`.
  static AppUpdateInfo? fromCheckResponse(Object? body) {
    if (body is! Map) return null;
    return fromBlock(body['data']);
  }

  static AppUpdateInfo? fromBlock(Object? block) {
    if (block is! Map) return null;

    final String? latestVersion = _string(block['latest_version']);
    final int? latestBuild = _int(block['latest_build']);
    final String? downloadUrl = _string(block['download_url']);
    final String? sha256 = _string(block['sha256']);
    final String? releaseUuid = _string(block['release_uuid']);

    if (latestVersion == null ||
        latestBuild == null ||
        downloadUrl == null ||
        sha256 == null ||
        releaseUuid == null) {
      return null;
    }

    final String? publishedAt = _string(block['published_at']);

    return AppUpdateInfo(
      latestVersion: latestVersion,
      latestBuild: latestBuild,
      serverMandatory: block['mandatory'] == true,
      minimumVersion: _string(block['minimum_version']),
      minimumBuild: _int(block['minimum_build']),
      releaseUuid: releaseUuid,
      releaseNotes: _string(block['release_notes']),
      downloadUrl: downloadUrl,
      downloadPageUrl: _string(block['download_page_url']),
      sha256: sha256.toLowerCase(),
      sizeBytes: _int(block['size_bytes']),
      publishedAt: publishedAt == null ? null : DateTime.tryParse(publishedAt),
    );
  }

  /// Forme sérialisable (mêmes clés que le bloc serveur) — cache local.
  static Map<String, dynamic> toBlock(AppUpdateInfo info) => <String, dynamic>{
        'latest_version': info.latestVersion,
        'latest_build': info.latestBuild,
        'mandatory': info.serverMandatory,
        'minimum_version': info.minimumVersion,
        'minimum_build': info.minimumBuild,
        'release_uuid': info.releaseUuid,
        'release_notes': info.releaseNotes,
        'download_url': info.downloadUrl,
        'download_page_url': info.downloadPageUrl,
        'sha256': info.sha256,
        'size_bytes': info.sizeBytes,
        'published_at': info.publishedAt?.toIso8601String(),
      };

  static String? _string(Object? value) =>
      value is String && value.trim().isNotEmpty ? value.trim() : null;

  static int? _int(Object? value) => switch (value) {
        final int number => number,
        final num number => number.toInt(),
        final String text => int.tryParse(text),
        _ => null,
      };
}
