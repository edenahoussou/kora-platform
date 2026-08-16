part of 'scan_boarding_cubit.dart';

enum ScanBoardingStatus { idle, scanning, valide, refuse, erreur }

class ScanBoardingState extends Equatable {
  const ScanBoardingState({
    this.status = ScanBoardingStatus.idle,
    this.lastScannedCode,
    this.matchedPassenger,
    this.message,
  });

  final ScanBoardingStatus status;
  final String? lastScannedCode;
  final Passenger? matchedPassenger;
  final String? message;

  ScanBoardingState copyWith({
    ScanBoardingStatus? status,
    String? lastScannedCode,
    Passenger? matchedPassenger,
    String? message,
  }) {
    return ScanBoardingState(
      status: status ?? this.status,
      lastScannedCode: lastScannedCode ?? this.lastScannedCode,
      matchedPassenger: matchedPassenger ?? this.matchedPassenger,
      message: message,
    );
  }

  @override
  List<Object?> get props =>
      <Object?>[status, lastScannedCode, matchedPassenger, message];
}
