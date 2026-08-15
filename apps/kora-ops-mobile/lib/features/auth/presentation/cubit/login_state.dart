part of 'login_cubit.dart';

enum LoginStatus { idle, submitting, success, failure }

/// Distingue les deux raisons d'envoyer un OTP : création de compte (après
/// saisie de l'email) ou confirmation d'un nouvel appareil (après un PIN
/// valide sur un appareil non reconnu) — l'écran OTP est le même composant
/// dans les deux cas, seul le libellé de retour diffère potentiellement.
enum OtpPurpose { accountCreation, deviceConfirmation }

/// État unique pour tout le parcours de connexion — les écrans sont des
/// routes GoRouter séparées (fidèles à la maquette), mais partagent une
/// seule instance de [LoginCubit] (voir injection.dart) pour ne pas perdre
/// l'email/le contexte en changeant d'écran.
class LoginState extends Equatable {
  const LoginState({
    this.status = LoginStatus.idle,
    this.email,
    this.otpPurpose,
    this.deviceInfo,
    this.errorMessage,
    this.user,
  });

  final LoginStatus status;
  final String? email;
  final OtpPurpose? otpPurpose;
  final DeviceInfo? deviceInfo;
  final String? errorMessage;
  final UserEntity? user;

  LoginState copyWith({
    LoginStatus? status,
    String? email,
    OtpPurpose? otpPurpose,
    DeviceInfo? deviceInfo,
    String? errorMessage,
    bool clearError = false,
    UserEntity? user,
  }) {
    return LoginState(
      status: status ?? this.status,
      email: email ?? this.email,
      otpPurpose: otpPurpose ?? this.otpPurpose,
      deviceInfo: deviceInfo ?? this.deviceInfo,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      user: user ?? this.user,
    );
  }

  @override
  List<Object?> get props =>
      <Object?>[status, email, otpPurpose, deviceInfo, errorMessage, user];
}
