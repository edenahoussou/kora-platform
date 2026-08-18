part of 'login_cubit.dart';

enum LoginStatus { idle, submitting, success, failure }

/// État unique pour le parcours de connexion (voir document.md § Flux
/// d'authentification). Les écrans sont des routes GoRouter séparées mais
/// partagent une seule instance de [LoginCubit] (voir injection.dart) pour
/// ne pas perdre le contexte (email, mot de passe validé…) en changeant
/// d'écran.
class LoginState extends Equatable {
  const LoginState({
    this.status = LoginStatus.idle,
    this.email,
    this.errorMessage,
    this.user,
    this.pendingOtpActivation = false,
  });

  final LoginStatus status;
  final String? email;
  final String? errorMessage;
  final UserEntity? user;

  /// `true` dès qu'un email + mot de passe a été validé (première
  /// connexion sur cet appareil) : le PIN doit alors être suivi d'un OTP
  /// d'activation. `false` pour une reconnexion (email déjà activé sur cet
  /// appareil) : le PIN suffit, pas d'OTP.
  final bool pendingOtpActivation;

  LoginState copyWith({
    LoginStatus? status,
    String? email,
    String? errorMessage,
    bool clearError = false,
    UserEntity? user,
    bool? pendingOtpActivation,
  }) {
    return LoginState(
      status: status ?? this.status,
      email: email ?? this.email,
      errorMessage: clearError ? null : (errorMessage ?? this.errorMessage),
      user: user ?? this.user,
      pendingOtpActivation: pendingOtpActivation ?? this.pendingOtpActivation,
    );
  }

  @override
  List<Object?> get props =>
      <Object?>[status, email, errorMessage, user, pendingOtpActivation];
}
