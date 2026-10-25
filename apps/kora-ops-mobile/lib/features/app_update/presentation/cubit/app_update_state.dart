part of 'app_update_cubit.dart';

/// Étape de la mise à jour en cours (après que l'utilisateur a touché
/// « Mettre à jour »).
enum AppUpdatePhase {
  /// Rien en cours.
  idle,

  /// Téléchargement de l'APK (puis vérification sha256 quand
  /// [AppUpdateState.progress] atteint 1).
  downloading,

  /// APK vérifié, Android demande d'autoriser KORA OPS à installer des
  /// applications (Android 8+).
  awaitingPermission,

  /// L'installeur système est ouvert : Android remplace l'app.
  installerOpened,

  /// Échec — voir [AppUpdateState.errorMessage] ; l'utilisateur peut
  /// réessayer.
  failed,
}

class AppUpdateState extends Equatable {
  const AppUpdateState({
    this.installed,
    this.info,
    this.phase = AppUpdatePhase.idle,
    this.progress,
    this.errorMessage,
    this.apkPath,
    this.promptDismissed = false,
  });

  /// Version installée (`null` hors Android : la fonctionnalité est alors
  /// inactive).
  final AppVersion? installed;

  /// Dernier bloc `update` connu (serveur ou cache local).
  final AppUpdateInfo? info;

  final AppUpdatePhase phase;

  /// Avancement du téléchargement (0 → 1), `null` si inconnu.
  final double? progress;
  final String? errorMessage;

  /// APK téléchargé et vérifié, en attente d'installation.
  final String? apkPath;

  /// Le prompt « Nouvelle version disponible » a été fermé ou reporté.
  final bool promptDismissed;

  /// Une version plus récente que celle installée est publiée.
  bool get updateAvailable =>
      installed != null && info != null && info!.isAvailableFor(installed);

  /// Mise à jour obligatoire → écran bloquant non-dismissible.
  bool get isBlocking =>
      installed != null && info != null && info!.isMandatoryFor(installed);

  /// Mise à jour non obligatoire, non encore écartée par l'utilisateur.
  bool get shouldPromptOptional =>
      updateAvailable && !isBlocking && !promptDismissed;

  bool get isBusy => phase == AppUpdatePhase.downloading;

  /// L'avancement atteint 100 % : le sha256 est en cours de vérification.
  bool get isVerifying => isBusy && (progress ?? 0) >= 1;

  AppUpdateState copyWith({
    AppVersion? installed,
    AppUpdateInfo? info,
    bool clearInfo = false,
    AppUpdatePhase? phase,
    double? progress,
    bool clearProgress = false,
    String? errorMessage,
    bool clearError = false,
    String? apkPath,
    bool clearApkPath = false,
    bool? promptDismissed,
  }) {
    return AppUpdateState(
      installed: installed ?? this.installed,
      info: clearInfo ? null : (info ?? this.info),
      phase: phase ?? this.phase,
      progress: clearProgress ? null : (progress ?? this.progress),
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      apkPath: clearApkPath ? null : (apkPath ?? this.apkPath),
      promptDismissed: promptDismissed ?? this.promptDismissed,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        installed,
        info,
        phase,
        progress,
        errorMessage,
        apkPath,
        promptDismissed,
      ];
}
