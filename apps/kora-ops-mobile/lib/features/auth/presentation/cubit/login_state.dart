part of 'login_cubit.dart';

enum LoginStatus { idle, submitting, success, failure }

/// État unique pour le parcours de connexion PIN → (nouvel appareil) → OTP.
/// Les écrans sont des routes GoRouter séparées mais partagent une seule
/// instance de [LoginCubit] (voir injection.dart) pour ne pas perdre le
/// contexte (email, infos appareil) en changeant d'écran.
class LoginState extends Equatable {
  const LoginState({
    this.status = LoginStatus.idle,
    this.email,
    this.deviceInfo,
    this.errorMessage,
    this.user,
  });

  final LoginStatus status;
  final String? email;
  final DeviceInfo? deviceInfo;
  final String? errorMessage;
  final UserEntity? user;

  LoginState copyWith({
    LoginStatus? status,
    String? email,
    DeviceInfo? deviceInfo,
    String? errorMessage,
    bool clearError = false,
    UserEntity? user,
  }) {
    return LoginState(
      status: status ?? this.status,
      email: email ?? this.email,
      deviceInfo: deviceInfo ?? this.deviceInfo,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      user: user ?? this.user,
    );
  }

  @override
  List<Object?> get props =>
      <Object?>[status, email, deviceInfo, errorMessage, user];
}
