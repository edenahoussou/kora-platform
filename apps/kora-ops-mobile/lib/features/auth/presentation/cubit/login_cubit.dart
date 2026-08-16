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

/// LoginCubit plutôt qu'un LoginBloc : chaque étape (PIN, nouvel appareil,
/// OTP) est déclenchée directement par une action utilisateur sur l'écran
/// courant — voir document.md § Bloc vs Cubit.
///
/// Les agents KORA OPS sont provisionnés par un administrateur : il n'y a
/// pas d'auto-inscription. L'écran d'entrée est donc directement le PIN
/// (voir document.md § Flux d'authentification) ; l'OTP ne sert qu'à
/// confirmer un appareil non reconnu, jamais à créer un compte.
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

  /// Relit l'email de l'agent provisionné sur l'appareil, pour l'afficher
  /// en sous-titre de l'écran PIN.
  Future<String?> loadSavedEmail() async {
    final result = await _getSavedEmail();
    final String? email =
        result.when(onSuccess: (value) => value, onFailure: (_) => null);
    if (email != null) emit(state.copyWith(email: email));
    return email;
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
        emit(state.copyWith(
            status: LoginStatus.success, user: user, deviceInfo: device));
        return true;
      case ResultFailure<UserEntity>(failure: final failure):
        emit(state.copyWith(
            status: LoginStatus.failure, errorMessage: failure.message));
        return false;
    }
  }

  /// Depuis l'écran "Nouvel appareil détecté" : demande un OTP de
  /// confirmation.
  Future<bool> requestDeviceConfirmationOtp() async {
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
        emit(state.copyWith(status: LoginStatus.success, user: user));
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
