import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../../core/result/result.dart';
import '../../domain/entities/device_info.dart';
import '../../domain/entities/user_entity.dart';
import '../../domain/usecases/get_current_device_info.dart';
import '../../domain/usecases/get_saved_email.dart';
import '../../domain/usecases/send_otp.dart';
import '../../domain/usecases/verify_otp.dart';
import '../../domain/usecases/verify_pin.dart';

part 'login_state.dart';

/// LoginCubit plutôt qu'un LoginBloc : chaque étape (email, OTP, PIN,
/// nouvel appareil) est déclenchée directement par une action utilisateur
/// sur l'écran courant, sans combinaison d'événements — voir document.md
/// § Bloc vs Cubit. Les méthodes retournent `Future<bool>` (succès/échec)
/// EN PLUS d'émettre un état : les écrans utilisent le `bool` pour décider
/// de la navigation (`context.push(...)`), et l'état pour l'affichage
/// (spinner, message d'erreur).
class LoginCubit extends Cubit<LoginState> {
  LoginCubit({
    required GetSavedEmail getSavedEmail,
    required SendOtp sendOtp,
    required VerifyOtp verifyOtp,
    required VerifyPin verifyPin,
    required GetCurrentDeviceInfo getCurrentDeviceInfo,
  })  : _getSavedEmail = getSavedEmail,
        _sendOtp = sendOtp,
        _verifyOtp = verifyOtp,
        _verifyPin = verifyPin,
        _getCurrentDeviceInfo = getCurrentDeviceInfo,
        super(const LoginState());

  final GetSavedEmail _getSavedEmail;
  final SendOtp _sendOtp;
  final VerifyOtp _verifyOtp;
  final VerifyPin _verifyPin;
  final GetCurrentDeviceInfo _getCurrentDeviceInfo;

  /// Relit l'email enregistré sur l'appareil, pour l'afficher en sous-titre
  /// de l'écran PIN (voir maquette : "aicha.hounkpe@example.com" sous "Votre
  /// PIN KORA").
  Future<String?> loadSavedEmail() async {
    final result = await _getSavedEmail();
    final String? email = result.when(onSuccess: (value) => value, onFailure: (_) => null);
    if (email != null) emit(state.copyWith(email: email));
    return email;
  }

  Future<bool> submitEmail(String email) async {
    emit(state.copyWith(status: LoginStatus.submitting, email: email, clearError: true));
    final result = await _sendOtp(email: email);
    return result.when(
      onSuccess: (_) {
        emit(
          state.copyWith(status: LoginStatus.success, otpPurpose: OtpPurpose.accountCreation),
        );
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  Future<bool> submitOtp(String code) async {
    final String? email = state.email;
    if (email == null) {
      emit(state.copyWith(status: LoginStatus.failure, errorMessage: 'Email manquant.'));
      return false;
    }

    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _verifyOtp(email: email, code: code);
    return result.when(
      onSuccess: (UserEntity user) {
        emit(state.copyWith(status: LoginStatus.success, user: user));
        return true;
      },
      onFailure: (failure) {
        // Toute vérification échouée mène à l'écran dédié "Code non valide"
        // (voir maquette) plutôt qu'une erreur inline.
        emit(state.copyWith(status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  /// Renvoie un OTP pour l'email courant, sans changer `otpPurpose` (on
  /// reste dans le même contexte : création de compte ou confirmation
  /// d'appareil).
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
        emit(state.copyWith(status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  Future<bool> submitPin(String pin) async {
    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _verifyPin(pin: pin);
    switch (result) {
      case Success<UserEntity>(value: final UserEntity user):
        final deviceResult = await _getCurrentDeviceInfo();
        final DeviceInfo? device = deviceResult.when(
          onSuccess: (info) => info,
          onFailure: (_) => null,
        );
        emit(state.copyWith(status: LoginStatus.success, user: user, deviceInfo: device));
        return true;
      case ResultFailure<UserEntity>(failure: final failure):
        emit(state.copyWith(status: LoginStatus.failure, errorMessage: failure.message));
        return false;
    }
  }

  /// Depuis l'écran "Nouvel appareil détecté" : demande un OTP de
  /// confirmation (même email, nouveau contexte `deviceConfirmation`).
  Future<bool> requestDeviceConfirmationOtp() async {
    final String? email = state.email;
    if (email == null) return false;

    emit(state.copyWith(status: LoginStatus.submitting, clearError: true));
    final result = await _sendOtp(email: email);
    return result.when(
      onSuccess: (_) {
        emit(
          state.copyWith(status: LoginStatus.success, otpPurpose: OtpPurpose.deviceConfirmation),
        );
        return true;
      },
      onFailure: (failure) {
        emit(state.copyWith(status: LoginStatus.failure, errorMessage: failure.message));
        return false;
      },
    );
  }

  /// Réinitialise le flux (ex. après "Modifier l'email" ou "Ce n'est pas
  /// moi") pour repartir d'un écran email propre.
  void reset() => emit(const LoginState());
}
