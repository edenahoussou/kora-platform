import 'package:equatable/equatable.dart';

import 'app_version.dart';

/// Bloc `update` renvoyé par le serveur (MEZ-150) — vérification au
/// lancement (`GET /api/v1/apps/ops-mobile/update`) et réponses
/// `ops-mobile/terminal/handshake|sync|activity`. Voir
/// `AppUpdateCheck::toPayload()` côté backend.
class AppUpdateInfo extends Equatable {
  const AppUpdateInfo({
    required this.latestVersion,
    required this.latestBuild,
    required this.serverMandatory,
    required this.releaseUuid,
    required this.downloadUrl,
    required this.sha256,
    this.minimumVersion,
    this.minimumBuild,
    this.releaseNotes,
    this.downloadPageUrl,
    this.sizeBytes,
    this.publishedAt,
  });

  final String latestVersion;
  final int latestBuild;

  /// Verdict du serveur, calculé avec la version qu'il connaît du
  /// terminal — voir [isMandatoryFor], qui le recoupe avec la version
  /// réellement installée.
  final bool serverMandatory;

  /// Dernière release obligatoire publiée (seuil minimal), si elle existe.
  final String? minimumVersion;
  final int? minimumBuild;

  final String releaseUuid;
  final String? releaseNotes;
  final String downloadUrl;
  final String? downloadPageUrl;

  /// Empreinte attendue du fichier téléchargé — vérifiée AVANT de lancer
  /// l'installation.
  final String sha256;
  final int? sizeBytes;
  final DateTime? publishedAt;

  AppVersion get latest =>
      AppVersion.fromParts(latestVersion, latestBuild) ??
      AppVersion(0, 0, 0, build: latestBuild);

  AppVersion? get minimum => AppVersion.fromParts(minimumVersion, minimumBuild);

  /// Libellé affiché : `1.4.0 (build 42)`.
  String get latestLabel => '$latestVersion (build $latestBuild)';

  /// Une version plus récente que celle installée est publiée. Version
  /// installée inconnue → le serveur a publié une version, on la propose.
  bool isAvailableFor(AppVersion? installed) {
    if (installed == null) return true;
    return installed.isOlderThan(latest);
  }

  /// Mise à jour obligatoire pour la version installée : recalculée
  /// localement à partir du seuil minimal quand il est connu, pour ne
  /// jamais bloquer un terminal déjà à jour si le serveur se base sur une
  /// version remontée plus ancienne (ex. réponse `sync` juste après
  /// l'installation, avant le handshake suivant).
  bool isMandatoryFor(AppVersion? installed) {
    if (!isAvailableFor(installed)) return false;

    final AppVersion? threshold = minimum;
    if (installed != null && threshold != null) {
      return installed.isOlderThan(threshold);
    }

    return serverMandatory;
  }

  @override
  List<Object?> get props => <Object?>[
        latestVersion,
        latestBuild,
        serverMandatory,
        minimumVersion,
        minimumBuild,
        releaseUuid,
        releaseNotes,
        downloadUrl,
        downloadPageUrl,
        sha256,
        sizeBytes,
        publishedAt,
      ];
}
