import 'package:equatable/equatable.dart';

/// Nombre d'exemplaires du manifeste frontière imprimés à chaque demande
/// du convoyeur (ticket MEZ-94 : « trois exemplaires »). Fixe — jamais
/// choisi ni modifié à l'écran.
const int kBorderManifestCopies = 3;

enum BorderManifestPrintJobStatus {
  /// Impression en cours (ou app tuée pendant l'impression — voir
  /// [BorderManifestPrintJob.needsConfirmation]).
  inProgress,

  /// Arrêtée sur une erreur (papier, imprimante…) : reprenable.
  interrupted,

  /// Tous les exemplaires demandés sont confirmés imprimés.
  completed,
}

/// Une demande d'impression du manifeste frontière et l'avancement de ses
/// exemplaires, tel que persisté dans `border_manifest_print_jobs`.
///
/// ### Invariants (garantis par `BorderManifestPrintRepositoryImpl`)
///
/// - `0 <= confirmedCopies <= requestedCopies` : jamais plus d'exemplaires
///   confirmés que demandés.
/// - Un seul exemplaire à la fois est « en cours » ([inFlightCopy]) et c'est
///   toujours `confirmedCopies + 1`.
/// - Un exemplaire n'est compté dans [confirmedCopies] qu'après confirmation
///   explicite de l'imprimante (ou du convoyeur après un crash).
///
/// Ne contient aucune donnée passager.
class BorderManifestPrintJob extends Equatable {
  const BorderManifestPrintJob({
    required this.jobUuid,
    required this.departureUuid,
    required this.requestedCopies,
    required this.confirmedCopies,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
    this.inFlightCopy,
    this.lastError,
    this.manifestVersion,
    this.userUuid,
    this.terminalSerial,
  });

  final String jobUuid;
  final String departureUuid;
  final int requestedCopies;
  final int confirmedCopies;
  final BorderManifestPrintJobStatus status;

  /// Exemplaire envoyé à l'imprimante dont le résultat n'est pas connu.
  final int? inFlightCopy;

  /// Dernier message d'erreur affichable (papier absent…), `null` si le
  /// dernier exemplaire a réussi.
  final String? lastError;

  final String? manifestVersion;
  final String? userUuid;
  final String? terminalSerial;
  final DateTime createdAt;
  final DateTime updatedAt;

  bool get isCompleted => status == BorderManifestPrintJobStatus.completed;

  /// Reste-t-il des exemplaires à imprimer ?
  bool get hasRemainingCopies => confirmedCopies < requestedCopies;

  /// Numéro du prochain exemplaire à imprimer, `null` si tout est confirmé.
  int? get nextCopy => hasRemainingCopies ? confirmedCopies + 1 : null;

  /// `true` quand un exemplaire a été envoyé à l'imprimante sans qu'on
  /// sache s'il est sorti (crash, redémarrage du terminal) : l'app ne
  /// décide jamais seule — elle demande au convoyeur de constater le
  /// papier, pour ne jamais imprimer un exemplaire de trop en silence.
  bool get needsConfirmation => inFlightCopy != null;

  BorderManifestPrintJob copyWith({
    int? confirmedCopies,
    BorderManifestPrintJobStatus? status,
    DateTime? updatedAt,
    Object? inFlightCopy = _unset,
    Object? lastError = _unset,
  }) {
    return BorderManifestPrintJob(
      jobUuid: jobUuid,
      departureUuid: departureUuid,
      requestedCopies: requestedCopies,
      confirmedCopies: confirmedCopies ?? this.confirmedCopies,
      status: status ?? this.status,
      createdAt: createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      inFlightCopy: identical(inFlightCopy, _unset)
          ? this.inFlightCopy
          : inFlightCopy as int?,
      lastError:
          identical(lastError, _unset) ? this.lastError : lastError as String?,
      manifestVersion: manifestVersion,
      userUuid: userUuid,
      terminalSerial: terminalSerial,
    );
  }

  static const Object _unset = Object();

  @override
  List<Object?> get props => <Object?>[
        jobUuid,
        departureUuid,
        requestedCopies,
        confirmedCopies,
        status,
        inFlightCopy,
        lastError,
        manifestVersion,
        userUuid,
        terminalSerial,
        createdAt,
        updatedAt,
      ];
}
