import 'package:equatable/equatable.dart';
import 'package:flutter_bloc/flutter_bloc.dart';
import '../../domain/entities/user_entity.dart';
import '../../domain/usecases/get_current_user.dart';
import '../../domain/usecases/get_saved_email.dart';
import '../../domain/usecases/logout_user.dart';

part 'auth_state.dart';

/// État de session global — c'est la seule source de vérité utilisée par
/// [AuthGuard] pour rediriger entre les écrans d'authentification et
/// `/home`. La logique détaillée du flux (email → OTP → PIN → nouvel
/// appareil) vit dans `LoginCubit`, propre à la feature de connexion ;
/// celui-ci appelle [authenticate] une fois la connexion aboutie.
class AuthCubit extends Cubit<AuthState> {
  AuthCubit({
    required GetCurrentUser getCurrentUser,
    required GetSavedEmail getSavedEmail,
    required LogoutUser logoutUser,
  })  : _getCurrentUser = getCurrentUser,
        _getSavedEmail = getSavedEmail,
        _logoutUser = logoutUser,
        super(const AuthInitial());

  final GetCurrentUser _getCurrentUser;
  final GetSavedEmail _getSavedEmail;
  final LogoutUser _logoutUser;

  Future<void> checkAuthStatus() async {
    emit(const AuthLoading());
    final result = await _getCurrentUser();
    result.when(
      onSuccess: (UserEntity? user) async {
        if (user != null) {
          emit(AuthAuthenticated(user));
          return;
        }
        final emailResult = await _getSavedEmail();
        emit(
          AuthUnauthenticated(
            savedEmail: emailResult.when(
                onSuccess: (email) => email, onFailure: (_) => null),
          ),
        );
      },
      onFailure: (failure) => emit(const AuthUnauthenticated()),
    );
  }

  /// Appelé par `LoginCubit` une fois l'authentification aboutie (via OTP ou
  /// PIN).
  void authenticate(UserEntity user) => emit(AuthAuthenticated(user));

  Future<void> logout() async {
    emit(const AuthLoading());
    final result = await _logoutUser();
    result.when(
      onSuccess: (_) async {
        final emailResult = await _getSavedEmail();
        emit(
          AuthUnauthenticated(
            savedEmail: emailResult.when(
                onSuccess: (email) => email, onFailure: (_) => null),
          ),
        );
      },
      onFailure: (failure) => emit(const AuthUnauthenticated()),
    );
  }
}
