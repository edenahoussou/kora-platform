import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/user_entity.dart';
import '../../domain/usecases/forgot_password.dart';
import '../../domain/usecases/get_saved_email.dart';
import '../../domain/usecases/login_with_password.dart';
import '../../domain/usecases/send_otp.dart';
import '../../domain/usecases/verify_otp.dart';
import '../../domain/usecases/verify_pin.dart';

part 'login_state.dart';

/// LoginCubit plutôt qu'un LoginBloc : chaque étape (email+mot de passe,
/// PIN, OTP) est déclenchée directement par une action utilisateur sur
/// l'écran courant — voir document.md § Bloc vs Cubit.
///
/// Deux parcours (voir document.md § Flux d'authentification) :
///   - **Première connexion sur cet appareil** : email + mot de passe →
///     PIN → OTP d'activation → connecté.
///   - **Appareil déjà reconnu** (email déjà activé une fois) : PIN seul →
///     connecté, sans repasser par l'email ni l'OTP.
class LoginCubit extends Cubit<LoginState> {
  LoginCubit({
    required GetSavedEmail getSavedEmail,
    required LoginWithPassword loginWithPassword,
    required ForgotPassword forgotPassword,
    required SendOtp sendOtp,
    required VerifyOtp verifyOtp,
    required VerifyPin verifyPin,
  })  : _getSavedEmail = getSavedEmail,
        _loginWithPassword = loginWithPassword,
        _forgotPassword = forgotPassword,
        _sendOtp = sendOtp,
        _verifyOtp = verifyOtp,
        _verifyPin = verifyPin,
        super(const LoginState());

  final GetSavedEmail _getSavedEmail;
  final LoginWithPassword _loginWithPassword;
  final ForgotPassword _forgotPassword;
  final SendOtp _sendOtp;
  final VerifyOtp _verifyOtp;
  final VerifyPin _verifyPin;

  /// Relit l'email déjà activé sur l'appareil (reconnexion), pour
  /// l'afficher en sous-titre de l'écran PIN et l'utiliser lors de la
  /// vérification.
  Future<String?> loadSavedEmail() async {
    final result = await _getSavedEmail();
    final String? email =
        result.when(onSuccess: (value) => value, onFailure: (_) => null);
    if (email != null) {
      emit(state.copyWith(email: email, pendingOtpActivation: false));
    }
    return email;
  }

  Future<bool> submitEmailPassword(String email, String password) async {
    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _loginWithPassword(email: email, password: password);
    return result.when(
      onSuccess: (_) {
        emit(
          state.copyWith(
            status: LoginStatus.success,
            email: email,
            pendingOtpActivation: true,
          ),
        );
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  Future<bool> submitForgotPassword(String email) async {
    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _forgotPassword(email: email);
    return result.when(
      onSuccess: (_) {
        emit(state.copyWith(status: LoginStatus.success));
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  /// Vérifie le PIN pour [state.email]. Ne déclenche **pas** l'envoi de
  /// l'OTP lui-même — l'écran appelant décide, selon
  /// [LoginState.pendingOtpActivation], d'appeler [requestActivationOtp]
  /// (première connexion) ou d'authentifier directement (reconnexion).
  Future<bool> submitPin(String pin) async {
    final String? email = state.email;
    if (email == null) {
      emit(state.copyWith(
          status: LoginStatus.failure, errorMessage: 'Email manquant.'));
      return false;
    }

    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _verifyPin(email: email, pin: pin);
    switch (result) {
      case Success<UserEntity>(value: final UserEntity user):
        emit(state.copyWith(status: LoginStatus.success, user: user));
        return true;
      case ResultFailure<UserEntity>(failure: final failure):
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
    }
  }

  /// Envoie l'OTP d'activation — première connexion sur cet appareil
  /// uniquement (après un PIN valide).
  Future<bool> requestActivationOtp() async {
    final String? email = state.email;
    if (email == null) return false;

    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _sendOtp(email: email);
    return result.when(
      onSuccess: (_) {
        emit(state.copyWith(status: LoginStatus.success));
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  Future<bool> submitOtp(String code) async {
    final String? email = state.email;
    if (email == null) {
      emit(state.copyWith(
          status: LoginStatus.failure, errorMessage: 'Email manquant.'));
      return false;
    }

    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _verifyOtp(email: email, code: code);
    return result.when(
      onSuccess: (UserEntity user) {
        emit(state.copyWith(
            status: LoginStatus.success,
            user: user,
            pendingOtpActivation: false));
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  Future<bool> resendOtp() async {
    final String? email = state.email;
    if (email == null) return false;

    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _sendOtp(email: email);
    return result.when(
      onSuccess: (_) {
        emit(state.copyWith(status: LoginStatus.idle));
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  void reset() => emit(const LoginState());
}
