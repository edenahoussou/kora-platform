import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/services.dart';
import 'package:flutter_networking/flutter_networking.dart'
    show ApiException, NetworkApiException, TimeoutApiException;

import '../../../../core/errors/failures.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/app_update_info.dart';
import '../../domain/entities/app_version.dart';
import '../../domain/repositories/app_update_repository.dart';
import '../datasources/app_update_local_datasource.dart';
import '../datasources/app_update_remote_datasource.dart';
import '../models/app_update_dtos.dart';
import '../services/apk_downloader.dart';
import '../services/apk_installer.dart';

/// Couche DATA : assemble API, téléchargement, contrôle d'intégrité et
/// installeur natif derrière le contrat du domaine (MEZ-150).
class AppUpdateRepositoryImpl implements AppUpdateRepository {
  AppUpdateRepositoryImpl(
    this._remote,
    this._local,
    this._downloader,
    this._installer, {
    DateTime Function()? now,
  }) : _now = now ?? DateTime.now;

  final AppUpdateRemoteDataSource _remote;
  final AppUpdateLocalDataSource _local;
  final ApkDownloader _downloader;
  final ApkInstaller _installer;
  final DateTime Function() _now;

  @override
  Future<AppVersion?> installedVersion() => _installer.installedVersion();

  @override
  Future<Result<AppUpdateInfo?>> check(AppVersion? installed) async {
    try {
      return Result<AppUpdateInfo?>.success(await _remote.check(installed));
    } on DioException catch (error) {
      return Result<AppUpdateInfo?>.failure(_networkFailure(error));
    }
  }

  @override
  Future<AppUpdateInfo?> lastKnown() async =>
      AppUpdateDtos.fromBlock(await _local.readLastKnownBlock());

  @override
  Future<void> remember(AppUpdateInfo? info) => _local.saveLastKnownBlock(
        info == null ? null : AppUpdateDtos.toBlock(info),
      );

  @override
  Future<Result<String>> downloadAndVerify(
    AppUpdateInfo info, {
    void Function(double? progress)? onProgress,
  }) async {
    final String? directory = await _installer.updatesDirectory();
    if (directory == null) {
      return const Result<String>.failure(
        AppUpdateFailure(
          AppUpdateFailure.unsupported,
          'La mise à jour automatique n’est disponible que sur Android.',
        ),
      );
    }

    final String fileName =
        'kora-ops-mobile-${info.latestVersion}-${info.latestBuild}.apk'
            .replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');

    try {
      final File file = await _downloader.download(
        url: info.downloadUrl,
        targetPath: '$directory${Platform.pathSeparator}$fileName',
        expectedSha256: info.sha256,
        onProgress: (int received, int total) {
          final int expected = total > 0 ? total : (info.sizeBytes ?? 0);
          onProgress?.call(
            expected > 0 ? (received / expected).clamp(0, 1).toDouble() : null,
          );
        },
      );

      return Result<String>.success(file.path);
    } on ApkIntegrityException {
      return const Result<String>.failure(
        AppUpdateFailure(
          AppUpdateFailure.integrity,
          'Le fichier téléchargé est corrompu ou incomplet (empreinte '
          'SHA-256 invalide). Il a été supprimé : réessayez.',
        ),
      );
    } on DioException catch (error) {
      final Failure failure = _networkFailure(error);
      return Result<String>.failure(
        AppUpdateFailure(AppUpdateFailure.download, failure.message),
      );
    } on FileSystemException {
      return const Result<String>.failure(
        AppUpdateFailure(
          AppUpdateFailure.download,
          'Espace de stockage insuffisant pour télécharger la mise à jour.',
        ),
      );
    }
  }

  @override
  Future<Result<ApkInstallOutcome>> install(String apkPath) async {
    try {
      return Result<ApkInstallOutcome>.success(
        await _installer.install(apkPath),
      );
    } on PlatformException catch (error) {
      return Result<ApkInstallOutcome>.failure(
        AppUpdateFailure(
          AppUpdateFailure.install,
          error.message ?? 'L’installeur Android n’a pas pu être lancé.',
        ),
      );
    } on MissingPluginException {
      return const Result<ApkInstallOutcome>.failure(
        AppUpdateFailure(
          AppUpdateFailure.unsupported,
          'La mise à jour automatique n’est disponible que sur Android.',
        ),
      );
    }
  }

  @override
  Future<void> openInstallPermissionSettings() =>
      _installer.openInstallPermissionSettings();

  @override
  Future<bool> isSnoozed(AppUpdateInfo info) async {
    final DateTime? until = await _local.snoozedUntil(info.latestBuild);
    return until != null && until.isAfter(_now());
  }

  @override
  Future<void> snooze(AppUpdateInfo info, Duration duration) =>
      _local.snooze(info.latestBuild, duration);

  Failure _networkFailure(DioException error) {
    final Object? apiError = error.error;
    // Même lecture que TerminalRepositoryImpl._failureOf.
    final String? message = apiError is ApiException ? apiError.message : null;

    return switch (apiError) {
      NetworkApiException() => const NetworkFailure(),
      TimeoutApiException() =>
        const NetworkFailure('Le serveur met trop de temps à répondre.'),
      ApiException() => ServerFailure(
          message ??
              'Le serveur de mise à jour est momentanément indisponible.',
        ),
      _ => switch (error.type) {
          DioExceptionType.connectionError ||
          DioExceptionType.connectionTimeout ||
          DioExceptionType.receiveTimeout ||
          DioExceptionType.sendTimeout =>
            const NetworkFailure(),
          _ => const ServerFailure(
              'Le serveur de mise à jour est momentanément indisponible.',
            ),
        },
    };
  }
}
