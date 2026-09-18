part of 'scan_boarding_cubit.dart';

/// Les 7 états métier requis par le ticket MEZ-91 (+ `idle`/`scanning`
/// pour l'UI, + `erreur` pour un échec technique hors périmètre du
/// ticket) — miroir direct de `BoardingOutcomeStatus`, nommé en français
/// pour rester cohérent avec le reste de l'écran.
enum ScanBoardingStatus {
  idle,
  scanning,
  valide,
  mauvaisDepart,
  billetInconnu,
  dejaEmbarque,
  annule,
  conflit,
  enAttenteSync,
  erreur;

  static ScanBoardingStatus fromOutcome(BoardingOutcomeStatus status) =>
      switch (status) {
        BoardingOutcomeStatus.valid => ScanBoardingStatus.valide,
        BoardingOutcomeStatus.wrongDeparture =>
          ScanBoardingStatus.mauvaisDepart,
        BoardingOutcomeStatus.ticketUnknown => ScanBoardingStatus.billetInconnu,
        BoardingOutcomeStatus.alreadyBoarded => ScanBoardingStatus.dejaEmbarque,
        BoardingOutcomeStatus.cancelled => ScanBoardingStatus.annule,
        BoardingOutcomeStatus.conflict => ScanBoardingStatus.conflit,
        BoardingOutcomeStatus.pendingSync => ScanBoardingStatus.enAttenteSync,
        BoardingOutcomeStatus.error => ScanBoardingStatus.erreur,
      };

  /// `true` pour un résultat métier conclusif (un des 7 états du ticket) —
  /// distingue l'affichage "résultat" de `idle`/`scanning`/`erreur`.
  bool get isOutcome =>
      this != ScanBoardingStatus.idle &&
      this != ScanBoardingStatus.scanning &&
      this != ScanBoardingStatus.erreur;
}

class ScanBoardingState extends Equatable {
  const ScanBoardingState({
    this.status = ScanBoardingStatus.idle,
    this.lastCode,
    this.matchedPassenger,
    this.message,
    this.manualQuery = '',
    this.manualResults = const <Passenger>[],
  });

  final ScanBoardingStatus status;
  final String? lastCode;
  final Passenger? matchedPassenger;
  final String? message;

  /// Recherche manuelle par code court (QR illisible) — voir
  /// `ScanBoardingCubit.searchManual`.
  final String manualQuery;
  final List<Passenger> manualResults;

  ScanBoardingState copyWith({
    ScanBoardingStatus? status,
    String? lastCode,
    Passenger? matchedPassenger,
    String? message,
    String? manualQuery,
    List<Passenger>? manualResults,
  }) {
    return ScanBoardingState(
      status: status ?? this.status,
      lastCode: lastCode ?? this.lastCode,
      matchedPassenger: matchedPassenger,
      message: message,
      manualQuery: manualQuery ?? this.manualQuery,
      manualResults: manualResults ?? this.manualResults,
    );
  }

  @override
  List<Object?> get props => <Object?>[
        status,
        lastCode,
        matchedPassenger,
        message,
        manualQuery,
        manualResults,
      ];
}
