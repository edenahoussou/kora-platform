import 'package:equatable/equatable.dart';

/// Version d'application : semver (`MAJEUR.MINEUR.CORRECTIF[-pre]`) + numéro
/// de build optionnel (`versionCode` Android, le `+42` du pubspec).
///
/// Miroir exact de `AppVersion` côté backend (MEZ-150) : précédence semver
/// d'abord (pré-versions comprises), puis build quand les deux versions en
/// portent un. Un build absent d'un côté ne départage jamais.
class AppVersion extends Equatable implements Comparable<AppVersion> {
  const AppVersion(
    this.major,
    this.minor,
    this.patch, {
    this.preRelease,
    this.build,
  });

  final int major;
  final int minor;
  final int patch;
  final String? preRelease;
  final int? build;

  static final RegExp _pattern = RegExp(
    r'^v?(\d+)\.(\d+)(?:\.(\d+))?(?:-([0-9A-Za-z.-]+))?(?:\s*(?:\+|\()\s*(\d+)\s*\)?)?$',
    caseSensitive: false,
  );

  /// Lecture tolérante (`1.4.0`, `v1.4.0`, `1.4.0+42`, `1.4.0 (42)`,
  /// `1.4`). `null` si la chaîne est vide ou illisible.
  static AppVersion? tryParse(String? raw) {
    final String value = raw?.trim() ?? '';
    if (value.isEmpty) return null;

    final RegExpMatch? match = _pattern.firstMatch(value);
    if (match == null) return null;

    final String? patch = match.group(3);
    final String? preRelease = match.group(4);
    final String? build = match.group(5);

    return AppVersion(
      int.parse(match.group(1)!),
      int.parse(match.group(2)!),
      patch == null || patch.isEmpty ? 0 : int.parse(patch),
      preRelease: preRelease == null || preRelease.isEmpty ? null : preRelease,
      build: build == null || build.isEmpty ? null : int.parse(build),
    );
  }

  /// Construit une version à partir d'un semver et d'un build séparés
  /// (format du bloc `update` : `latest_version` + `latest_build`).
  static AppVersion? fromParts(String? semver, int? build) {
    final AppVersion? version = tryParse(semver);
    if (version == null) return null;

    return version.withBuild(build);
  }

  AppVersion withBuild(int? build) => AppVersion(
        major,
        minor,
        patch,
        preRelease: preRelease,
        build: build,
      );

  /// `1.4.0-beta.1`
  String get semver {
    final String core = '$major.$minor.$patch';
    return preRelease == null ? core : '$core-$preRelease';
  }

  bool isOlderThan(AppVersion other) => compareTo(other) < 0;

  bool isNewerThan(AppVersion other) => compareTo(other) > 0;

  @override
  int compareTo(AppVersion other) {
    for (final (int left, int right) in <(int, int)>[
      (major, other.major),
      (minor, other.minor),
      (patch, other.patch),
    ]) {
      if (left != right) return left.compareTo(right);
    }

    final int preReleaseOrder =
        _comparePreRelease(preRelease, other.preRelease);
    if (preReleaseOrder != 0) return preReleaseOrder;

    if (build == null || other.build == null) return 0;

    return build!.compareTo(other.build!);
  }

  static int _comparePreRelease(String? left, String? right) {
    if (left == right) return 0;
    if (left == null) return 1;
    if (right == null) return -1;

    final List<String> leftParts = left.split('.');
    final List<String> rightParts = right.split('.');
    final int length = leftParts.length > rightParts.length
        ? leftParts.length
        : rightParts.length;

    for (int index = 0; index < length; index++) {
      if (index >= leftParts.length) return -1;
      if (index >= rightParts.length) return 1;

      final String leftPart = leftParts[index];
      final String rightPart = rightParts[index];
      final int? leftNumber = int.tryParse(leftPart);
      final int? rightNumber = int.tryParse(rightPart);

      final int result = switch ((leftNumber, rightNumber)) {
        (final int l, final int r) => l.compareTo(r),
        (int(), null) => -1,
        (null, int()) => 1,
        _ => leftPart.compareTo(rightPart).sign,
      };

      if (result != 0) return result;
    }

    return 0;
  }

  /// `1.4.0+42` — forme envoyée au serveur dans `app_version`.
  @override
  String toString() => build == null ? semver : '$semver+$build';

  @override
  List<Object?> get props => <Object?>[major, minor, patch, preRelease, build];
}
