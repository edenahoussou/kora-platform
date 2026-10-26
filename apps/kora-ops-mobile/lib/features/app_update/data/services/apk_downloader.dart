import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';

/// Couche DATA : téléchargement de l'APK et contrôle d'intégrité (MEZ-150).
///
/// Le fichier est d'abord écrit sous un nom temporaire (`.part`), son
/// sha256 est calculé en flux (sans charger l'APK en mémoire — les P501
/// ont peu de RAM), puis il n'est renommé en `.apk` que si l'empreinte
/// correspond à celle annoncée par le serveur. Un fichier non conforme est
/// supprimé : il ne peut jamais être proposé à l'installeur.
class ApkDownloader {
  ApkDownloader({Dio? dio}) : _dio = dio ?? _defaultDio();

  final Dio _dio;

  /// Client dédié, sans l'intercepteur d'auth : l'URL de téléchargement
  /// est publique et un APK de plusieurs dizaines de Mo sur réseau mobile
  /// dépasse largement les délais des appels API.
  static Dio _defaultDio() => Dio(
        BaseOptions(
          connectTimeout: const Duration(seconds: 30),
          receiveTimeout: const Duration(minutes: 10),
          followRedirects: true,
        ),
      );

  /// Télécharge [url] vers [targetPath] et vérifie [expectedSha256].
  /// Réutilise un fichier déjà présent et conforme (téléchargement
  /// interrompu par une installation annulée, par exemple).
  ///
  /// Lève [ApkIntegrityException] si l'empreinte ne correspond pas, et
  /// laisse remonter les [DioException]/[FileSystemException].
  Future<File> download({
    required String url,
    required String targetPath,
    required String expectedSha256,
    void Function(int received, int total)? onProgress,
    CancelToken? cancelToken,
  }) async {
    final File target = File(targetPath);

    if (await target.exists()) {
      if (await matchesSha256(target, expectedSha256)) return target;
      await target.delete();
    }

    final File partial = File('$targetPath.part');
    if (await partial.exists()) await partial.delete();

    await _dio.download(
      url,
      partial.path,
      cancelToken: cancelToken,
      onReceiveProgress: onProgress,
      deleteOnError: true,
      options: Options(headers: <String, String>{'Accept': '*/*'}),
    );

    if (!await matchesSha256(partial, expectedSha256)) {
      await partial.delete();
      throw const ApkIntegrityException();
    }

    return partial.rename(target.path);
  }

  /// Empreinte sha256 (hexadécimal minuscule) calculée en flux.
  static Future<String> sha256Of(File file) async {
    final Digest digest = await sha256.bind(file.openRead()).first;
    return digest.toString();
  }

  static Future<bool> matchesSha256(File file, String expected) async =>
      await sha256Of(file) == expected.trim().toLowerCase();
}

/// Le fichier téléchargé ne correspond pas au sha256 annoncé.
class ApkIntegrityException implements Exception {
  const ApkIntegrityException();

  @override
  String toString() => 'ApkIntegrityException: empreinte sha256 invalide';
}
