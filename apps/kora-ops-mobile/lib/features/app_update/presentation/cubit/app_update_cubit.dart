import 'dart:async';

import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';

import '../../data/services/apk_installer.dart';
import '../../domain/entities/app_update_info.dart';
import '../../domain/entities/app_version.dart';
import '../../domain/repositories/app_update_repository.dart';

part 'app_update_state.dart';

/// Distribution hors store de KORA OPS Mobile (MEZ-150).
///
/// Sources du bloc `update`, toutes ramenées ici :
///  1. au lancement, [bootstrap] relit le dernier bloc connu (cache local,
///     pour rester bloquant hors ligne) puis interroge
///     `GET /api/v1/apps/ops-mobile/update` ;
///  2. en continu, `AppUpdateInterceptor` lit `data.update` dans chaque
///     réponse handshake/sync/activity/session et appelle [applyServerInfo].
///
/// L'état expose [AppUpdateState.isBlocking] (écran plein écran
/// non-dismissible, voir `AppUpdateGate`) et
/// [AppUpdateState.shouldPromptOptional] (prompt « Nouvelle version
/// disponible » reportable).
class AppUpdateCubit extends Cubit<AppUpdateState> {
  AppUpdateCubit(this._repository) : super(const AppUpdateState());

  /// Durée du report « Plus tard » d'une mise à jour non obligatoire.
  static const Duration snoozeDuration = Duration(hours: 24);

  final AppUpdateRepository _repository;
  bool _bootstrapped = false;

  /// Au lancement — non bloquant pour le splash : l'écran bloquant
  /// s'affiche dès que l'information arrive.
  Future<void> bootstrap() async {
    if (_bootstrapped) return;
    _bootstrapped = true;

    final AppVersion? installed = await _repository.installedVersion();
    if (installed == null) return; // Hors Android : rien à gérer.

    emit(state.copyWith(installed: installed));

    final AppUpdateInfo? cached = await _repository.lastKnown();
    if (cached != null) await _apply(cached);

    await checkNow();
  }

  /// Vérification explicite auprès du serveur (lancement, retour au
  /// premier plan). Un échec réseau est silencieux : on garde le dernier
  /// bloc connu.
  Future<void> checkNow() async {
    final AppVersion? installed = state.installed;
    if (installed == null || isClosed) return;

    final result = await _repository.check(installed);
    await result.when<Future<void>>(
      onSuccess: _applyFromServer,
      onFailure: (_) async {},
    );
  }

  /// Bloc `data.update` lu dans une réponse OPS Mobile (voir
  /// `AppUpdateInterceptor`). `null` = aucune release publiée.
  Future<void> applyServerInfo(AppUpdateInfo? info) async {
    if (state.installed == null || isClosed) return;
    await _applyFromServer(info);
  }

  Future<void> _applyFromServer(AppUpdateInfo? info) async {
    await _repository.remember(info);
    if (isClosed) return;

    if (info == null) {
      // Toutes les releases ont été archivées : plus rien à proposer
      // (sauf si un téléchargement est déjà en cours).
      if (!state.isBusy) {
        emit(state.copyWith(clearInfo: true, phase: AppUpdatePhase.idle));
      }
      return;
    }

    await _apply(info);
  }

  Future<void> _apply(AppUpdateInfo info) async {
    final bool sameRelease = state.info?.releaseUuid == info.releaseUuid;
    final bool snoozed = !info.isMandatoryFor(state.installed) &&
        await _repository.isSnoozed(info);

    if (isClosed) return;

    emit(
      state.copyWith(
        info: info,
        // Nouvelle release → on repropose (sauf report en cours).
        promptDismissed: snoozed || (sameRelease && state.promptDismissed),
        // Une autre release que celle téléchargée : l'APK local n'est plus
        // la bonne cible.
        clearApkPath: !sameRelease,
      ),
    );
  }

  /// « Plus tard » sur le prompt non obligatoire : reporté 24 h.
  Future<void> postpone() async {
    final AppUpdateInfo? info = state.info;
    if (info == null || state.isBlocking) return;

    emit(state.copyWith(promptDismissed: true));
    await _repository.snooze(info, snoozeDuration);
  }

  /// Fermeture du prompt sans report (session courante uniquement).
  void dismissPrompt() {
    if (state.isBlocking) return;
    emit(state.copyWith(promptDismissed: true));
  }

  /// « Télécharger et installer » : téléchargement, vérification sha256,
  /// puis installeur Android.
  Future<void> startUpdate() async {
    final AppUpdateInfo? info = state.info;
    if (info == null || state.isBusy) return;

    String? apkPath = state.apkPath;

    if (apkPath == null) {
      emit(
        state.copyWith(
          phase: AppUpdatePhase.downloading,
          progress: 0,
          clearError: true,
        ),
      );

      final result = await _repository.downloadAndVerify(
        info,
        onProgress: (double? progress) {
          if (!isClosed && state.phase == AppUpdatePhase.downloading) {
            emit(
              progress == null
                  ? state.copyWith(clearProgress: true)
                  : state.copyWith(progress: progress),
            );
          }
        },
      );

      if (isClosed) return;

      apkPath = result.when<String?>(
        onSuccess: (String path) => path,
        onFailure: (failure) {
          emit(
            state.copyWith(
              phase: AppUpdatePhase.failed,
              errorMessage: failure.message,
              clearProgress: true,
            ),
          );
          return null;
        },
      );

      if (apkPath == null) return;

      emit(state.copyWith(apkPath: apkPath, progress: 1));
    }

    await _install(apkPath);
  }

  /// Ouvre l'écran système « Installer des applis inconnues ».
  Future<void> openPermissionSettings() =>
      _repository.openInstallPermissionSettings();

  /// Retour au premier plan : après le réglage de l'autorisation, on
  /// relance l'installation ; après un installeur annulé, on réactive le
  /// bouton ; dans tous les cas on revérifie la version publiée.
  Future<void> onAppResumed() async {
    if (state.installed == null || isClosed) return;

    switch (state.phase) {
      case AppUpdatePhase.awaitingPermission:
        final String? apkPath = state.apkPath;
        if (apkPath != null) await _install(apkPath);
      case AppUpdatePhase.installerOpened:
        emit(state.copyWith(phase: AppUpdatePhase.idle));
      case AppUpdatePhase.idle ||
            AppUpdatePhase.failed ||
            AppUpdatePhase.downloading:
        break;
    }

    if (!state.isBusy) unawaited(checkNow());
  }

  Future<void> _install(String apkPath) async {
    final result = await _repository.install(apkPath);
    if (isClosed) return;

    result.when(
      onSuccess: (ApkInstallOutcome outcome) => emit(
        state.copyWith(
          phase: outcome == ApkInstallOutcome.permissionRequired
              ? AppUpdatePhase.awaitingPermission
              : AppUpdatePhase.installerOpened,
          clearError: true,
        ),
      ),
      onFailure: (failure) => emit(
        state.copyWith(
          phase: AppUpdatePhase.failed,
          errorMessage: failure.message,
          // Le fichier peut avoir disparu (cache vidé) : on retéléchargera.
          clearApkPath: true,
        ),
      ),
    );
  }
}
